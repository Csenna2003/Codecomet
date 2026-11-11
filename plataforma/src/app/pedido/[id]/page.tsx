import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { exigirUsuario } from "@/lib/auth";
import { db } from "@/lib/db";
import { brl } from "@/lib/fmt";
import { simularPagamento } from "../../acoes/aluno";

export default async function Pedido({ params }: { params: Promise<{ id: string }> }) {
  const u = await exigirUsuario();
  const pedido = await db.pedido.findUnique({ where: { id: (await params).id }, include: { curso: true } });
  if (!pedido || pedido.usuarioId !== u.id) notFound();
  if (pedido.status === "PAGO") redirect(`/cursos/${pedido.curso.slug}`);
  const simulado = process.env.PAGAMENTO_MODO === "simulado";
  return (
    <main className="estreito">
      <h1>Pagamento</h1>
      <div className="cartao">
        <p><strong>{pedido.curso.titulo}</strong></p>
        <p>Valor: <strong>{brl(pedido.valorCentavos)}</strong></p>
        <p className="suave pequeno">Pedido {pedido.id}</p>
        {simulado ? (
          <form action={simularPagamento}>
            <input type="hidden" name="pedidoId" value={pedido.id} />
            <p className="aviso alerta">Ambiente de testes: nenhum pagamento real é cobrado.</p>
            <button className="btn">Simular pagamento aprovado</button>
          </form>
        ) : (
          <p className="aviso alerta">
            Seu pedido foi registrado e o acesso é liberado automaticamente assim que o pagamento for confirmado.
          </p>
        )}
      </div>
      <Link href={`/cursos/${pedido.curso.slug}`}>Voltar ao curso</Link>
    </main>
  );
}
