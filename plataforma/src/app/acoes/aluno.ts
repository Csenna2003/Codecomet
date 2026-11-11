"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { exigirUsuario } from "@/lib/auth";
import { acessoAtivo, carregarCurso, concluirAula, estadoDoCurso } from "@/lib/curso";
import { confirmarPedido } from "@/lib/pedidos";
import { ehHttps, plataformaDe } from "@/lib/simulador";
import type { Pergunta } from "@/lib/fmt";

const txt = (f: FormData, k: string) => String(f.get(k) ?? "").trim();
const NOTA_MINIMA_QUIZ = 0.7;

export async function matricular(f: FormData) {
  const u = await exigirUsuario();
  const curso = await db.curso.findUnique({ where: { id: txt(f, "cursoId") } });
  if (!curso || curso.status !== "PUBLICADO") redirect("/");
  if (await acessoAtivo(u.id, curso.id)) redirect(`/cursos/${curso.slug}`);

  const pedido = await db.pedido.create({
    data: { usuarioId: u.id, cursoId: curso.id, valorCentavos: curso.precoCentavos },
  });
  if (curso.precoCentavos === 0) {
    await confirmarPedido(pedido.id);
    redirect(`/cursos/${curso.slug}`);
  }
  redirect(`/pedido/${pedido.id}`);
}

export async function simularPagamento(f: FormData) {
  const u = await exigirUsuario();
  if (process.env.PAGAMENTO_MODO !== "simulado") redirect("/painel");
  const pedido = await db.pedido.findUnique({ where: { id: txt(f, "pedidoId") }, include: { curso: true } });
  if (!pedido || pedido.usuarioId !== u.id) redirect("/painel");
  await confirmarPedido(pedido.id, "simulado");
  redirect(`/cursos/${pedido.curso.slug}`);
}

/** Carrega a aula e confirma no servidor que o aluno tem matrícula e que ela já foi liberada. */
async function aulaDoAluno(aulaId: string) {
  const u = await exigirUsuario();
  const aula = await db.aula.findUnique({
    where: { id: aulaId },
    include: { modulo: true, exercicio: true },
  });
  if (!aula) redirect("/painel");
  const curso = await carregarCurso({ id: aula.modulo.cursoId });
  const acesso = curso && (await acessoAtivo(u.id, curso.id));
  if (!curso || !acesso) redirect("/painel");
  const estado = await estadoDoCurso(u.id, curso);
  if (!estado.porAula.get(aula.id)?.liberada) redirect(`/cursos/${curso.slug}`);
  return { u, aula, curso, acesso };
}

export async function marcarConcluida(f: FormData) {
  const { u, aula, curso } = await aulaDoAluno(txt(f, "aulaId"));
  // Quiz e exercício só concluem pelo fluxo próprio (acerto mínimo / envio).
  if (aula.tipo === "QUIZ" || aula.tipo === "EXERCICIO") redirect(`/aprender/${aula.id}`);
  await concluirAula(u.id, aula.id, curso.id);
  revalidatePath(`/cursos/${curso.slug}`);
  redirect(`/aprender/${aula.id}`);
}

export async function responderQuiz(f: FormData) {
  const { u, aula, curso } = await aulaDoAluno(txt(f, "aulaId"));
  const perguntas = (aula.quiz ?? []) as Pergunta[];
  if (aula.tipo !== "QUIZ" || !perguntas.length) redirect(`/aprender/${aula.id}`);
  const acertos = perguntas.filter((p, i) => Number(f.get(`q${i}`) ?? -1) === p.correta).length;
  const aprovado = acertos / perguntas.length >= NOTA_MINIMA_QUIZ;
  if (aprovado) await concluirAula(u.id, aula.id, curso.id);
  const msg = `Você acertou ${acertos} de ${perguntas.length}.`;
  redirect(
    `/aprender/${aula.id}?` +
      (aprovado
        ? `ok=${encodeURIComponent(msg + " Quiz concluído!")}`
        : `erro=${encodeURIComponent(msg + " É preciso acertar 70% — revise e tente de novo.")}`),
  );
}

export async function enviarEntrega(f: FormData) {
  const { u, aula, curso, acesso } = await aulaDoAluno(txt(f, "aulaId"));
  const ex = aula.exercicio;
  const rota = `/aprender/${aula.id}`;
  const falha = (erro: string): never => redirect(`${rota}?erro=${encodeURIComponent(erro)}`);
  if (!ex) redirect(rota);

  const link = txt(f, "link");
  const codigo = String(f.get("codigo") ?? "").trim();
  const observacao = txt(f, "observacao");

  if (ex!.tipoEntrega === "LINK_SIMULADOR" && !plataformaDe(link))
    falha("Cole o link do seu projeto no Tinkercad ou no Wokwi (https://...).");
  if (ex!.tipoEntrega === "LINK_VIDEO" && !ehHttps(link)) falha("Cole o link do vídeo (https://...).");
  if (ex!.tipoEntrega === "CODIGO" && codigo.length < 10) falha("Cole o código do seu programa.");
  if (link && !ehHttps(link)) falha("O link precisa começar com https://");
  if (codigo.length > 100_000 || observacao.length > 2000) falha("Texto muito longo.");

  const anteriores = await db.entrega.findMany({
    where: { exercicioId: ex!.id, usuarioId: u.id },
    orderBy: { tentativa: "desc" },
  });
  if (anteriores.some((e) => e.status === "APROVADA")) falha("Este exercício já foi aprovado.");
  if (anteriores.some((e) => e.status === "ENVIADA")) falha("Sua entrega anterior ainda está aguardando correção.");
  if (ex!.maxTentativas && anteriores.length >= ex!.maxTentativas)
    falha("Você atingiu o limite de tentativas. Fale com seu professor.");

  await db.entrega.create({
    data: {
      exercicioId: ex!.id,
      usuarioId: u.id,
      orgId: acesso.orgId,
      tentativa: (anteriores[0]?.tentativa ?? 0) + 1,
      link: link || null,
      codigo: codigo || null,
      observacao: observacao || null,
    },
  });
  // Enviar já libera a próxima aula; o certificado continua exigindo aprovação.
  await concluirAula(u.id, aula.id, curso.id);
  revalidatePath(`/cursos/${curso.slug}`);
  redirect(`${rota}?ok=${encodeURIComponent("Entrega enviada! Você será avisado aqui quando for corrigida.")}`);
}
