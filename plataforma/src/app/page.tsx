import Link from "next/link";
import { db } from "@/lib/db";
import { brl, rotuloNivel } from "@/lib/fmt";

export const dynamic = "force-dynamic";

export default async function Inicio() {
  const [cursos, trilhas] = await Promise.all([
    db.curso.findMany({
      where: { status: "PUBLICADO" },
      orderBy: { criadoEm: "asc" },
      include: { modulos: { include: { _count: { select: { aulas: true } } } } },
    }),
    db.trilha.findMany({
      include: { cursos: { orderBy: { ordem: "asc" }, include: { curso: true } } },
    }),
  ]);
  return (
    <main>
      <section className="heroi">
        <h1>Aprenda robótica montando, programando e entregando projetos.</h1>
        <p className="suave">
          Cada curso tem simulador dentro da aula (Tinkercad e Wokwi), exercícios corrigidos por
          professores e certificado com código de verificação.
        </p>
      </section>

      <h2>Cursos</h2>
      {cursos.length === 0 && <p className="suave">Nenhum curso publicado ainda.</p>}
      <div className="grade">
        {cursos.map((c) => (
          <article className="cartao" key={c.id}>
            <span className="etiqueta">{rotuloNivel[c.nivel]}</span>
            <h3 style={{ marginTop: ".5rem" }}>{c.titulo}</h3>
            <p className="suave pequeno">{c.descricao}</p>
            <div className="fim linha">
              <span className="pequeno">
                {c.modulos.reduce((n, m) => n + m._count.aulas, 0)} aulas · {c.cargaHoraria}h ·{" "}
                <strong>{brl(c.precoCentavos)}</strong>
              </span>
              <Link className="btn mini" href={`/cursos/${c.slug}`}>Ver curso</Link>
            </div>
          </article>
        ))}
      </div>

      {trilhas.length > 0 && <h2>Trilhas de aprendizado</h2>}
      {trilhas.map((t) => (
        <section className="cartao" key={t.id}>
          <h3>{t.titulo}</h3>
          <p className="suave pequeno">{t.descricao}</p>
          <ol>
            {t.cursos.map((tc) => (
              <li key={tc.cursoId}>
                {tc.curso.status === "PUBLICADO" ? (
                  <Link href={`/cursos/${tc.curso.slug}`}>{tc.curso.titulo}</Link>
                ) : (
                  <span className="suave">{tc.curso.titulo} (em breve)</span>
                )}
              </li>
            ))}
          </ol>
        </section>
      ))}
    </main>
  );
}
