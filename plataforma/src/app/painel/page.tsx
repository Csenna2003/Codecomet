import Link from "next/link";
import { Aviso, type SP } from "@/componentes/Aviso";
import { exigirUsuario } from "@/lib/auth";
import { db } from "@/lib/db";
import { carregarCurso, estadoDoCurso } from "@/lib/curso";
import { data, rotuloPapel } from "@/lib/fmt";
import { usarCodigoEscola } from "../acoes/conta";

export default async function Painel({ searchParams }: { searchParams: SP }) {
  const u = await exigirUsuario();
  const agora = new Date();
  const acessos = await db.acesso.findMany({
    where: { usuarioId: u.id, status: "ATIVO", OR: [{ fim: null }, { fim: { gt: agora } }] },
    include: { org: true },
    orderBy: { inicio: "asc" },
  });
  const cursos = await Promise.all(
    acessos.map(async (a) => {
      const curso = (await carregarCurso({ id: a.cursoId }))!;
      return { a, curso, estado: await estadoDoCurso(u.id, curso) };
    }),
  );
  const [certificados, devolvidas] = await Promise.all([
    db.certificado.findMany({ where: { usuarioId: u.id }, include: { curso: true } }),
    db.entrega.findMany({
      where: { usuarioId: u.id, status: "PRECISA_AJUSTE" },
      include: { exercicio: { include: { aula: true, entregas: { where: { usuarioId: u.id } } } } },
    }),
  ]);
  // Só avisa do ajuste se o aluno ainda não reenviou.
  const pendentes = devolvidas.filter(
    (e) => !e.exercicio.entregas.some((o) => o.tentativa > e.tentativa),
  );

  return (
    <main>
      <h1>Olá, {u.nome.split(" ")[0]}!</h1>
      <Aviso sp={await searchParams} />

      {pendentes.map((e) => (
        <p className="aviso alerta" key={e.id}>
          Seu professor pediu um ajuste em <strong>{e.exercicio.aula.titulo}</strong>.{" "}
          <Link href={`/aprender/${e.exercicio.aulaId}`}>Ver o comentário e reenviar</Link>
        </p>
      ))}

      <h2>Meus cursos</h2>
      {cursos.length === 0 && (
        <p className="suave">
          Você ainda não está em nenhum curso. <Link href="/">Veja os cursos</Link> ou use o código da sua escola abaixo.
        </p>
      )}
      <div className="grade">
        {cursos.map(({ a, curso, estado }) => (
          <article className="cartao" key={a.id}>
            <h3>{curso.titulo}</h3>
            <p className="pequeno suave">
              {a.org ? `Pela escola ${a.org.nome}` : "Compra individual"}
              {a.fim && ` · acesso até ${data(a.fim)}`}
            </p>
            <div className="barra" aria-label={`${estado.percentual}% concluído`}>
              <span style={{ width: `${estado.percentual}%` }} />
            </div>
            <p className="pequeno">{estado.percentual}% concluído</p>
            <div className="fim">
              <Link className="btn mini" href={estado.proxima ? `/aprender/${estado.proxima}` : `/cursos/${curso.slug}`}>
                {estado.percentual === 0 ? "Começar" : estado.proxima ? "Continuar" : "Rever curso"}
              </Link>
            </div>
          </article>
        ))}
      </div>

      <h2>Certificados</h2>
      {certificados.length === 0 && (
        <p className="suave">Conclua todas as aulas e tenha os exercícios aprovados para receber o certificado.</p>
      )}
      {certificados.map((c) => (
        <p className="cartao linha" key={c.id}>
          <span><strong>{c.curso.titulo}</strong> · emitido em {data(c.emitidoEm)}</span>
          <Link className="btn mini" href={`/certificado/${c.codigo}`}>Abrir certificado</Link>
        </p>
      ))}

      <h2>Minha escola</h2>
      {u.membros.map((m) => (
        <p key={m.id}>{m.org.nome} — {rotuloPapel[m.papel]}</p>
      ))}
      <form action={usarCodigoEscola} className="cartao campos">
        <label>Tenho um código da escola<input name="codigo" required autoCapitalize="characters" /></label>
        <div><button className="btn sec">Usar código</button></div>
      </form>
    </main>
  );
}
