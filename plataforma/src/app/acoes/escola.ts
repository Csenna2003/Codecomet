"use server";

import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import { exigirGestaoOrg } from "@/lib/auth";
import { codigoAleatorio } from "@/lib/fmt";
import { removerDaEscola } from "@/lib/escola";

const txt = (f: FormData, k: string) => String(f.get(k) ?? "").trim();
const rota = (orgId: string, tipo: "ok" | "erro", msg: string): never =>
  redirect(`/escola?org=${orgId}&${tipo}=${encodeURIComponent(msg)}`);

export async function criarTurma(f: FormData) {
  const orgId = txt(f, "orgId");
  await exigirGestaoOrg(orgId);
  const nome = txt(f, "nome");
  if (!nome) rota(orgId, "erro", "Informe o nome da turma.");
  const professorId = txt(f, "professorId") || null;
  if (professorId) {
    const m = await db.membro.findUnique({ where: { usuarioId_orgId: { usuarioId: professorId, orgId } } });
    if (!m || m.papel === "ALUNO") rota(orgId, "erro", "Professor inválido.");
  }
  await db.turma.create({ data: { orgId, nome, periodo: txt(f, "periodo") || null, professorId } });
  rota(orgId, "ok", "Turma criada.");
}

export async function criarConvite(f: FormData) {
  const orgId = txt(f, "orgId");
  await exigirGestaoOrg(orgId);
  const papel = f.get("papel") === "PROFESSOR" ? "PROFESSOR" : "ALUNO";
  const turmaId = txt(f, "turmaId") || null;
  if (turmaId && !(await db.turma.findFirst({ where: { id: turmaId, orgId } })))
    rota(orgId, "erro", "Turma inválida.");
  const maxUsos = Number(txt(f, "maxUsos")) || null;
  const convite = await db.convite.create({
    data: { orgId, papel, turmaId: papel === "ALUNO" ? turmaId : null, maxUsos, codigo: codigoAleatorio(8) },
  });
  rota(orgId, "ok", `Código criado: ${convite.codigo}`);
}

export async function desativarConvite(f: FormData) {
  const orgId = txt(f, "orgId");
  await exigirGestaoOrg(orgId);
  await db.convite.updateMany({ where: { id: txt(f, "conviteId"), orgId }, data: { ativo: false } });
  rota(orgId, "ok", "Código desativado.");
}

export async function removerMembro(f: FormData) {
  const orgId = txt(f, "orgId");
  const gestor = await exigirGestaoOrg(orgId);
  const usuarioId = txt(f, "usuarioId");
  if (usuarioId === gestor.id) rota(orgId, "erro", "Você não pode remover a si mesmo.");
  const m = await db.membro.findUnique({ where: { usuarioId_orgId: { usuarioId, orgId } } });
  if (!m) rota(orgId, "erro", "Pessoa não encontrada nesta escola.");
  await removerDaEscola(usuarioId, orgId);
  await db.eventoAuditoria.create({
    data: { atorId: gestor.id, orgId, acao: "membro.removido", alvoTipo: "Usuario", alvoId: usuarioId },
  });
  rota(orgId, "ok", "Pessoa removida da escola. O histórico e os certificados dela foram mantidos.");
}
