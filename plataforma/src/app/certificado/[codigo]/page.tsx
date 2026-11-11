import { notFound } from "next/navigation";
import { BotaoImprimir } from "@/componentes/BotaoImprimir";
import { db } from "@/lib/db";
import { data } from "@/lib/fmt";

// Página pública: qualquer pessoa com o código confere a autenticidade.
export default async function Certificado({ params }: { params: Promise<{ codigo: string }> }) {
  const codigo = decodeURIComponent((await params).codigo).toUpperCase();
  const c = await db.certificado.findUnique({
    where: { codigo },
    include: { usuario: { select: { nome: true } }, curso: true, org: true },
  });
  if (!c) notFound();
  return (
    <main>
      <p className="aviso ok nao-imprimir">Certificado autêntico, emitido pela Codecomet.</p>
      <div className="certificado">
        <h1>CERTIFICADO DE CONCLUSÃO</h1>
        <p>Certificamos que</p>
        <p className="nome">{c.usuario.nome}</p>
        <p>
          concluiu o curso <strong>{c.curso.titulo}</strong>, com carga horária de{" "}
          <strong>{c.cargaHoraria} horas</strong>, tendo todas as aulas concluídas e os projetos práticos aprovados.
        </p>
        {c.org && <p>Realizado por meio de {c.org.nome}.</p>}
        <p>Emitido em {data(c.emitidoEm)}.</p>
        <p className="suave pequeno">Código de verificação: {c.codigo}</p>
      </div>
      <p className="nao-imprimir" style={{ marginTop: "1rem" }}><BotaoImprimir /></p>
    </main>
  );
}
