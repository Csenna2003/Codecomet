import Link from "next/link";
import { notFound } from "next/navigation";
import { Aviso, type SP } from "@/componentes/Aviso";
import { exigirAdmin } from "@/lib/auth";
import { carregarCurso } from "@/lib/curso";
import { rotuloTipoAula } from "@/lib/fmt";
import { atualizarCurso, criarAula, criarModulo, excluirAula } from "../../../acoes/admin";

export default async function EditarCurso({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: SP }) {
  await exigirAdmin();
  const curso = await carregarCurso({ id: (await params).id });
  if (!curso) notFound();
  return (
    <main>
      <p className="pequeno"><Link href="/admin">← Administração</Link> · <Link href={`/cursos/${curso.slug}`}>Ver como aluno</Link></p>
      <h1>{curso.titulo}</h1>
      <Aviso sp={await searchParams} />

      <form action={atualizarCurso} className="pilha cartao">
        <input type="hidden" name="cursoId" value={curso.id} />
        <label>Título<input name="titulo" defaultValue={curso.titulo} required /></label>
        <label>Descrição<textarea name="descricao" defaultValue={curso.descricao} rows={3} required /></label>
        <div className="campos">
          <label>Nível
            <select name="nivel" defaultValue={curso.nivel}>
              <option value="INICIANTE">Iniciante</option>
              <option value="INTERMEDIARIO">Intermediário</option>
              <option value="AVANCADO">Avançado</option>
            </select>
          </label>
          <label>Preço em R$ (0 = gratuito)
            <input name="preco" inputMode="decimal" defaultValue={(curso.precoCentavos / 100).toFixed(2)} required />
          </label>
          <label>Carga horária (h)<input name="cargaHoraria" type="number" min={0} defaultValue={curso.cargaHoraria} required /></label>
          <label>Status
            <select name="status" defaultValue={curso.status}>
              <option value="RASCUNHO">Rascunho</option>
              <option value="PUBLICADO">Publicado</option>
              <option value="ARQUIVADO">Arquivado</option>
            </select>
          </label>
        </div>
        <div><button className="btn">Salvar curso</button></div>
      </form>

      {curso.modulos.map((m) => (
        <section className="cartao" key={m.id}>
          <h3>Módulo {m.ordem}: {m.titulo}</h3>
          <ul className="aulas">
            {m.aulas.map((a) => (
              <li key={a.id}>
                <span className="titulo">
                  {a.ordem}. <Link href={`/aprender/${a.id}`}>{a.titulo}</Link>
                  {!a.obrigatoria && <span className="suave pequeno"> (opcional)</span>}
                </span>
                <span className="etiqueta">{rotuloTipoAula[a.tipo]}</span>
                <form action={excluirAula}>
                  <input type="hidden" name="aulaId" value={a.id} />
                  <button className="btn mini perigo">Excluir</button>
                </form>
              </li>
            ))}
          </ul>
          <details style={{ marginTop: ".75rem" }}>
            <summary>Adicionar aula</summary>
            <form action={criarAula} className="pilha">
              <input type="hidden" name="moduloId" value={m.id} />
              <div className="campos">
                <label>Título<input name="titulo" required /></label>
                <label>Tipo
                  <select name="tipo">
                    <option value="VIDEO">Vídeo</option>
                    <option value="TEXTO">Leitura</option>
                    <option value="SIMULADOR">Simulador</option>
                    <option value="QUIZ">Quiz</option>
                    <option value="EXERCICIO">Exercício com entrega</option>
                  </select>
                </label>
                <label>Duração (min)<input name="duracaoMin" type="number" min={0} /></label>
              </div>
              <label>Texto da aula<textarea name="conteudo" rows={4} /></label>
              <label>Link do vídeo (YouTube ou Vimeo)<input name="videoUrl" type="url" /></label>
              <label>Link do projeto no simulador (tinkercad.com/things/… ou wokwi.com/projects/…)
                <input name="simuladorUrl" type="url" />
              </label>
              <fieldset className="pilha" style={{ display: "grid", gap: ".75rem" }}>
                <legend>Só para quiz</legend>
                <label>Perguntas — separe com uma linha em branco; marque a opção correta com * e as outras com -
                  <textarea name="quiz" rows={5} placeholder={"Qual pino liga o LED?\n* GPIO16\n- GPIO34"} />
                </label>
              </fieldset>
              <fieldset className="pilha" style={{ display: "grid", gap: ".75rem" }}>
                <legend>Só para exercício</legend>
                <label>Enunciado<textarea name="enunciado" rows={3} /></label>
                <label>Critérios de avaliação (um por linha)<textarea name="rubrica" rows={3} /></label>
                <div className="campos">
                  <label>O aluno entrega
                    <select name="tipoEntrega">
                      <option value="LINK_SIMULADOR">Link do Tinkercad/Wokwi</option>
                      <option value="CODIGO">Código</option>
                      <option value="LINK_VIDEO">Link de vídeo (projeto físico)</option>
                    </select>
                  </label>
                  <label>Limite de tentativas (opcional)<input name="maxTentativas" type="number" min={1} /></label>
                </div>
              </fieldset>
              <label className="marcar"><input type="checkbox" name="opcional" /> <span>Aula opcional (não bloqueia as próximas nem o certificado)</span></label>
              <div><button className="btn sec">Adicionar aula</button></div>
            </form>
          </details>
        </section>
      ))}

      <form action={criarModulo} className="cartao campos">
        <input type="hidden" name="cursoId" value={curso.id} />
        <label>Novo módulo<input name="titulo" required /></label>
        <div><button className="btn sec">Adicionar módulo</button></div>
      </form>
    </main>
  );
}
