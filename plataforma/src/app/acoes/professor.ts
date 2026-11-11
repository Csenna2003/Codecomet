"use server";

import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import { exigirUsuario } from "@/lib/auth";
import { filtroEntregasRevisaveis } from "@/lib/revisao";
import { verificarCertificado } from "@/lib/curso";

export async function revisarEntrega(f: FormData) {
  const u = await exigirUsuario();
  const id = String(f.get("entregaId") ?? "");
  const filtro = filtroEntregasRevisaveis(u);
  if (!filtro) redirect("/painel");
  // O filtro de escopo vai na própria consulta: professor de outra escola recebe "não encontrado".
  const entrega = await db.entrega.findFirst({
    where: { AND: [{ id }, filtro] },
    include: { exercicio: { include: { aula: { include: { modulo: true } } } } },
  });
  if (!entrega) redirect("/professor");
  const rota = `/professor/entregas/${entrega.id}`;
  const falha = (erro: string): never => redirect(`${rota}?erro=${encodeURIComponent(erro)}`);
  if (entrega.status !== "ENVIADA") falha("Esta entrega já foi corrigida.");

  const decisao = f.get("decisao") === "APROVADA" ? "APROVADA" : "PRECISA_AJUSTE";
  const nota = Number(f.get("nota"));
  const comentario = String(f.get("comentario") ?? "").trim();
  const max = entrega.exercicio.notaMaxima;
  if (!Number.isInteger(nota) || nota < 0 || nota > max) falha(`A nota deve ser um número inteiro de 0 a ${max}.`);
  if (decisao === "PRECISA_AJUSTE" && comentario.length < 5)
    falha("Explique ao aluno o que precisa ser ajustado.");
  const criterios = entrega.exercicio.rubrica.filter((_, i) => f.get(`c${i}`) === "on");

  // updateMany condicional evita dupla correção simultânea.
  const ok = await db.entrega.updateMany({
    where: { id: entrega.id, status: "ENVIADA" },
    data: { status: decisao, nota, revisadoEm: new Date() },
  });
  if (!ok.count) falha("Esta entrega já foi corrigida.");
  await db.revisao.create({
    data: { entregaId: entrega.id, revisorId: u.id, decisao, nota, comentario, criteriosAtendidos: criterios },
  });
  await db.eventoAuditoria.create({
    data: {
      atorId: u.id,
      orgId: entrega.orgId,
      acao: `entrega.${decisao.toLowerCase()}`,
      alvoTipo: "Entrega",
      alvoId: entrega.id,
      dados: { nota },
    },
  });
  if (decisao === "APROVADA")
    await verificarCertificado(entrega.usuarioId, entrega.exercicio.aula.modulo.cursoId);
  redirect("/professor?ok=" + encodeURIComponent("Correção registrada."));
}
