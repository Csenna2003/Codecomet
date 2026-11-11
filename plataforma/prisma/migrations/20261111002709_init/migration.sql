-- CreateEnum
CREATE TYPE "PapelGlobal" AS ENUM ('USUARIO', 'ADMIN_PLATAFORMA');

-- CreateEnum
CREATE TYPE "TipoOrg" AS ENUM ('ESCOLA', 'EMPRESA');

-- CreateEnum
CREATE TYPE "PapelOrg" AS ENUM ('ALUNO', 'PROFESSOR', 'COORDENADOR', 'ADMIN_ORG');

-- CreateEnum
CREATE TYPE "Nivel" AS ENUM ('INICIANTE', 'INTERMEDIARIO', 'AVANCADO');

-- CreateEnum
CREATE TYPE "StatusCurso" AS ENUM ('RASCUNHO', 'PUBLICADO', 'ARQUIVADO');

-- CreateEnum
CREATE TYPE "TipoAula" AS ENUM ('VIDEO', 'TEXTO', 'QUIZ', 'SIMULADOR', 'EXERCICIO');

-- CreateEnum
CREATE TYPE "StatusPedido" AS ENUM ('PENDENTE', 'PAGO', 'CANCELADO');

-- CreateEnum
CREATE TYPE "OrigemAcesso" AS ENUM ('COMPRA', 'LICENCA_ESCOLA', 'BOLSA', 'MANUAL');

-- CreateEnum
CREATE TYPE "StatusAcesso" AS ENUM ('ATIVO', 'REVOGADO');

-- CreateEnum
CREATE TYPE "TipoEntrega" AS ENUM ('LINK_SIMULADOR', 'CODIGO', 'LINK_VIDEO');

-- CreateEnum
CREATE TYPE "StatusEntrega" AS ENUM ('ENVIADA', 'APROVADA', 'PRECISA_AJUSTE');

-- CreateTable
CREATE TABLE "Usuario" (
    "id" TEXT NOT NULL,
    "nome" TEXT NOT NULL,
    "email" TEXT NOT NULL,
    "senhaHash" TEXT NOT NULL,
    "papelGlobal" "PapelGlobal" NOT NULL DEFAULT 'USUARIO',
    "dataNascimento" TIMESTAMP(3),
    "responsavelNome" TEXT,
    "responsavelEmail" TEXT,
    "consentimentoEm" TIMESTAMP(3),
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Usuario_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Sessao" (
    "tokenHash" TEXT NOT NULL,
    "usuarioId" TEXT NOT NULL,
    "expiraEm" TIMESTAMP(3) NOT NULL,
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Sessao_pkey" PRIMARY KEY ("tokenHash")
);

