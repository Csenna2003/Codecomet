import QRCode from "qrcode";

/** CRC-16/CCITT-FALSE, exigido pelo padrão do Pix (BR Code). */
export function crc16(texto: string) {
  let crc = 0xffff;
  for (const byte of Buffer.from(texto, "utf8")) {
    crc ^= byte << 8;
    for (let i = 0; i < 8; i++) crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff;
  }
  return crc.toString(16).toUpperCase().padStart(4, "0");
}

const campo = (id: string, valor: string) => id + String(valor.length).padStart(2, "0") + valor;

// O padrão só garante letras sem acento, números e espaço nos campos de nome e cidade.
const limpar = (s: string, max: number) =>
  s
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/[^A-Za-z0-9 ]/g, "")
    .trim()
    .toUpperCase()
    .slice(0, max);

export function configPix() {
  const chave = (process.env.PIX_CHAVE ?? "").trim();
  const nome = limpar(process.env.PIX_NOME ?? "", 25);
  const cidade = limpar(process.env.PIX_CIDADE ?? "", 15);
  return chave && nome && cidade ? { chave, nome, cidade } : null;
}

/**
 * Monta o "Pix copia e cola" de uma cobrança com valor fixo.
 * O identificador (txid) aparece no extrato de quem recebe e liga o Pix ao pedido.
 */
export function pixCopiaECola(o: { chave: string; nome: string; cidade: string; valorCentavos: number; txid: string }) {
  const txid = o.txid.replace(/[^A-Za-z0-9]/g, "").slice(0, 25);
  const semCrc =
    campo("00", "01") +
    campo("26", campo("00", "br.gov.bcb.pix") + campo("01", o.chave)) +
    campo("52", "0000") +
    campo("53", "986") +
    campo("54", (o.valorCentavos / 100).toFixed(2)) +
    campo("58", "BR") +
    campo("59", limpar(o.nome, 25)) +
    campo("60", limpar(o.cidade, 15)) +
    campo("62", campo("05", txid)) +
    "6304";
  return semCrc + crc16(semCrc);
}

export const qrCodeSvg = (texto: string) => QRCode.toString(texto, { type: "svg", margin: 1, errorCorrectionLevel: "M" });
