"use server";

import bcrypt from "bcryptjs";
import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import { criarSessao, encerrarSessao, ehAdmin, exigirUsuario, orgsGeridas } from "@/lib/auth";
import {
  buscarRedefinicao,
  criarLinkDeSenha,
  limparFalhasLogin,
  loginBloqueado,
  registrarFalhaLogin,
} from "@/lib/senha";
import { usarConvite } from "@/lib/escola";

const txt = (f: FormData, k: string) => String(f.get(k) ?? "").trim();
const voltar = (rota: string, erro: string): never => redirect(`${rota}?erro=${encodeURIComponent(erro)}`);

function idade(nasc: Date) {
  const hoje = new Date();
  let anos = hoje.getFullYear() - nasc.getFullYear();
  const m = hoje.getMonth() - nasc.getMonth();
  if (m < 0 || (m === 0 && hoje.getDate() < nasc.getDate())) anos--;
  return anos;
}

export async function cadastrar(f: FormData) {
  const nome = txt(f, "nome");
  const email = txt(f, "email").toLowerCase();
  const senha = String(f.get("senha") ?? "");
  const nascTxt = txt(f, "nascimento");
  const codigo = txt(f, "codigo");
  const rota = codigo ? `/cadastro?codigo=${encodeURIComponent(codigo)}&` : "/cadastro?";
  const falha = (erro: string): never => redirect(`${rota}erro=${encodeURIComponent(erro)}`);

  if (nome.length < 2) falha("Informe seu nome completo.");
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) falha("E-mail inválido.");
  if (senha.length < 8) falha("A senha precisa ter pelo menos 8 caracteres.");
  const nasc = new Date(`${nascTxt}T12:00:00Z`);
  if (!nascTxt || isNaN(nasc.getTime()) || nasc > new Date()) falha("Informe a data de nascimento.");

  const menor = idade(nasc) < 18;
  const responsavelNome = txt(f, "responsavelNome");
  const responsavelEmail = txt(f, "responsavelEmail").toLowerCase();
  if (menor) {
    if (responsavelNome.length < 2 || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(responsavelEmail))
      falha("Menores de 18 anos precisam informar nome e e-mail do responsável.");
    if (f.get("consentimento") !== "on")
      falha("O responsável precisa autorizar o cadastro do menor.");
  }
  if (await db.usuario.findUnique({ where: { email } })) falha("Já existe uma conta com este e-mail.");

  // A primeira conta criada com o e-mail de ADMIN_EMAIL vira administradora da plataforma.
  const emailAdmin = (process.env.ADMIN_EMAIL ?? "").trim().toLowerCase();
  const viraAdmin =
    !!emailAdmin && email === emailAdmin && !(await db.usuario.count({ where: { papelGlobal: "ADMIN_PLATAFORMA" } }));

  const usuario = await db.usuario.create({
    data: {
      papelGlobal: viraAdmin ? "ADMIN_PLATAFORMA" : "USUARIO",
      nome,
      email,
      senhaHash: await bcrypt.hash(senha, 10),
      dataNascimento: nasc,
      responsavelNome: menor ? responsavelNome : null,
      responsavelEmail: menor ? responsavelEmail : null,
      consentimentoEm: menor ? new Date() : null,
    },
  });
  await criarSessao(usuario.id);

  if (codigo) {
    const r = await usarConvite(usuario.id, codigo);
    if ("erro" in r) voltar("/painel", `Conta criada, mas o código da escola não funcionou: ${r.erro}`);
  }
  redirect("/painel?ok=" + encodeURIComponent("Conta criada. Bons estudos!"));
}

export async function entrar(f: FormData) {
  const email = txt(f, "email").toLowerCase();
  const senha = String(f.get("senha") ?? "");
  if (await loginBloqueado(email))
    voltar("/entrar", "Muitas tentativas. Aguarde 15 minutos ou peça um link de nova senha ao seu professor.");
  const usuario = await db.usuario.findUnique({ where: { email } });
  // Mesma mensagem para e-mail inexistente e senha errada.
  if (!usuario || !(await bcrypt.compare(senha, usuario.senhaHash))) {
    await registrarFalhaLogin(email);
    voltar("/entrar", "E-mail ou senha incorretos.");
  }
  await limparFalhasLogin(email);
  await criarSessao(usuario!.id);
  redirect("/painel");
}