-- CreateTable
CREATE TABLE "Organizacao" (
    "id" TEXT NOT NULL,
    "nome" TEXT NOT NULL,
    "tipo" "TipoOrg" NOT NULL DEFAULT 'ESCOLA',
    "documento" TEXT,
    "ativa" BOOLEAN NOT NULL DEFAULT true,
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Organizacao_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Membro" (
    "id" TEXT NOT NULL,
    "usuarioId" TEXT NOT NULL,
    "orgId" TEXT NOT NULL,
    "papel" "PapelOrg" NOT NULL,
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Membro_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Turma" (
    "id" TEXT NOT NULL,
    "orgId" TEXT NOT NULL,
    "nome" TEXT NOT NULL,
    "periodo" TEXT,
    "professorId" TEXT,
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Turma_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "TurmaAluno" (
    "turmaId" TEXT NOT NULL,
    "usuarioId" TEXT NOT NULL,
    "entrouEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "TurmaAluno_pkey" PRIMARY KEY ("turmaId","usuarioId")
);

-- CreateTable
CREATE TABLE "Convite" (
    "id" TEXT NOT NULL,
    "codigo" TEXT NOT NULL,
    "orgId" TEXT NOT NULL,
    "turmaId" TEXT,
    "papel" "PapelOrg" NOT NULL DEFAULT 'ALUNO',
    "maxUsos" INTEGER,
    "usos" INTEGER NOT NULL DEFAULT 0,
    "expiraEm" TIMESTAMP(3),
    "ativo" BOOLEAN NOT NULL DEFAULT true,
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Convite_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Curso" (
    "id" TEXT NOT NULL,
    "slug" TEXT NOT NULL,
    "titulo" TEXT NOT NULL,
    "descricao" TEXT NOT NULL,
    "nivel" "Nivel" NOT NULL DEFAULT 'INICIANTE',
    "cargaHoraria" INTEGER NOT NULL DEFAULT 0,
    "precoCentavos" INTEGER NOT NULL DEFAULT 0,
    "status" "StatusCurso" NOT NULL DEFAULT 'RASCUNHO',
    "versao" INTEGER NOT NULL DEFAULT 1,
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Curso_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Modulo" (
    "id" TEXT NOT NULL,
    "cursoId" TEXT NOT NULL,
    "ordem" INTEGER NOT NULL,
    "titulo" TEXT NOT NULL,

    CONSTRAINT "Modulo_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Aula" (
    "id" TEXT NOT NULL,
    "moduloId" TEXT NOT NULL,
    "ordem" INTEGER NOT NULL,
    "titulo" TEXT NOT NULL,
    "tipo" "TipoAula" NOT NULL,
    "conteudo" TEXT NOT NULL DEFAULT '',
    "videoUrl" TEXT,
    "simuladorUrl" TEXT,
    "quiz" JSONB,
    "obrigatoria" BOOLEAN NOT NULL DEFAULT true,
    "duracaoMin" INTEGER NOT NULL DEFAULT 0,

    CONSTRAINT "Aula_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Exercicio" (
    "id" TEXT NOT NULL,
    "aulaId" TEXT NOT NULL,
    "enunciado" TEXT NOT NULL,
    "rubrica" TEXT[],
    "tipoEntrega" "TipoEntrega" NOT NULL DEFAULT 'LINK_SIMULADOR',
    "notaMaxima" INTEGER NOT NULL DEFAULT 10,
    "maxTentativas" INTEGER,

    CONSTRAINT "Exercicio_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Trilha" (
    "id" TEXT NOT NULL,
    "titulo" TEXT NOT NULL,
    "descricao" TEXT NOT NULL,

    CONSTRAINT "Trilha_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "TrilhaCurso" (
    "trilhaId" TEXT NOT NULL,
    "cursoId" TEXT NOT NULL,
    "ordem" INTEGER NOT NULL,

    CONSTRAINT "TrilhaCurso_pkey" PRIMARY KEY ("trilhaId","cursoId")
);

-- CreateTable
CREATE TABLE "Pedido" (
    "id" TEXT NOT NULL,
    "usuarioId" TEXT NOT NULL,
    "cursoId" TEXT NOT NULL,
    "valorCentavos" INTEGER NOT NULL,
    "status" "StatusPedido" NOT NULL DEFAULT 'PENDENTE',
    "gatewayRef" TEXT,
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "pagoEm" TIMESTAMP(3),

    CONSTRAINT "Pedido_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Acesso" (
    "id" TEXT NOT NULL,
    "usuarioId" TEXT NOT NULL,
    "cursoId" TEXT NOT NULL,
    "orgId" TEXT,
    "licencaId" TEXT,
    "pedidoId" TEXT,
    "origem" "OrigemAcesso" NOT NULL,
    "status" "StatusAcesso" NOT NULL DEFAULT 'ATIVO',
    "inicio" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "fim" TIMESTAMP(3),

    CONSTRAINT "Acesso_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Licenca" (
    "id" TEXT NOT NULL,
    "orgId" TEXT NOT NULL,
    "cursoId" TEXT NOT NULL,
    "vagasTotal" INTEGER NOT NULL,
    "validade" TIMESTAMP(3),
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Licenca_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ProgressoAula" (
    "usuarioId" TEXT NOT NULL,
    "aulaId" TEXT NOT NULL,
    "concluidoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ProgressoAula_pkey" PRIMARY KEY ("usuarioId","aulaId")
);

-- CreateTable
CREATE TABLE "Entrega" (
    "id" TEXT NOT NULL,
    "exercicioId" TEXT NOT NULL,
    "usuarioId" TEXT NOT NULL,
    "orgId" TEXT,
    "tentativa" INTEGER NOT NULL,
    "link" TEXT,
    "codigo" TEXT,
    "observacao" TEXT,
    "status" "StatusEntrega" NOT NULL DEFAULT 'ENVIADA',
    "nota" INTEGER,
    "enviadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "revisadoEm" TIMESTAMP(3),

    CONSTRAINT "Entrega_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Revisao" (
    "id" TEXT NOT NULL,
    "entregaId" TEXT NOT NULL,
    "revisorId" TEXT NOT NULL,
    "decisao" "StatusEntrega" NOT NULL,
    "nota" INTEGER NOT NULL,
    "comentario" TEXT NOT NULL,
    "criteriosAtendidos" TEXT[],
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Revisao_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Certificado" (
    "id" TEXT NOT NULL,
    "codigo" TEXT NOT NULL,
    "usuarioId" TEXT NOT NULL,
    "cursoId" TEXT NOT NULL,
    "orgId" TEXT,
    "cursoVersao" INTEGER NOT NULL,
    "cargaHoraria" INTEGER NOT NULL,
    "emitidoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Certificado_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "EventoAuditoria" (
    "id" TEXT NOT NULL,
    "atorId" TEXT,
    "orgId" TEXT,
    "acao" TEXT NOT NULL,
    "alvoTipo" TEXT NOT NULL,
    "alvoId" TEXT NOT NULL,
    "dados" JSONB,
    "criadoEm" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "EventoAuditoria_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "Usuario_email_key" ON "Usuario"("email");

-- CreateIndex
CREATE INDEX "Sessao_usuarioId_idx" ON "Sessao"("usuarioId");

-- CreateIndex
CREATE INDEX "Membro_orgId_idx" ON "Membro"("orgId");

-- CreateIndex
CREATE UNIQUE INDEX "Membro_usuarioId_orgId_key" ON "Membro"("usuarioId", "orgId");

-- CreateIndex
CREATE INDEX "Turma_orgId_idx" ON "Turma"("orgId");

-- CreateIndex
CREATE UNIQUE INDEX "Convite_codigo_key" ON "Convite"("codigo");

-- CreateIndex
CREATE INDEX "Convite_orgId_idx" ON "Convite"("orgId");

-- CreateIndex
CREATE UNIQUE INDEX "Curso_slug_key" ON "Curso"("slug");

-- CreateIndex
CREATE INDEX "Modulo_cursoId_idx" ON "Modulo"("cursoId");

-- CreateIndex
CREATE INDEX "Aula_moduloId_idx" ON "Aula"("moduloId");

-- CreateIndex
CREATE UNIQUE INDEX "Exercicio_aulaId_key" ON "Exercicio"("aulaId");

-- CreateIndex
CREATE INDEX "Pedido_usuarioId_idx" ON "Pedido"("usuarioId");

-- CreateIndex
CREATE INDEX "Acesso_licencaId_idx" ON "Acesso"("licencaId");

-- CreateIndex
CREATE UNIQUE INDEX "Acesso_usuarioId_cursoId_key" ON "Acesso"("usuarioId", "cursoId");

-- CreateIndex
CREATE UNIQUE INDEX "Licenca_orgId_cursoId_key" ON "Licenca"("orgId", "cursoId");

-- CreateIndex
CREATE INDEX "Entrega_orgId_status_idx" ON "Entrega"("orgId", "status");

-- CreateIndex
CREATE UNIQUE INDEX "Entrega_exercicioId_usuarioId_tentativa_key" ON "Entrega"("exercicioId", "usuarioId", "tentativa");

-- CreateIndex
CREATE INDEX "Revisao_entregaId_idx" ON "Revisao"("entregaId");

-- CreateIndex
CREATE UNIQUE INDEX "Certificado_codigo_key" ON "Certificado"("codigo");

-- CreateIndex
CREATE UNIQUE INDEX "Certificado_usuarioId_cursoId_key" ON "Certificado"("usuarioId", "cursoId");

-- CreateIndex
CREATE INDEX "EventoAuditoria_orgId_criadoEm_idx" ON "EventoAuditoria"("orgId", "criadoEm");

-- AddForeignKey
ALTER TABLE "Sessao" ADD CONSTRAINT "Sessao_usuarioId_fkey" FOREIGN KEY ("usuarioId") REFERENCES "Usuario"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Membro" ADD CONSTRAINT "Membro_usuarioId_fkey" FOREIGN KEY ("usuarioId") REFERENCES "Usuario"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Membro" ADD CONSTRAINT "Membro_orgId_fkey" FOREIGN KEY ("orgId") REFERENCES "Organizacao"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Turma" ADD CONSTRAINT "Turma_orgId_fkey" FOREIGN KEY ("orgId") REFERENCES "Organizacao"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Turma" ADD CONSTRAINT "Turma_professorId_fkey" FOREIGN KEY ("professorId") REFERENCES "Usuario"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "TurmaAluno" ADD CONSTRAINT "TurmaAluno_turmaId_fkey" FOREIGN KEY ("turmaId") REFERENCES "Turma"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "TurmaAluno" ADD CONSTRAINT "TurmaAluno_usuarioId_fkey" FOREIGN KEY ("usuarioId") REFERENCES "Usuario"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Convite" ADD CONSTRAINT "Convite_orgId_fkey" FOREIGN KEY ("orgId") REFERENCES "Organizacao"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Convite" ADD CONSTRAINT "Convite_turmaId_fkey" FOREIGN KEY ("turmaId") REFERENCES "Turma"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Modulo" ADD CONSTRAINT "Modulo_cursoId_fkey" FOREIGN KEY ("cursoId") REFERENCES "Curso"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Aula" ADD CONSTRAINT "Aula_moduloId_fkey" FOREIGN KEY ("moduloId") REFERENCES "Modulo"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Exercicio" ADD CONSTRAINT "Exercicio_aulaId_fkey" FOREIGN KEY ("aulaId") REFERENCES "Aula"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "TrilhaCurso" ADD CONSTRAINT "TrilhaCurso_trilhaId_fkey" FOREIGN KEY ("trilhaId") REFERENCES "Trilha"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "TrilhaCurso" ADD CONSTRAINT "TrilhaCurso_cursoId_fkey" FOREIGN KEY ("cursoId") REFERENCES "Curso"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Pedido" ADD CONSTRAINT "Pedido_usuarioId_fkey" FOREIGN KEY ("usuarioId") REFERENCES "Usuario"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Pedido" ADD CONSTRAINT "Pedido_cursoId_fkey" FOREIGN KEY ("cursoId") REFERENCES "Curso"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Acesso" ADD CONSTRAINT "Acesso_usuarioId_fkey" FOREIGN KEY ("usuarioId") REFERENCES "Usuario"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Acesso" ADD CONSTRAINT "Acesso_cursoId_fkey" FOREIGN KEY ("cursoId") REFERENCES "Curso"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Acesso" ADD CONSTRAINT "Acesso_orgId_fkey" FOREIGN KEY ("orgId") REFERENCES "Organizacao"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Acesso" ADD CONSTRAINT "Acesso_licencaId_fkey" FOREIGN KEY ("licencaId") REFERENCES "Licenca"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Licenca" ADD CONSTRAINT "Licenca_orgId_fkey" FOREIGN KEY ("orgId") REFERENCES "Organizacao"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Licenca" ADD CONSTRAINT "Licenca_cursoId_fkey" FOREIGN KEY ("cursoId") REFERENCES "Curso"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ProgressoAula" ADD CONSTRAINT "ProgressoAula_usuarioId_fkey" FOREIGN KEY ("usuarioId") REFERENCES "Usuario"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ProgressoAula" ADD CONSTRAINT "ProgressoAula_aulaId_fkey" FOREIGN KEY ("aulaId") REFERENCES "Aula"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Entrega" ADD CONSTRAINT "Entrega_exercicioId_fkey" FOREIGN KEY ("exercicioId") REFERENCES "Exercicio"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Entrega" ADD CONSTRAINT "Entrega_usuarioId_fkey" FOREIGN KEY ("usuarioId") REFERENCES "Usuario"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Entrega" ADD CONSTRAINT "Entrega_orgId_fkey" FOREIGN KEY ("orgId") REFERENCES "Organizacao"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Revisao" ADD CONSTRAINT "Revisao_entregaId_fkey" FOREIGN KEY ("entregaId") REFERENCES "Entrega"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Revisao" ADD CONSTRAINT "Revisao_revisorId_fkey" FOREIGN KEY ("revisorId") REFERENCES "Usuario"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Certificado" ADD CONSTRAINT "Certificado_usuarioId_fkey" FOREIGN KEY ("usuarioId") REFERENCES "Usuario"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Certificado" ADD CONSTRAINT "Certificado_cursoId_fkey" FOREIGN KEY ("cursoId") REFERENCES "Curso"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Certificado" ADD CONSTRAINT "Certificado_orgId_fkey" FOREIGN KEY ("orgId") REFERENCES "Organizacao"("id") ON DELETE SET NULL ON UPDATE CASCADE;
