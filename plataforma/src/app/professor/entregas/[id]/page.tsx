import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { Aviso, type SP } from "@/componentes/Aviso";
import { exigirUsuario } from "@/lib/auth";
import { db } from "@/lib/db";
import { data, rotuloEntrega } from "@/lib/fmt";
import { filtroEntregasRevisaveis } from "@/lib/revisao";
import { embedSimulador } from "@/lib/simulador";
import { revisarEntrega } from "../../../acoes/professor";

export default async function Corrigir({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: SP }) {
  const u = await exigirUsuario();
  const filtro = filtroEntregasRevisaveis(u);
  if (!filtro) redirect("/painel");
  const e = await db.entrega.findFirst({
    where: { AND: [{ id: (await params).id }, filtro] },
    include: {
      usuario: { select: { nome: true } },
      org: true,
      exercicio: { include: { aula: true } },
      revisoes: { orderBy: { criadoEm: "desc" }, include: { revisor: { select: { nome: true } } } },
    },
  });
  if (!e) notFound();
  const ex = e.exercicio;
  const sim = e.link ? embedSimulador(e.link) : null;
  return (
    <main>
      <p className="pequeno"><Link href="/professor">← Correções</Link></p>
      <h1>{ex.aula.titulo}</h1>
      <p className="suave">
        {e.usuario.nome} · {e.org?.nome ?? "Venda direta"} · tentativa {e.tentativa} · enviada em {data(e.enviadoEm)} ·{" "}
        {rotuloEntrega[e.status]}
      </p>
      <Aviso sp={await searchParams} />

      <details className="cartao">
        <summary>Enunciado do exercício</summary>
        <p className="texto-aula">{ex.enunciado}</p>
      </details>

      <h2>Entrega do aluno</h2>
      {e.link && <p><a href={e.link} target="_blank" rel="noopener noreferrer">{e.link} ↗</a></p>}
      {sim && (
        <iframe
          className="quadro simulador"
          src={sim.embed}
          title="Projeto do aluno"
          sandbox="allow-scripts allow-same-origin allow-popups allow-forms"
          loading="lazy"
        />
      )}
      {e.codigo && <pre>{e.codigo}</pre>}
      {e.observacao && <p className="cartao texto-aula">Recado do aluno: {e.observacao}</p>}

      {e.status === "ENVIADA" ? (
        <form action={revisarEntrega} className="pilha cartao">
          <h3>Correção</h3>
          <input type="hidden" name="entregaId" value={e.id} />
          <fieldset>
            <legend>Critérios atendidos</legend>
            {ex.rubrica.map((r, i) => (
              <label className="marcar" key={r}><input type="checkbox" name={`c${i}`} /> <span>{r}</span></label>
            ))}
          </fieldset>
          <div className="campos">
            <label>Nota (0 a {ex.notaMaxima})
              <input name="nota" type="number" min={0} max={ex.notaMaxima} step={1} required />
            </label>
            <label>Decisão
              <select name="decisao" required defaultValue="">
                <option value="" disabled>Escolha…</option>
                <option value="APROVADA">Aprovar</option>
                <option value="PRECISA_AJUSTE">Pedir ajuste</option>
              </select>
            </label>
          </div>
          <label>Comentário para o aluno<textarea name="comentario" rows={4} /></label>
          <div><button className="btn">Registrar correção</button></div>
        </form>
      ) : (
        e.revisoes.map((r) => (
          <div className="cartao" key={r.id}>
            <strong>{rotuloEntrega[r.decisao]} · nota {r.nota}</strong>
            <p className="suave pequeno">por {r.revisor.nome} em {data(r.criadoEm)}</p>
            {r.comentario && <p className="texto-aula">{r.comentario}</p>}
            <ul className="pequeno">
              {ex.rubrica.map((c) => <li key={c}>{r.criteriosAtendidos.includes(c) ? "✅" : "⬜"} {c}</li>)}
            </ul>
          </div>
        ))
      )}
    </main>
  );
}
