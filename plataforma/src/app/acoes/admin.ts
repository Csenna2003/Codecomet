"use server";

import { redirect } from "next/navigation";
import type { Nivel, StatusCurso, TipoAula, TipoEntrega } from "@prisma/client";
import { db } from "@/lib/db";
import { exigirAdmin } from "@/lib/auth";
import { codigoAleatorio, parseQuiz, slugify } from "@/lib/fmt";
import { aplicarLicencas } from "@/lib/escola";
import { embedSimulador, embedVideo } from "@/lib/simulador";
import { confirmarPedido } from "@/lib/pedidos";

const txt = (f: FormData, k: string) => String(f.get(k) ?? "").trim();
const ir = (rota: string, tipo: "ok" | "erro", msg: string): never =>
  redirect(`${rota}?${tipo}=${encodeURIComponent(msg)}`);

export async function criarOrganizacao(f: FormData) {
  await exigirAdmin();
  const nome = txt(f, "nome");
  if (!nome) ir("/admin", "erro", "Informe o nome da escola.");
  const org = await db.organizacao.create({
    data: { nome, documento: txt(f, "documento") || null, tipo: f.get("tipo") === "EMPRESA" ? "EMPRESA" : "ESCOLA" },
  });
  // Código de uso único para a pessoa que vai administrar a escola.
  const convite = await db.convite.create({
    data: { orgId: org.id, papel: "ADMIN_ORG", maxUsos: 1, codigo: codigoAleatorio(8) },
  });
  ir("/admin", "ok", `Escola criada. Envie ao responsável o código de administrador: ${convite.codigo}`);
}

export async function criarLicenca(f: FormData) {
  const admin = await exigirAdmin();
  const orgId = txt(f, "orgId");
  const cursoId = txt(f, "cursoId");
  const vagasTotal = Number(txt(f, "vagas"));
  const validadeTxt = txt(f, "validade");
  if (!orgId || !cursoId || !Number.isInteger(vagasTotal) || vagasTotal < 1)
    ir("/admin", "erro", "Escolha a escola, o curso e um número de vagas válido.");
  const validade = validadeTxt ? new Date(`${validadeTxt}T23:59:59-03:00`) : null;
  const lic = await db.licenca.upsert({
    where: { orgId_cursoId: { orgId, cursoId } },
    create: { orgId, cursoId, vagasTotal, validade },
    update: { vagasTotal, validade },
  });
  // Mantém o prazo dos acessos já concedidos igual ao da licença renovada.
  await db.acesso.updateMany({ where: { licencaId: lic.id }, data: { fim: validade } });
  await aplicarLicencas(orgId);
  await db.eventoAuditoria.create({
    data: { atorId: admin.id, orgId, acao: "licenca.definida", alvoTipo: "Licenca", alvoId: lic.id, dados: { vagasTotal } },
  });
  ir("/admin", "ok", "Licença salva e vagas distribuídas aos alunos da escola.");
}

export async function criarCurso(f: FormData) {
  await exigirAdmin();
  const titulo = txt(f, "titulo");
  if (!titulo) ir("/admin", "erro", "Informe o título do curso.");
  let slug = slugify(titulo) || "curso";
  if (await db.curso.findUnique({ where: { slug } })) slug += "-" + codigoAleatorio(4).toLowerCase();
  const curso = await db.curso.create({ data: { titulo, slug, descricao: txt(f, "descricao") } });
  redirect(`/admin/cursos/${curso.id}`);
}

export async function atualizarCurso(f: FormData) {
  await exigirAdmin();
  const id = txt(f, "cursoId");
  const rota = `/admin/cursos/${id}`;
  const status = txt(f, "status") as StatusCurso;
  const preco = Math.round(Number(txt(f, "preco").replace(",", ".")) * 100);
  const cargaHoraria = Number(txt(f, "cargaHoraria"));
  if (!["RASCUNHO", "PUBLICADO", "ARQUIVADO"].includes(status) || !(preco >= 0) || !(cargaHoraria >= 0))
    ir(rota, "erro", "Confira preço, carga horária e status.");
  if (status === "PUBLICADO" && !(await db.aula.count({ where: { modulo: { cursoId: id } } })))
    ir(rota, "erro", "Adicione pelo menos uma aula antes de publicar.");
  await db.curso.update({
    where: { id },
    data: {
      titulo: txt(f, "titulo"),
      descricao: txt(f, "descricao"),
      nivel: txt(f, "nivel") as Nivel,
      status,
      precoCentavos: preco,
      cargaHoraria: Math.round(cargaHoraria),
    },
  });
  ir(rota, "ok", "Curso salvo.");
}

export async function criarModulo(f: FormData) {
  await exigirAdmin();
  const cursoId = txt(f, "cursoId");
  const titulo = txt(f, "titulo");
  if (!titulo) ir(`/admin/cursos/${cursoId}`, "erro", "Informe o título do módulo.");
  const ultimo = await db.modulo.findFirst({ where: { cursoId }, orderBy: { ordem: "desc" } });
  await db.modulo.create({ data: { cursoId, titulo, ordem: (ultimo?.ordem ?? 0) + 1 } });
  ir(`/admin/cursos/${cursoId}`, "ok", "Módulo criado.");
}

