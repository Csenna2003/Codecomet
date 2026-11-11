import { createHash, randomBytes } from "crypto";
import { headers } from "next/headers";
import { db } from "./db";

const JANELA_MS = 15 * 60 * 1000;
const MAX_POR_EMAIL = 5;
const MAX_POR_IP = 30; // uma escola inteira pode sair pelo mesmo IP
const hash = (t: string) => createHash("sha256").update(t).digest("hex");

async function chavesDoLogin(email: string) {
  const h = await headers();
  // x-real-ip é definido pelo proxy da hospedagem; x-forwarded-for pode ser forjado pelo cliente.
  const ip = h.get("x-real-ip")?.trim() || (h.get("x-forwarded-for") ?? "").split(",")[0].trim() || "desconhecido";
  return { email: `email:${email}`, ip: `ip:${ip}` };
}

/** true quando houve falhas demais nos últimos 15 minutos para este e-mail ou IP. */
export async function loginBloqueado(email: string) {
  const c = await chavesDoLogin(email);
  const desde = new Date(Date.now() - JANELA_MS);
  const [porEmail, porIp] = await Promise.all([
    db.tentativaLogin.count({ where: { chave: c.email, criadoEm: { gt: desde } } }),
    db.tentativaLogin.count({ where: { chave: c.ip, criadoEm: { gt: desde } } }),
  ]);
  return porEmail >= MAX_POR_EMAIL || porIp >= MAX_POR_IP;
}

export async function registrarFalhaLogin(email: string) {
  const c = await chavesDoLogin(email);
  await db.tentativaLogin.createMany({ data: [{ chave: c.email }, { chave: c.ip }] });
  // Limpeza oportunista do que já saiu da janela.
  await db.tentativaLogin.deleteMany({ where: { criadoEm: { lt: new Date(Date.now() - JANELA_MS) } } });
}

export const limparFalhasLogin = (email: string) =>
  db.tentativaLogin.deleteMany({ where: { chave: `email:${email}` } });

/** Cria um link de uso único, válido por 24 horas. Só o hash do token fica no banco. */
export async function criarLinkDeSenha(usuarioId: string, criadoPor: string) {
  const token = randomBytes(32).toString("hex");
  await db.redefinicaoSenha.deleteMany({ where: { usuarioId, usadoEm: null } });
  await db.redefinicaoSenha.create({
    data: { tokenHash: hash(token), usuarioId, criadoPor, expiraEm: new Date(Date.now() + 24 * 60 * 60 * 1000) },
  });
  const h = await headers();
  const host = h.get("x-forwarded-host") ?? h.get("host") ?? "localhost";
  const proto = h.get("x-forwarded-proto") ?? (host.startsWith("localhost") ? "http" : "https");
  return `${proto}://${host}/redefinir/${token}`;
}

export async function buscarRedefinicao(token: string) {
  const r = await db.redefinicaoSenha.findUnique({
    where: { tokenHash: hash(token) },
    include: { usuario: { select: { id: true, nome: true } } },
  });
  return r && !r.usadoEm && r.expiraEm > new Date() ? r : null;
}
