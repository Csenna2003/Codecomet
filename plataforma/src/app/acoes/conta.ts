"use server";

import bcrypt from "bcryptjs";
import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import { criarSessao, encerrarSessao, exigirUsuario } from "@/lib/auth";
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

  const usuario = await db.usuario.create({
    data: {
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
  const usuario = await db.usuario.findUnique({ where: { email } });
  // Mesma mensagem para e-mail inexistente e senha errada.
  if (!usuario || !(await bcrypt.compare(senha, usuario.senhaHash))) voltar("/entrar", "E-mail ou senha incorretos.");
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
