import Link from "next/link";
import { redirect } from "next/navigation";
import { Aviso, type SP } from "@/componentes/Aviso";
import { exigirUsuario } from "@/lib/auth";
import { db } from "@/lib/db";
import { data, rotuloEntrega } from "@/lib/fmt";
import { filtroEntregasRevisaveis } from "@/lib/revisao";

export default async function Correcoes({ searchParams }: { searchParams: SP }) {
  const u = await exigirUsuario();
  const filtro = filtroEntregasRevisaveis(u);
  if (!filtro) redirect("/painel");
  const incluir = {
    usuario: { select: { nome: true } },
    org: true,
    exercicio: { include: { aula: { include: { modulo: { include: { curso: true } } } } } },
  };
  const [fila, recentes] = await Promise.all([
    db.entrega.findMany({
      where: { AND: [filtro, { status: "ENVIADA" }] },
      orderBy: { enviadoEm: "asc" },
      include: incluir,
    }),
    db.entrega.findMany({
      where: { AND: [filtro, { status: { not: "ENVIADA" } }] },
      orderBy: { revisadoEm: "desc" },
      take: 20,
      include: incluir,
    }),
  ]);
  const Tabela = ({ itens, acao }: { itens: typeof fila; acao: string }) => (
    <div className="cartao tabela-rolagem">
      <table>
        <thead>
          <tr><th>Aluno</th><th>Escola</th><th>Exercício</th><th>Enviada em</th><th>Situação</th><th /></tr>
        </thead>
        <tbody>
          {itens.map((e) => (
            <tr key={e.id}>
              <td>{e.usuario.nome}</td>
              <td>{e.org?.nome ?? "Venda direta"}</td>
              <td>
                {e.exercicio.aula.titulo}
                <br /><span className="suave pequeno">{e.exercicio.aula.modulo.curso.titulo} · tentativa {e.tentativa}</span>
              </td>
              <td>{data(e.enviadoEm)}</td>
              <td>{rotuloEntrega[e.status]}{e.nota != null && ` · nota ${e.nota}`}</td>
              <td><Link href={`/professor/entregas/${e.id}`}>{acao}</Link></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
  return (
    <main>
      <h1>Correções</h1>
      <Aviso sp={await searchParams} />
      <h2>Aguardando correção ({fila.length})</h2>
      {fila.length ? <Tabela itens={fila} acao="Corrigir" /> : <p className="suave">Nenhuma entrega na fila. 🎉</p>}
      <h2>Corrigidas recentemente</h2>
      {recentes.length ? <Tabela itens={recentes} acao="Ver" /> : <p className="suave">Nada por aqui ainda.</p>}
    </main>
  );
}
