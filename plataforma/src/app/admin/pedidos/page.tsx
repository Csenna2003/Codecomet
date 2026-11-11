import Link from "next/link";
import { Aviso, type SP } from "@/componentes/Aviso";
import { exigirAdmin } from "@/lib/auth";
import { db } from "@/lib/db";
import { brl, data } from "@/lib/fmt";
import { cancelarPedido, confirmarPagamento } from "../../acoes/admin";

export default async function Pedidos({ searchParams }: { searchParams: SP }) {
  await exigirAdmin();
  const incluir = { usuario: { select: { nome: true, email: true } }, curso: { select: { titulo: true } } };
  const [pendentes, pagos] = await Promise.all([
    db.pedido.findMany({
      where: { status: "PENDENTE", valorCentavos: { gt: 0 } },
      // Quem já avisou que pagou aparece primeiro.
      orderBy: [{ avisouPagoEm: { sort: "asc", nulls: "last" } }, { criadoEm: "asc" }],
      include: incluir,
    }),
    db.pedido.findMany({
      where: { status: "PAGO", valorCentavos: { gt: 0 } },
      orderBy: { pagoEm: "desc" },
      take: 20,
      include: incluir,
    }),
  ]);
  return (
    <main>
      <p className="pequeno"><Link href="/admin">← Administração</Link></p>
      <h1>Pagamentos por Pix</h1>
      <Aviso sp={await searchParams} />
      <p className="suave">
        Confira no extrato do banco se o Pix chegou (mesmo valor e mesmo identificador) e só então confirme. Confirmar
        libera o curso na hora.
      </p>

      <h2>Aguardando conferência ({pendentes.length})</h2>
      {pendentes.length === 0 ? (
        <p className="suave">Nenhum pedido pendente.</p>
      ) : (
        <div className="cartao tabela-rolagem">
          <table>
            <thead><tr><th>Aluno</th><th>Curso</th><th>Valor</th><th>Identificador no extrato</th><th>Situação</th><th /></tr></thead>
            <tbody>
              {pendentes.map((p) => (
                <tr key={p.id}>
                  <td>{p.usuario.nome}<br /><span className="suave pequeno">{p.usuario.email}</span></td>
                  <td>{p.curso.titulo}</td>
                  <td><strong>{brl(p.valorCentavos)}</strong></td>
                  <td><code>{p.id}</code></td>
                  <td>
                    {p.avisouPagoEm ? (
                      <span className="etiqueta alerta">Avisou que pagou em {data(p.avisouPagoEm)}</span>
                    ) : (
                      <span className="suave pequeno">Gerado em {data(p.criadoEm)}, sem aviso</span>
                    )}
                  </td>
                  <td>
                    <form action={confirmarPagamento} style={{ marginBottom: ".4rem" }}>
                      <input type="hidden" name="pedidoId" value={p.id} />
                      <button className="btn mini">Confirmar recebimento</button>
                    </form>
                    <form action={cancelarPedido}>
                      <input type="hidden" name="pedidoId" value={p.id} />
                      <button className="btn mini perigo">Cancelar</button>
                    </form>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <h2>Últimos pagamentos confirmados</h2>
      {pagos.length === 0 ? (
        <p className="suave">Nenhum pagamento confirmado ainda.</p>
      ) : (
        <div className="cartao tabela-rolagem">
          <table>
            <thead><tr><th>Aluno</th><th>Curso</th><th>Valor</th><th>Pago em</th></tr></thead>
            <tbody>
              {pagos.map((p) => (
                <tr key={p.id}>
                  <td>{p.usuario.nome}</td>
                  <td>{p.curso.titulo}</td>
                  <td>{brl(p.valorCentavos)}</td>
                  <td>{p.pagoEm && data(p.pagoEm)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </main>
  );
}
