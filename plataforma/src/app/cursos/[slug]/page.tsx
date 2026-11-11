import Link from "next/link";
import { notFound } from "next/navigation";
import { ehAdmin, usuarioAtual } from "@/lib/auth";
import { carregarCurso, estadoDoCurso, modoDeAcesso } from "@/lib/curso";
import { db } from "@/lib/db";
import { brl, rotuloNivel, rotuloTipoAula } from "@/lib/fmt";
import { matricular } from "../../acoes/aluno";

export default async function PaginaCurso({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const curso = await carregarCurso({ slug });
  const u = await usuarioAtual();
  if (!curso || (curso.status !== "PUBLICADO" && !(u && ehAdmin(u)))) notFound();

  const acesso = u ? await modoDeAcesso(u, curso.id) : null;
  const estado = u && acesso?.modo === "aluno" ? await estadoDoCurso(u.id, curso) : null;
  const certificado =
    u && (await db.certificado.findUnique({ where: { usuarioId_cursoId: { usuarioId: u.id, cursoId: curso.id } } }));

  return (
    <main>
      <span className="etiqueta">{rotuloNivel[curso.nivel]}</span>
      <h1 style={{ marginTop: ".5rem" }}>{curso.titulo}</h1>
      <p className="suave">{curso.descricao}</p>

      <div className="cartao linha">
        {estado ? (
          <>
            <div style={{ flex: 1, minWidth: 200 }}>
              <div className="barra"><span style={{ width: `${estado.percentual}%` }} /></div>
              <span className="pequeno">{estado.percentual}% concluído</span>
            </div>
            {certificado ? (
              <Link className="btn" href={`/certificado/${certificado.codigo}`}>Ver certificado</Link>
            ) : estado.proxima ? (
              <Link className="btn" href={`/aprender/${estado.proxima}`}>
                {estado.percentual === 0 ? "Começar o curso" : "Continuar"}
              </Link>
            ) : (
              <span className="etiqueta alerta">Aulas concluídas — aguardando aprovação dos exercícios</span>
            )}
          </>
        ) : acesso?.modo === "preview" ? (
          <span>Você está vendo este curso como equipe (sem registrar progresso).</span>
        ) : (
          <>
            <span>{curso.cargaHoraria}h de carga horária · <strong>{brl(curso.precoCentavos)}</strong></span>
            {u ? (
              <form action={matricular}>
                <input type="hidden" name="cursoId" value={curso.id} />
                <button className="btn">{curso.precoCentavos ? "Comprar curso" : "Matricular-se grátis"}</button>
              </form>
            ) : (
              <Link className="btn" href="/cadastro">Criar conta para começar</Link>
            )}
          </>
        )}
      </div>

      {curso.modulos.map((m) => (
        <section className="cartao" key={m.id}>
          <h3>Módulo {m.ordem}: {m.titulo}</h3>
          <ul className="aulas">
            {m.aulas.map((a) => {
              const e = estado?.porAula.get(a.id);
              const aberta = acesso?.modo === "preview" || e?.liberada;
              return (
                <li key={a.id}>
                  <span className="marca-aula" aria-hidden>{e?.concluida ? "✅" : aberta ? "▶️" : "🔒"}</span>
                  <span className={`titulo ${aberta ? "" : "bloqueada"}`}>
                    {aberta ? <Link href={`/aprender/${a.id}`}>{a.titulo}</Link> : a.titulo}
                    {!a.obrigatoria && <span className="suave pequeno"> (opcional)</span>}
                  </span>
                  <span className="etiqueta">{rotuloTipoAula[a.tipo]}</span>
                </li>
              );
            })}
          </ul>
        </section>
      ))}
    </main>
  );
}
