import type { Prisma } from "@prisma/client";
import { ehAdmin, orgsComoProfessor, orgsGeridas, type UsuarioLogado } from "./auth";

/**
 * Quais entregas o usuário pode corrigir:
 * - admin da plataforma: todas (inclui alunos de venda direta, sem escola);
 * - coordenador/admin da escola: todas da escola;
 * - professor: só de alunos das turmas dele.
 * Retorna null para quem não corrige nada.
 */
export function filtroEntregasRevisaveis(u: UsuarioLogado): Prisma.EntregaWhereInput | null {
  if (ehAdmin(u)) return {};
  const geridas = orgsGeridas(u).map((o) => o.id);
  const comoProf = orgsComoProfessor(u).map((o) => o.id);
  const ou: Prisma.EntregaWhereInput[] = [];
  if (geridas.length) ou.push({ orgId: { in: geridas } });
  for (const orgId of comoProf)
    ou.push({ orgId, usuario: { turmas: { some: { turma: { orgId, professorId: u.id } } } } });
  return ou.length ? { OR: ou } : null;
}
