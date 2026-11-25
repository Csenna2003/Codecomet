<?php
declare(strict_types=1);

/** CRC-16/CCITT-FALSE, exigido pelo padrão do Pix (BR Code). */
function pix_crc16(string $texto): string
{
    $crc = 0xFFFF;
    $n = strlen($texto);
    for ($i = 0; $i < $n; $i++) {
        $crc ^= ord($texto[$i]) << 8;
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function pix_campo(string $id, string $valor): string
{
    return $id . str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
}

// O padrão só garante letras sem acento, números e espaço nos campos de nome e cidade.
function pix_limpar(string $s, int $max): string
{
    $s = preg_replace('/[^A-Za-z0-9 ]/', '', sem_acentos($s));
    return substr(strtoupper(trim($s)), 0, $max);
}

function config_pix(): ?array
{
    $p = config()['pix'] ?? [];
    $chave = trim((string) ($p['chave'] ?? ''));
    $nome = pix_limpar((string) ($p['nome'] ?? ''), 25);
    $cidade = pix_limpar((string) ($p['cidade'] ?? ''), 15);
    return ($chave && $nome && $cidade) ? ['chave' => $chave, 'nome' => $nome, 'cidade' => $cidade] : null;
}

/**
 * "Pix copia e cola" de uma cobrança com valor fixo. O identificador (txid) aparece
 * no extrato de quem recebe e liga o Pix ao pedido.
 */
function pix_copia_e_cola(array $cfg, int $centavos, string $txid): string
{
    $txid = substr(preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25);
    $s = pix_campo('00', '01')
        . pix_campo('26', pix_campo('00', 'br.gov.bcb.pix') . pix_campo('01', $cfg['chave']))
        . pix_campo('52', '0000')
        . pix_campo('53', '986')
        . pix_campo('54', number_format($centavos / 100, 2, '.', ''))
        . pix_campo('58', 'BR')
        . pix_campo('59', pix_limpar($cfg['nome'], 25))
        . pix_campo('60', pix_limpar($cfg['cidade'], 15))
        . pix_campo('62', pix_campo('05', $txid))
        . '6304';
    return $s . pix_crc16($s);
}
