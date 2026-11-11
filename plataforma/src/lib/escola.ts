import { db } from "./db";

const vigente = (validade: Date | null) => !validade || validade > new Date();

/**
 * Distribui as vagas das licenças da escola entre os alunos que ainda não têm
 * acesso ao curso. Trava a linha da licença para dois cadastros simultâneos
 * não ocuparem a mesma vaga.
 */
export async function aplicarLicencas(orgId: string) {
  const licencas = await db.licenca.findMany({ where: { orgId } });
  for (const lic of licencas) {
    if (!vigente(lic.validade)) continue;
    await db.$transaction(async (tx) => {
      await tx.$queryRaw`SELECT id FROM "Licenca" WHERE id = ${lic.id} FOR UPDATE`;
      let usadas = await tx.acesso.count({ where: { licencaId: lic.id, status: "ATIVO" } });
      const alunos = await tx.membro.findMany({
        where: { orgId, papel: "ALUNO" },
        orderBy: { criadoEm: "asc" },
        select: { usuarioId: true },
      });
      for (const { usuarioId } of alunos) {
        if (usadas >= lic.vagasTotal) break;
        const chave = { usuarioId_cursoId: { usuarioId, cursoId: lic.cursoId } };
        const atual = await tx.acesso.findUnique({ where: chave });
        const valido = atual && atual.status === "ATIVO" && (!atual.fim || atual.fim > new Date());
        if (valido) continue; // já tem acesso (por compra ou por esta licença)
        const dados = {
          orgId,
          licencaId: lic.id,
          origem: "LICENCA_ESCOLA" as const,
          status: "ATIVO" as const,
          fim: lic.validade,
        };
        await tx.acesso.upsert({
          where: chave,
          create: { usuarioId, cursoId: lic.cursoId, ...dados },
          update: dados,
        });
        usadas++;
      }
    });
  }
}

/** Valida o código e vincula o usuário à escola (e à turma, se o convite tiver uma). */
export async function usarConvite(usuarioId: string, codigoBruto: string): Promise<{ erro: string } | { org: string }> {
  const codigo = codigoBruto.trim().toUpperCase();
  const convite = await db.convite.findUnique({ where: { codigo }, include: { org: true } });
  if (!convite || !convite.ativo || !convite.org.ativa) return { erro: "Código inválido." };
  if (convite.expiraEm && convite.expiraEm < new Date()) return { erro: "Este código expirou." };

  const jaMembro = await db.membro.findUnique({
    where: { usuarioId_orgId: { usuarioId, orgId: convite.orgId } },
  });
  if (!jaMembro) {
    // Incremento condicional: não ultrapassa maxUsos mesmo com usos simultâneos.
    const ok = await db.convite.updateMany({
      where: { id: convite.id, OR: [{ maxUsos: null }, { usos: { lt: convite.maxUsos ?? 0 } }] },
      data: { usos: { increment: 1 } },
    });
    if (!ok.count) return { erro: "Este código já atingiu o limite de usos." };
    await db.membro.create({ data: { usuarioId, orgId: convite.orgId, papel: convite.papel } });
  }
  if (convite.turmaId && convite.papel === "ALUNO") {
    await db.turmaAluno.upsert({
      where: { turmaId_usuarioId: { turmaId: convite.turmaId, usuarioId } },
      create: { turmaId: convite.turmaId, usuarioId },
      update: {},
    });
  }
  await aplicarLicencas(convite.orgId);
  return { org: convite.org.nome };
}

/** Ao sair da escola o aluno perde os acessos da licença, mas mantém progresso e certificados. */
export async function removerDaEscola(usuarioId: string, orgId: string) {
  await db.$transaction([
    db.acesso.updateMany({
      where: { usuarioId, orgId, origem: "LICENCA_ESCOLA" },
      data: { status: "REVOGADO" },
    }),
    db.turmaAluno.deleteMany({ where: { usuarioId, turma: { orgId } } }),
    db.turma.updateMany({ where: { orgId, professorId: usuarioId }, data: { professorId: null } }),
    db.membro.deleteMany({ where: { usuarioId, orgId } }),
  ]);
  await aplicarLicencas(orgId); // a vaga liberada vai para quem estava na fila
}
