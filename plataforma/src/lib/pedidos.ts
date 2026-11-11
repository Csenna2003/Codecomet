import { db } from "./db";

/** Marca o pedido como pago e libera o curso. Idempotente: o gateway pode reenviar o webhook. */
export async function confirmarPedido(pedidoId: string, gatewayRef?: string) {
  return db.$transaction(async (tx) => {
    const pedido = await tx.pedido.findUnique({ where: { id: pedidoId } });
    if (!pedido || pedido.status === "CANCELADO") return null;
    if (pedido.status === "PENDENTE") {
      await tx.pedido.update({
        where: { id: pedidoId },
        data: { status: "PAGO", pagoEm: new Date(), gatewayRef },
      });
    }
    const dados = {
      origem: "COMPRA" as const,
      status: "ATIVO" as const,
      pedidoId,
      fim: null,
      orgId: null,
      licencaId: null,
    };
    await tx.acesso.upsert({
      where: { usuarioId_cursoId: { usuarioId: pedido.usuarioId, cursoId: pedido.cursoId } },
      create: { usuarioId: pedido.usuarioId, cursoId: pedido.cursoId, ...dados },
      update: dados,
    });
    return pedido;
  });
}