export async function criarAula(f: FormData) {
  await exigirAdmin();
  const moduloId = txt(f, "moduloId");
  const modulo = await db.modulo.findUnique({ where: { id: moduloId } });
  if (!modulo) redirect("/admin");
  const rota = `/admin/cursos/${modulo.cursoId}`;
  const titulo = txt(f, "titulo");
  const tipo = txt(f, "tipo") as TipoAula;
  if (!titulo || !["VIDEO", "TEXTO", "QUIZ", "SIMULADOR", "EXERCICIO"].includes(tipo))
    ir(rota, "erro", "Informe título e tipo da aula.");

  const videoUrl = txt(f, "videoUrl") || null;
  const simuladorUrl = txt(f, "simuladorUrl") || null;
  if (tipo === "VIDEO" && !(videoUrl && embedVideo(videoUrl)))
    ir(rota, "erro", "Aula em vídeo precisa de um link do YouTube ou do Vimeo.");
  if (videoUrl && !embedVideo(videoUrl)) ir(rota, "erro", "Link de vídeo não reconhecido (use YouTube ou Vimeo).");
  if (tipo === "SIMULADOR" && !simuladorUrl) ir(rota, "erro", "Aula de simulador precisa do link do projeto.");
  if (simuladorUrl && !embedSimulador(simuladorUrl))
    ir(rota, "erro", "Link de simulador não reconhecido. Use tinkercad.com/things/... ou wokwi.com/projects/...");

  const quiz = tipo === "QUIZ" ? parseQuiz(String(f.get("quiz") ?? "")) : [];
  if (tipo === "QUIZ" && !quiz.length)
    ir(rota, "erro", "Quiz sem perguntas válidas. Marque a opção correta com * e as demais com -.");

  const rubrica = String(f.get("rubrica") ?? "").split("\n").map((l) => l.trim()).filter(Boolean);
  const enunciado = txt(f, "enunciado");
  if (tipo === "EXERCICIO" && (!enunciado || !rubrica.length))
    ir(rota, "erro", "Exercício precisa de enunciado e de pelo menos um critério de avaliação.");
  const tipoEntrega = (["LINK_SIMULADOR", "CODIGO", "LINK_VIDEO"].includes(txt(f, "tipoEntrega"))
    ? txt(f, "tipoEntrega")
    : "LINK_SIMULADOR") as TipoEntrega;

  const ultima = await db.aula.findFirst({ where: { moduloId }, orderBy: { ordem: "desc" } });
  await db.aula.create({
    data: {
      moduloId,
      titulo,
      tipo,
      ordem: (ultima?.ordem ?? 0) + 1,
      conteudo: String(f.get("conteudo") ?? "").trim(),
      videoUrl,
      simuladorUrl,
      quiz: tipo === "QUIZ" ? quiz : undefined,
      obrigatoria: f.get("opcional") !== "on",
      duracaoMin: Number(txt(f, "duracaoMin")) || 0,
      exercicio:
        tipo === "EXERCICIO"
          ? {
              create: {
                enunciado,
                rubrica,
                tipoEntrega,
                notaMaxima: 10,
                maxTentativas: Number(txt(f, "maxTentativas")) || null,
              },
            }
          : undefined,
    },
  });
  ir(rota, "ok", "Aula criada.");
}

export async function excluirAula(f: FormData) {
  await exigirAdmin();
  const aula = await db.aula.findUnique({
    where: { id: txt(f, "aulaId") },
    include: { modulo: true, exercicio: { include: { _count: { select: { entregas: true } } } } },
  });
  if (!aula) redirect("/admin");
  const rota = `/admin/cursos/${aula.modulo.cursoId}`;
  // Excluir apagaria as entregas dos alunos em cascata.
  if (aula.exercicio?._count.entregas) ir(rota, "erro", "Esta aula já tem entregas de alunos e não pode ser excluída.");
  await db.aula.delete({ where: { id: aula.id } });
  ir(rota, "ok", "Aula excluída.");
}

export async function confirmarPagamento(f: FormData) {
  const admin = await exigirAdmin();
  const id = txt(f, "pedidoId");
  const pedido = await db.pedido.findUnique({ where: { id } });
  if (!pedido || pedido.status !== "PENDENTE") ir("/admin/pedidos", "erro", "Este pedido não está mais pendente.");
  await confirmarPedido(id, "pix-manual");
  await db.eventoAuditoria.create({
    data: { atorId: admin.id, acao: "pedido.confirmado", alvoTipo: "Pedido", alvoId: id, dados: { valorCentavos: pedido!.valorCentavos } },
  });
  ir("/admin/pedidos", "ok", "Pagamento confirmado e curso liberado para o aluno.");
}

export async function cancelarPedido(f: FormData) {
  const admin = await exigirAdmin();
  const id = txt(f, "pedidoId");
  const r = await db.pedido.updateMany({ where: { id, status: "PENDENTE" }, data: { status: "CANCELADO" } });
  if (!r.count) ir("/admin/pedidos", "erro", "Este pedido não está mais pendente.");
  await db.eventoAuditoria.create({ data: { atorId: admin.id, acao: "pedido.cancelado", alvoTipo: "Pedido", alvoId: id } });
  ir("/admin/pedidos", "ok", "Pedido cancelado.");
}
