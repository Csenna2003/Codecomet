import Link from "next/link";
import { Aviso, type SP } from "@/componentes/Aviso";
import { exigirAdmin } from "@/lib/auth";
import { db } from "@/lib/db";
import { brl } from "@/lib/fmt";
import { criarCurso, criarLicenca, criarOrganizacao } from "../acoes/admin";

const rotuloStatus = { RASCUNHO: "Rascunho", PUBLICADO: "Publicado", ARQUIVADO: "Arquivado" };

export default async function Admin({ searchParams }: { searchParams: SP }) {
  await exigirAdmin();
  const [orgs, cursos, usuarios, pagos, pendentes] = await Promise.all([
    db.organizacao.findMany({
      orderBy: { nome: "asc" },
      include: { _count: { select: { membros: true } }, licencas: { include: { curso: true } } },
    }),
    db.curso.findMany({ orderBy: { criadoEm: "asc" }, include: { _count: { select: { acessos: true } } } }),
    db.usuario.count(),
    db.pedido.aggregate({ where: { status: "PAGO" }, _sum: { valorCentavos: true }, _count: true }),
    db.pedido.count({ where: { status: "PENDENTE", valorCentavos: { gt: 0 } } }),
  ]);
  return (
    <main>
      <h1>Administração</h1>
      <Aviso sp={await searchParams} />
      <div className="grade">
        <div className="cartao"><h3>{usuarios}</h3><span className="suave">usuários</span></div>
        <div className="cartao"><h3>{orgs.length}</h3><span className="suave">escolas</span></div>
        <div className="cartao">
          <h3>{pagos._sum.valorCentavos ? brl(pagos._sum.valorCentavos) : "R$ 0,00"}</h3>
          <span className="suave">em {pagos._count} pedidos pagos (venda direta)</span>
        </div>
        <div className="cartao">
          <h3>{pendentes}</h3>
          <span className="suave">Pix aguardando conferência · </span>
          <Link href="/admin/pedidos">Conferir pagamentos</Link>
        </div>
      </div>

      <h2>Cursos</h2>
      <div className="cartao tabela-rolagem">
        <table>
          <thead><tr><th>Curso</th><th>Status</th><th>Preço</th><th>Matrículas</th><th /></tr></thead>
          <tbody>
            {cursos.map((c) => (
              <tr key={c.id}>
                <td>{c.titulo}</td>
                <td>{rotuloStatus[c.status]}</td>
                <td>{brl(c.precoCentavos)}</td>
                <td>{c._count.acessos}</td>
                <td><Link href={`/admin/cursos/${c.id}`}>Editar conteúdo</Link></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <form action={criarCurso} className="cartao campos">
        <label>Título do novo curso<input name="titulo" required /></label>
        <label>Descrição curta<input name="descricao" required /></label>
        <div><button className="btn sec">Criar curso</button></div>
      </form>

      <h2>Escolas</h2>
      {orgs.map((o) => (
        <div className="cartao" key={o.id}>
          <div className="linha">
            <strong>{o.nome}</strong>
            <Link href={`/escola?org=${o.id}`}>Abrir painel da escola</Link>
          </div>
          <p className="pequeno suave">{o._count.membros} pessoas{o.documento && ` · ${o.documento}`}</p>
          {o.licencas.map((l) => (
            <p className="pequeno" key={l.id}>Licença: {l.curso.titulo} — {l.vagasTotal} vagas</p>
          ))}
        </div>
      ))}
      <form action={criarOrganizacao} className="cartao campos">
        <label>Nome da nova escola<input name="nome" required /></label>
        <label>CNPJ (opcional)<input name="documento" /></label>
        <label>Tipo
          <select name="tipo"><option value="ESCOLA">Escola</option><option value="EMPRESA">Empresa</option></select>
        </label>
        <div><button className="btn sec">Criar escola</button></div>
      </form>

      <h2>Liberar curso para uma escola</h2>
      <form action={criarLicenca} className="cartao campos">
        <label>Escola
          <select name="orgId" required>{orgs.map((o) => <option key={o.id} value={o.id}>{o.nome}</option>)}</select>
        </label>
        <label>Curso
          <select name="cursoId" required>{cursos.map((c) => <option key={c.id} value={c.id}>{c.titulo}</option>)}</select>
        </label>
        <label>Vagas<input name="vagas" type="number" min={1} required /></label>
        <label>Válida até (opcional)<input name="validade" type="date" /></label>
        <div><button className="btn sec">Salvar licença</button></div>
      </form>
    </main>
  );
}
