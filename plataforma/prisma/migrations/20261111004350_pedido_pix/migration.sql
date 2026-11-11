-- AlterTable
ALTER TABLE "Pedido" ADD COLUMN     "avisouPagoEm" TIMESTAMP(3);

-- CreateIndex
CREATE INDEX "Pedido_status_idx" ON "Pedido"("status");
