import { cache } from "react";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { createHash, randomBytes } from "crypto";
import type { PapelOrg } from "@prisma/client";
import { db } from "./db";

const COOKIE = "sessao";
const DURACAO_MS = 30 * 24 * 60 * 60 * 1000;
const hash = (t: string) => createHash("sha256").update(t).digest("hex");

export async function criarSessao(usuarioId: string) {
  const token = randomBytes(32).toString("hex");
  const expiraEm = new Date(Date.now() + DURACAO_MS);
  await db.sessao.create({ data: { tokenHash: hash(token), usuarioId, expiraEm } });
  (await cookies()).set(COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    expires: expiraEm,
  });
}

export async function encerrarSessao() {
  const jar = await cookies();
  const token = jar.get(COOKIE)?.value;
  if (token) await db.sessao.deleteMany({ where: { tokenHash: hash(token) } });
  jar.delete(COOKIE);
}

export const usuarioAtual = cache(async () => {
  const token = (await cookies()).get(COOKIE)?.value;
  if (!token) return null;
  const sessao = await db.sessao.findUnique({
    where: { tokenHash: hash(token) },
    include: { usuario: { include: { membros: { include: { org: true } } } } },
  });
  if (!sessao || sessao.expiraEm < new Date()) return null;
  return sessao.usuario;
});

export type UsuarioLogado = NonNullable<Awaited<ReturnType<typeof usuarioAtual>>>;

export async function exigirUsuario() {
  const u = await usuarioAtual();
  if (!u) redirect("/entrar");
  return u;
}

export const ehAdmin = (u: UsuarioLogado) => u.papelGlobal === "ADMIN_PLATAFORMA";

export async function exigirAdmin() {
  const u = await exigirUsuario();
  if (!ehAdmin(u)) redirect("/painel");
  return u;
}

const GESTAO: PapelOrg[] = ["COORDENADOR", "ADMIN_ORG"];

/** Escolas que o usuário administra (coordenador ou admin da escola). */
export const orgsGeridas = (u: UsuarioLogado) =>
  u.membros.filter((m) => GESTAO.includes(m.papel) && m.org.ativa).map((m) => m.org);

export const orgsComoProfessor = (u: UsuarioLogado) =>
  u.membros.filter((m) => m.papel === "PROFESSOR" && m.org.ativa).map((m) => m.org);

export const ehEquipe = (u: UsuarioLogado) =>
  ehAdmin(u) || u.membros.some((m) => m.papel !== "ALUNO" && m.org.ativa);

/** Garante que o usuário administra a escola informada. Nunca confie no orgId vindo do formulário. */
export async function exigirGestaoOrg(orgId: string) {
  const u = await exigirUsuario();
  if (!ehAdmin(u) && !orgsGeridas(u).some((o) => o.id === orgId)) redirect("/painel");
  return u;
}
