import { timingSafeEqual } from "crypto";
import { confirmarPedido } from "@/lib/pedidos";

/**
 * Ponto de entrada para o gateway de pagamento confirmar um pedido.
 * Corpo: { "pedidoId": "...", "status": "PAGO", "gatewayRef": "..." }
 * Cabeçalho: x-webhook-secret = WEBHOOK_SECRET
 * Ao contratar o gateway, adapte aqui a validação de assinatura e o formato dele.
 */
export async function POST(req: Request) {
  const segredo = process.env.WEBHOOK_SECRET ?? "";
  const recebido = req.headers.get("x-webhook-secret") ?? "";
  const a = Buffer.from(segredo);
  const b = Buffer.from(recebido);
  if (!segredo || a.length !== b.length || !timingSafeEqual(a, b))
    return Response.json({ erro: "não autorizado" }, { status: 401 });

  const corpo = await req.json().catch(() => null);
  if (!corpo || typeof corpo.pedidoId !== "string") return Response.json({ erro: "corpo inválido" }, { status: 400 });
  if (corpo.status !== "PAGO") return Response.json({ ok: true, ignorado: true });

  const pedido = await confirmarPedido(corpo.pedidoId, typeof corpo.gatewayRef === "string" ? corpo.gatewayRef : undefined);
  if (!pedido) return Response.json({ erro: "pedido não encontrado" }, { status: 404 });
  return Response.json({ ok: true });
}
