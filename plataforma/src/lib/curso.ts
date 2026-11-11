import type { Prisma } from "@prisma/client";
import { db } from "./db";
import { ehAdmin, type UsuarioLogado } from "./auth";
import { codigoAleatorio } from "./fmt";

const incluirConteudo = {
  modulos: {
    orderBy: { ordem: "asc" },
    include: { aulas: { orderBy: { ordem: "asc" }, include: { exercicio: true } } },
  },
} satisfies Prisma.CursoInclude;

export type CursoCompleto = Prisma.CursoGetPayload<{ include: typeof incluirConteudo }>;

export const carregarCurso = (where: Prisma.CursoWhereUniqueInput) =>
  db.curso.findUnique({ where, include: incluirConteudo });

export const achatar = (curso: CursoCompleto) => curso.modulos.flatMap((m) => m.aulas);

export async function acessoAtivo(usuarioId: string, cursoId: string) {
  const a = await db.acesso.findUnique({ where: { usuarioId_cursoId: { usuarioId, cursoId } } });
  if (!a || a.status !== "ATIVO") return null;
  if (a.fim && a.fim < new Date()) return null;
  return a;
}

/**
 * "aluno": tem matrícula e progride no curso.
 * "preview": equipe (admin da plataforma ou professor/coordenador de escola com
 * licença do curso) vê todas as aulas sem ocupar vaga nem registrar progresso.
 */
export async function modoDeAcesso(u: UsuarioLogado, cursoId: string) {
  const acesso = await acessoAtivo(u.id, cursoId);
  if (acesso) return { modo: "aluno" as const, acesso };
  if (ehAdmin(u)) return { modo: "preview" as const, acesso: null };
  const orgsEquipe = u.membros.filter((m) => m.papel !== "ALUNO").map((m) => m.orgId);
  if (orgsEquipe.length) {
    const lic = await db.licenca.findFirst({ where: { cursoId, orgId: { in: orgsEquipe } } });
    if (lic) return { modo: "preview" as const, acesso: null };
  }
  return null;
}

/** Aulas liberadas em sequência: uma aula abre quando todas as obrigatórias anteriores foram concluídas. */
export async function estadoDoCurso(usuarioId: string, curso: CursoCompleto) {
  const aulas = achatar(curso);
  const prog = await db.progressoAula.findMany({
    where: { usuarioId, aulaId: { in: aulas.map((a) => a.id) } },
    select: { aulaId: true },
  });
  const feitas = new Set(prog.map((p) => p.aulaId));
  const porAula = new Map<string, { concluida: boolean; liberada: boolean }>();
  let bloqueado = false;
  let proxima: string | null = null;
  for (const a of aulas) {
    const concluida = feitas.has(a.id);
    porAula.set(a.id, { concluida, liberada: !bloqueado });
    if (!concluida && !bloqueado && !proxima) proxima = a.id;
    if (a.obrigatoria && !concluida) bloqueado = true;
  }
  const obrig = aulas.filter((a) => a.obrigatoria);
  const feitasObrig = obrig.filter((a) => feitas.has(a.id)).length;
  const percentual = obrig.length ? Math.round((feitasObrig / obrig.length) * 100) : 0;
  return { porAula, percentual, proxima };
}

export async function concluirAula(usuarioId: string, aulaId: string, cursoId: string) {
  await db.progressoAula.upsert({
    where: { usuarioId_aulaId: { usuarioId, aulaId } },
    create: { usuarioId, aulaId },
    update: {},
  });
  await verificarCertificado(usuarioId, cursoId);
}

/**
 * Emite o certificado quando todas as aulas obrigatórias estão concluídas e
 * todo exercício obrigatório tem uma entrega aprovada.
 */
export async function verificarCertificado(usuarioId: string, cursoId: string) {
  const curso = await carregarCurso({ id: cursoId });
  if (!curso) return null;
  const existente = await db.certificado.findUnique({ where: { usuarioId_cursoId: { usuarioId, cursoId } } });
  if (existente) return existente;

  const obrig = achatar(curso).filter((a) => a.obrigatoria);
  if (!obrig.length) return null;
  const concluidas = await db.progressoAula.count({
    where: { usuarioId, aulaId: { in: obrig.map((a) => a.id) } },
  });
  if (concluidas < obrig.length) return null;

  const exercicios = obrig.flatMap((a) => (a.exercicio ? [a.exercicio.id] : []));
  if (exercicios.length) {
    const aprovados = await db.entrega.findMany({
      where: { usuarioId, exercicioId: { in: exercicios }, status: "APROVADA" },
      distinct: ["exercicioId"],
      select: { exercicioId: true },
    });
    if (aprovados.length < exercicios.length) return null;
  }

  const acesso = await db.acesso.findUnique({ where: { usuarioId_cursoId: { usuarioId, cursoId } } });
  const cert = await db.certificado.create({
    data: {
      codigo: `CC-${codigoAleatorio(4)}-${codigoAleatorio(4)}`,
      usuarioId,
      cursoId,
      orgId: acesso?.orgId ?? null,
      cursoVersao: curso.versao,
      cargaHoraria: curso.cargaHoraria,
    },
  });
  await db.eventoAuditoria.create({
    data: { atorId: usuarioId, orgId: cert.orgId, acao: "certificado.emitido", alvoTipo: "Certificado", alvoId: cert.id },
  });
  return cert;
}
