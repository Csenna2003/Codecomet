import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { Aviso, type SP } from "@/componentes/Aviso";
import { exigirUsuario } from "@/lib/auth";
import { achatar, carregarCurso, estadoDoCurso, modoDeAcesso } from "@/lib/curso";
import { db } from "@/lib/db";
import { data, rotuloEntrega, rotuloTipoAula, type Pergunta } from "@/lib/fmt";
import { embedSimulador, embedVideo } from "@/lib/simulador";
import { enviarEntrega, marcarConcluida, responderQuiz } from "../../acoes/aluno";

const rotuloLink = {
  LINK_SIMULADOR: "Link do seu projeto no Tinkercad ou no Wokwi",
  LINK_VIDEO: "Link do vídeo mostrando o projeto funcionando",
  CODIGO: "Link do projeto (opcional)",
};

export default async function PaginaAula({
  params,
  searchParams,
}: {
  params: Promise<{ aulaId: string }>;
  searchParams: SP;
}) {
  const u = await exigirUsuario();
  const aula = await db.aula.findUnique({ where: { id: (await params).aulaId }, include: { modulo: true } });
  if (!aula) notFound();
  const curso = await carregarCurso({ id: aula.modulo.cursoId });
  if (!curso) notFound();
  const acesso = await modoDeAcesso(u, curso.id);
  if (!acesso) redirect(`/cursos/${curso.slug}`);

  const aluno = acesso.modo === "aluno";
  const estado = aluno ? await estadoDoCurso(u.id, curso) : null;
  const meu = estado?.porAula.get(aula.id);
  if (aluno && !meu?.liberada) redirect(`/cursos/${curso.slug}`);

  const aulas = achatar(curso);
  const i = aulas.findIndex((a) => a.id === aula.id);
  const anterior = aulas[i - 1];
  const seguinte = aulas[i + 1];
  const seguinteAberta = seguinte && (!aluno || estado?.porAula.get(seguinte.id)?.liberada);
  const exercicio = aulas[i].exercicio;
  const video = aula.videoUrl ? embedVideo(aula.videoUrl) : null;
  const sim = aula.simuladorUrl ? embedSimulador(aula.simuladorUrl) : null;
  const perguntas = (aula.quiz ?? []) as Pergunta[];

  const entregas =
    aluno && exercicio
      ? await db.entrega.findMany({
          where: { exercicioId: exercicio.id, usuarioId: u.id },
          orderBy: { tentativa: "desc" },
          include: { revisoes: { orderBy: { criadoEm: "desc" }, take: 1 } },
        })
      : [];
  const ultima = entregas[0];
  const esgotou = !!exercicio?.maxTentativas && entregas.length >= exercicio.maxTentativas;
  const podeEnviar = aluno && exercicio && (!ultima || (ultima.status === "PRECISA_AJUSTE" && !esgotou));

  return (
    <main>
      <p className="pequeno">
        <Link href={`/cursos/${curso.slug}`}>← {curso.titulo}</Link> · {aula.modulo.titulo}
      </p>
      <div className="linha">
        <h1>{aula.titulo}</h1>
        <span>
          <span className="etiqueta">{rotuloTipoAula[aula.tipo]}</span>{" "}
          {meu?.concluida && <span className="etiqueta ok">Concluída</span>}
        </span>
      </div>
      <Aviso sp={await searchParams} />
      {!aluno && <p className="aviso alerta">Visualização da equipe: o progresso não é registrado.</p>}

      {video && (
        <iframe
          className="quadro video"
          src={video}
          title={aula.titulo}
          allow="accelerometer; encrypted-media; picture-in-picture; fullscreen"
          allowFullScreen
        />
      )}

      {aula.conteudo && <div className="cartao texto-aula">{aula.conteudo}</div>}

      {sim && (
        <section>
          <h2>Simulador ({sim.plataforma === "WOKWI" ? "Wokwi" : "Tinkercad"})</h2>
          <iframe
            className="quadro simulador"
            src={sim.embed}
            title={`Simulador da aula ${aula.titulo}`}
            sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-downloads"
            loading="lazy"
          />
          <p className="pequeno suave">
            Para guardar o seu trabalho, abra o projeto na sua conta e salve uma cópia:{" "}
            <a href={sim.original} target="_blank" rel="noopener noreferrer">abrir o simulador em nova aba ↗</a>
          </p>
        </section>
      )}

      {aula.tipo === "QUIZ" && (
        <form action={responderQuiz} className="pilha">
          <input type="hidden" name="aulaId" value={aula.id} />
          {perguntas.map((p, n) => (
            <fieldset key={n}>
              <legend>{n + 1}. {p.pergunta}</legend>
              {p.opcoes.map((o, k) => (
                <label className="marcar" key={k}>
                  <input type="radio" name={`q${n}`} value={k} required /> <span>{o}</span>
                </label>
              ))}
            </fieldset>
          ))}
          {aluno && <div><button className="btn">{meu?.concluida ? "Responder de novo" : "Enviar respostas"}</button></div>}
        </form>
      )}

      {exercicio && (
        <section>
          <h2>Seu desafio</h2>
          <div className="cartao">
            <p className="texto-aula">{exercicio.enunciado}</p>
            <h3>Como você será avaliado</h3>
            <ul>{exercicio.rubrica.map((r) => <li key={r}>{r}</li>)}</ul>
            {exercicio.maxTentativas && (
              <p className="pequeno suave">Tentativas: {entregas.length} de {exercicio.maxTentativas}</p>
            )}
          </div>

          {entregas.map((e) => (
            <div className="cartao" key={e.id}>
              <div className="linha">
                <strong>Tentativa {e.tentativa} · enviada em {data(e.enviadoEm)}</strong>
                <span className={`etiqueta ${e.status === "APROVADA" ? "ok" : e.status === "PRECISA_AJUSTE" ? "erro" : "alerta"}`}>
                  {rotuloEntrega[e.status]}
                </span>
              </div>
              {e.link && <p className="pequeno"><a href={e.link} target="_blank" rel="noopener noreferrer">{e.link}</a></p>}
              {e.revisoes[0] && (
                <>
                  <p>Nota: <strong>{e.revisoes[0].nota}</strong> de {exercicio.notaMaxima}</p>
                  {e.revisoes[0].comentario && <p className="texto-aula">💬 {e.revisoes[0].comentario}</p>}
                  <ul className="pequeno">
                    {exercicio.rubrica.map((r) => (
                      <li key={r}>{e.revisoes[0].criteriosAtendidos.includes(r) ? "✅" : "⬜"} {r}</li>
                    ))}
                  </ul>
                </>
              )}
            </div>
          ))}

          {podeEnviar && (
            <form action={enviarEntrega} className="pilha cartao">
              <h3>{ultima ? "Reenviar com os ajustes" : "Enviar meu projeto"}</h3>
              <input type="hidden" name="aulaId" value={aula.id} />
              <label>{rotuloLink[exercicio.tipoEntrega]}
                <input name="link" type="url" placeholder="https://" required={exercicio.tipoEntrega !== "CODIGO"} />
              </label>
              <label>
                {exercicio.tipoEntrega === "CODIGO" ? "Código do programa" : "Código do programa (opcional, mas recomendado)"}
                <textarea className="codigo" name="codigo" rows={10} required={exercicio.tipoEntrega === "CODIGO"} />
              </label>
              <label>Recado para o professor (opcional)<textarea name="observacao" rows={2} /></label>
              <div><button className="btn">Enviar para correção</button></div>
            </form>
          )}
          {aluno && ultima?.status === "PRECISA_AJUSTE" && esgotou && (
            <p className="aviso erro">Você usou todas as tentativas. Fale com seu professor.</p>
          )}
        </section>
      )}

      <div className="linha" style={{ marginTop: "1.5rem" }}>
        {anterior ? <Link className="btn sec" href={`/aprender/${anterior.id}`}>← Aula anterior</Link> : <span />}
        {aluno && !meu?.concluida && aula.tipo !== "QUIZ" && aula.tipo !== "EXERCICIO" && (
          <form action={marcarConcluida}>
            <input type="hidden" name="aulaId" value={aula.id} />
            <button className="btn">Marcar como concluída</button>
          </form>
        )}
        {seguinteAberta ? (
          <Link className="btn" href={`/aprender/${seguinte.id}`}>Próxima aula →</Link>
        ) : seguinte ? (
          <span className="suave pequeno">Conclua esta aula para liberar a próxima 🔒</span>
        ) : (
          <Link className="btn sec" href={`/cursos/${curso.slug}`}>Voltar ao curso</Link>
        )}
      </div>
    </main>
  );
}
