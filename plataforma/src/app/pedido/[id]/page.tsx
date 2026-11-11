import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { BotaoCopiar } from "@/componentes/BotaoCopiar";
import { exigirUsuario } from "@/lib/auth";
import { db } from "@/lib/db";
import { brl } from "@/lib/fmt";
import { configPix, pixCopiaECola, qrCodeSvg } from "@/lib/pix";
import { avisarPagamento, simularPagamento } from "../../acoes/aluno";

export const dynamic = "force-dynamic";

export default async function Pedido({ params }: { params: Promise<{ id: string }> }) {
  const u = await exigirUsuario();
  const pedido = await db.pedido.findUnique({ where: { id: (await params).id }, include: { curso: true } });
  if (!pedido || pedido.usuarioId !== u.id) notFound();
  if (pedido.status === "PAGO") redirect(`/cursos/${pedido.curso.slug}`);

  const simulado = process.env.PAGAMENTO_MODO === "simulado";
  const pix = configPix();
  const codigo = pix && pixCopiaECola({ ...pix, valorCentavos: pedido.valorCentavos, txid: pedido.id });

  return (
    <main className="estreito">
      <h1>Pagamento por Pix</h1>
      <div className="cartao">
        <p><strong>{pedido.curso.titulo}</strong></p>
        <p>Valor: <strong>{brl(pedido.valorCentavos)}</strong></p>

        {pedido.status === "CANCELADO" ? (
          <p className="aviso erro">Este pedido foi cancelado. Volte ao curso para gerar um novo.</p>
        ) : simulado ? (
          <form action={simularPagamento}>
            <input type="hidden" name="pedidoId" value={pedido.id} />
            <p className="aviso alerta">Ambiente de testes: nenhum pagamento real é cobrado.</p>
            <button className="btn">Simular pagamento aprovado</button>
          </form>
        ) : !codigo ? (
          <p className="aviso erro">O pagamento está temporariamente indisponível. Fale com a equipe Codecomet.</p>
        ) : pedido.avisouPagoEm ? (
          <p className="aviso ok">
            Recebemos o seu aviso! Assim que o Pix for conferido, o curso aparece em “Meu painel”. Isso costuma levar
            até 1 dia útil.
          </p>
        ) : (
          <>
            <ol>
              <li>Abra o aplicativo do seu banco e escolha <strong>Pix → Ler QR Code</strong> ou <strong>Pix copia e cola</strong>.</li>
              <li>Confira o valor e o nome <strong>{pix!.nome}</strong> antes de confirmar.</li>
              <li>Depois de pagar, clique em <strong>Já paguei</strong>.</li>
            </ol>
            <div className="qr" dangerouslySetInnerHTML={{ __html: await qrCodeSvg(codigo) }} />
            <label>Pix copia e cola
              <textarea className="codigo" readOnly rows={6} value={codigo} />
            </label>
            <div className="linha" style={{ marginTop: ".75rem" }}>
              <BotaoCopiar texto={codigo} />
              <form action={avisarPagamento}>
                <input type="hidden" name="pedidoId" value={pedido.id} />
                <button className="btn">Já paguei</button>
              </form>
            </div>
          </>
        )}
        <p className="suave pequeno" style={{ marginTop: "1rem" }}>Pedido {pedido.id}</p>
      </div>
      <Link href={`/cursos/${pedido.curso.slug}`}>Voltar ao curso</Link>
    </main>
  );
}