export async function sair() {
  await encerrarSessao();
  redirect("/");
}

export async function usarCodigoEscola(f: FormData) {
  const u = await exigirUsuario();
  const r = await usarConvite(u.id, txt(f, "codigo"));
  if ("erro" in r) voltar("/painel", r.erro);
  else redirect("/painel?ok=" + encodeURIComponent(`Você entrou em ${r.org}.`));
}

/**
 * Gera o link de nova senha. Admin da plataforma: qualquer conta, pelo e-mail.
 * Gestor de escola: só alunos e professores da própria escola.
 */
export async function gerarLinkDeSenha(f: FormData) {
  const u = await exigirUsuario();
  const orgId = txt(f, "orgId");
  if (orgId) {
    const rota = `/escola?org=${orgId}`;
    if (!ehAdmin(u) && !orgsGeridas(u).some((o) => o.id === orgId)) redirect("/painel");
    const m = await db.membro.findUnique({
      where: { usuarioId_orgId: { usuarioId: txt(f, "usuarioId"), orgId } },
      include: { usuario: true },
    });
    if (!m || m.usuario.papelGlobal !== "USUARIO" || (m.papel !== "ALUNO" && m.papel !== "PROFESSOR"))
      redirect(`${rota}&erro=${encodeURIComponent("Você só pode gerar nova senha para alunos e professores desta escola.")}`);
    const link = await criarLinkDeSenha(m!.usuarioId, u.id);
    await db.eventoAuditoria.create({
      data: { atorId: u.id, orgId, acao: "senha.link_gerado", alvoTipo: "Usuario", alvoId: m!.usuarioId },
    });
    redirect(`${rota}&ok=${encodeURIComponent(`Envie este link para ${m!.usuario.nome} (vale por 24 horas, um único uso): ${link}`)}`);
  }

  if (!ehAdmin(u)) redirect("/painel");
  const alvo = await db.usuario.findUnique({ where: { email: txt(f, "email").toLowerCase() } });
  if (!alvo) voltar("/admin", "Nenhuma conta com este e-mail.");
  const link = await criarLinkDeSenha(alvo!.id, u.id);
  await db.eventoAuditoria.create({
    data: { atorId: u.id, acao: "senha.link_gerado", alvoTipo: "Usuario", alvoId: alvo!.id },
  });
  redirect(`/admin?ok=${encodeURIComponent(`Envie este link para ${alvo!.nome} (vale por 24 horas, um único uso): ${link}`)}`);
}

export async function redefinirSenha(f: FormData) {
  const token = txt(f, "token");
  const senha = String(f.get("senha") ?? "");
  const pedido = await buscarRedefinicao(token);
  if (!pedido) voltar("/entrar", "Este link de nova senha não vale mais. Peça outro.");
  if (senha.length < 8) voltar(`/redefinir/${token}`, "A senha precisa ter pelo menos 8 caracteres.");
  // Marca como usado só se ainda não foi: dois envios simultâneos não reutilizam o link.
  const ok = await db.redefinicaoSenha.updateMany({
    where: { tokenHash: pedido!.tokenHash, usadoEm: null },
    data: { usadoEm: new Date() },
  });
  if (!ok.count) voltar("/entrar", "Este link de nova senha não vale mais. Peça outro.");
  const usuario = await db.usuario.update({
    where: { id: pedido!.usuarioId },
    data: { senhaHash: await bcrypt.hash(senha, 10) },
  });
  // Derruba as sessões abertas com a senha antiga.
  await db.sessao.deleteMany({ where: { usuarioId: usuario.id } });
  await limparFalhasLogin(usuario.email);
  redirect("/entrar?ok=" + encodeURIComponent("Senha alterada. Entre com a nova senha."));
}
