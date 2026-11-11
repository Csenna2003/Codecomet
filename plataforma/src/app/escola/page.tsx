import Link from "next/link";
import { redirect } from "next/navigation";
import { Aviso, type SP } from "@/componentes/Aviso";
import { ehAdmin, exigirUsuario, orgsGeridas } from "@/lib/auth";
import { db } from "@/lib/db";
import { data, rotuloPapel } from "@/lib/fmt";
import { criarConvite, criarTurma, desativarConvite, removerMembro } from "../acoes/escola";
import { gerarLinkDeSenha } from "../acoes/conta";

export default async function Escola({ searchParams }: { searchParams: SP }) {
  const u = await exigirUsuario();
  const sp = await searchParams;
  const orgs = ehAdmin(u) ? await db.organizacao.findMany({ orderBy: { nome: "asc" } }) : orgsGeridas(u);
  if (!orgs.length) redirect(ehAdmin(u) ? "/admin" : "/painel");
  // A escola pedida na URL só vale se estiver entre as que o usuário administra.
  const org = orgs.find((o) => o.id === sp.org) ?? orgs[0];

  const [membros, turmas, convites, licencas] = await Promise.all([
    db.membro.findMany({
      where: { orgId: org.id },
      include: {
        usuario: {
          select: { id: true, nome: true, email: true, turmas: { where: { turma: { orgId: org.id } }, include: { turma: true } } },
        },
      },
      orderBy: { usuario: { nome: "asc" } },
    }),
    db.turma.findMany({
      where: { orgId: org.id },
      include: { professor: { select: { nome: true } }, _count: { select: { alunos: true } } },
      orderBy: { nome: "asc" },
    }),
    db.convite.findMany({ where: { orgId: org.id, ativo: true }, include: { turma: true }, orderBy: { criadoEm: "desc" } }),
    db.licenca.findMany({
      where: { orgId: org.id },
      include: {
        curso: { include: { modulos: { include: { aulas: { where: { obrigatoria: true }, select: { id: true } } } } } },
        acessos: { where: { status: "ATIVO" }, select: { usuarioId: true } },
      },
    }),
  ]);
  const alunos = membros.filter((m) => m.papel === "ALUNO");
  const equipe = membros.filter((m) => m.papel !== "ALUNO");
  const idsAlunos = alunos.map((m) => m.usuarioId);

  // Progresso por aluno em cada curso licenciado.
  const cursos = licencas.map((l) => ({
    lic: l,
    aulas: l.curso.modulos.flatMap((m) => m.aulas.map((a) => a.id)),
    comVaga: new Set(l.acessos.map((a) => a.usuarioId)),
  }));
  const progresso = await db.progressoAula.findMany({
    where: { usuarioId: { in: idsAlunos }, aulaId: { in: cursos.flatMap((c) => c.aulas) } },
    select: { usuarioId: true, aulaId: true },
  });
  const feitas = new Set(progresso.map((p) => `${p.usuarioId}:${p.aulaId}`));
  const certs = await db.certificado.findMany({
    where: { usuarioId: { in: idsAlunos }, cursoId: { in: licencas.map((l) => l.cursoId) } },
    select: { usuarioId: true, cursoId: true },
  });
  const comCert = new Set(certs.map((c) => `${c.usuarioId}:${c.cursoId}`));
  const pct = (uid: string, aulas: string[]) =>
    aulas.length ? Math.round((aulas.filter((a) => feitas.has(`${uid}:${a}`)).length / aulas.length) * 100) : 0;

  return (
    <main>
      <h1>{org.nome}</h1>
      {orgs.length > 1 && (
        <p className="pequeno">
          Trocar de escola:{" "}
          {orgs.filter((o) => o.id !== org.id).map((o) => (
            <Link key={o.id} href={`/escola?org=${o.id}`} style={{ marginRight: ".75rem" }}>{o.nome}</Link>
          ))}
        </p>
      )}
      <Aviso sp={sp} />

      <h2>Licenças</h2>
      {licencas.length === 0 && <p className="suave">Esta escola ainda não tem cursos contratados.</p>}
      <div className="grade">
        {cursos.map(({ lic }) => (
          <div className="cartao" key={lic.id}>
            <h3>{lic.curso.titulo}</h3>
            <p>{lic.acessos.length} de {lic.vagasTotal} vagas em uso</p>
            <div className="barra"><span style={{ width: `${Math.min(100, (lic.acessos.length / lic.vagasTotal) * 100)}%` }} /></div>
            <p className="pequeno suave">{lic.validade ? `Válida até ${data(lic.validade)}` : "Sem data de validade"}</p>
          </div>
        ))}
      </div>

      <h2>Alunos e progresso ({alunos.length})</h2>
      {alunos.length === 0 ? (
        <p className="suave">Nenhum aluno ainda. Crie um código de convite abaixo e distribua para a turma.</p>
      ) : (
        <div className="cartao tabela-rolagem">
          <table>
            <thead>
              <tr>
                <th>Aluno</th><th>Turma</th>
                {cursos.map((c) => <th key={c.lic.id}>{c.lic.curso.titulo}</th>)}
                <th />
              </tr>
            </thead>
            <tbody>
              {alunos.map((m) => (
                <tr key={m.id}>
                  <td>{m.usuario.nome}<br /><span className="suave pequeno">{m.usuario.email}</span></td>
                  <td>{m.usuario.turmas.map((t) => t.turma.nome).join(", ") || "—"}</td>
                  {cursos.map((c) => (
                    <td key={c.lic.id}>
                      {comCert.has(`${m.usuarioId}:${c.lic.cursoId}`) ? (
                        <span className="etiqueta ok">Certificado</span>
                      ) : c.comVaga.has(m.usuarioId) ? (
                        `${pct(m.usuarioId, c.aulas)}%`
                      ) : (
                        <span className="etiqueta alerta">Sem vaga</span>
                      )}
                    </td>
                  ))}
                  <td>
                    <form action={gerarLinkDeSenha} style={{ marginBottom: ".4rem" }}>
                      <input type="hidden" name="orgId" value={org.id} />
                      <input type="hidden" name="usuarioId" value={m.usuarioId} />
                      <button className="btn mini sec">Nova senha</button>
                    </form>
                    <form action={removerMembro}>
                      <input type="hidden" name="orgId" value={org.id} />
                      <input type="hidden" name="usuarioId" value={m.usuarioId} />
                      <button className="btn mini perigo">Remover</button>
                    </form>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <h2>Turmas</h2>
      {turmas.map((t) => (
        <p key={t.id}>
          <strong>{t.nome}</strong>{t.periodo && ` (${t.periodo})`} · {t._count.alunos} alunos · professor:{" "}
          {t.professor?.nome ?? "não definido"}
        </p>
      ))}
      <form action={criarTurma} className="cartao campos">
        <input type="hidden" name="orgId" value={org.id} />
        <label>Nome da turma<input name="nome" required placeholder="7º ano B" /></label>
        <label>Período<input name="periodo" placeholder="2027" /></label>
        <label>Professor
          <select name="professorId" defaultValue="">
            <option value="">Definir depois</option>
            {equipe.map((m) => <option key={m.id} value={m.usuarioId}>{m.usuario.nome}</option>)}
          </select>
        </label>
        <div><button className="btn sec">Criar turma</button></div>
      </form>

      <h2>Códigos de convite</h2>
      <p className="suave pequeno">
        O aluno digita o código ao criar a conta (ou em “Meu painel”) e entra direto na turma, já com acesso aos cursos da escola.
      </p>
      {convites.length > 0 && (
        <div className="cartao tabela-rolagem">
          <table>
            <thead><tr><th>Código</th><th>Para</th><th>Turma</th><th>Usos</th><th /></tr></thead>
            <tbody>
              {convites.map((c) => (
                <tr key={c.id}>
                  <td><strong>{c.codigo}</strong></td>
                  <td>{rotuloPapel[c.papel]}</td>
                  <td>{c.turma?.nome ?? "—"}</td>
                  <td>{c.usos}{c.maxUsos ? ` de ${c.maxUsos}` : ""}</td>
                  <td>
                    <form action={desativarConvite}>
                      <input type="hidden" name="orgId" value={org.id} />
                      <input type="hidden" name="conviteId" value={c.id} />
                      <button className="btn mini perigo">Desativar</button>
                    </form>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <form action={criarConvite} className="cartao campos">
        <input type="hidden" name="orgId" value={org.id} />
        <label>Convite para
          <select name="papel"><option value="ALUNO">Alunos</option><option value="PROFESSOR">Professor</option></select>
        </label>
        <label>Turma (para alunos)
          <select name="turmaId" defaultValue="">
            <option value="">Sem turma</option>
            {turmas.map((t) => <option key={t.id} value={t.id}>{t.nome}</option>)}
          </select>
        </label>
        <label>Limite de usos (opcional)<input name="maxUsos" type="number" min={1} /></label>
        <div><button className="btn sec">Gerar código</button></div>
      </form>

      <h2>Equipe</h2>
      {equipe.map((m) => (
        <div className="linha" key={m.id} style={{ marginBottom: ".5rem" }}>
          <span>{m.usuario.nome} — {rotuloPapel[m.papel]} <span className="suave pequeno">({m.usuario.email})</span></span>
          {m.usuarioId !== u.id && m.papel === "PROFESSOR" && (
            <form action={gerarLinkDeSenha}>
              <input type="hidden" name="orgId" value={org.id} />
              <input type="hidden" name="usuarioId" value={m.usuarioId} />
              <button className="btn mini sec">Nova senha</button>
            </form>
          )}
          {m.usuarioId !== u.id && (
            <form action={removerMembro}>
              <input type="hidden" name="orgId" value={org.id} />
              <input type="hidden" name="usuarioId" value={m.usuarioId} />
              <button className="btn mini perigo">Remover</button>
            </form>
          )}
        </div>
      ))}
    </main>
  );
}
