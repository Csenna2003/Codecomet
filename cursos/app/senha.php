<?php
declare(strict_types=1);

const JANELA_LOGIN = 15 * 60;
const MAX_FALHAS_EMAIL = 5;
const MAX_FALHAS_IP = 30; // uma escola inteira pode sair pelo mesmo IP

function chaves_login(string $email): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    return ["email:$email", "ip:$ip"];
}

function login_bloqueado(string $email): bool
{
    [$ce, $ci] = chaves_login($email);
    $desde = date('Y-m-d H:i:s', time() - JANELA_LOGIN);
    $porEmail = (int) valor('SELECT COUNT(*) FROM tentativas_login WHERE chave = ? AND criado_em > ?', [$ce, $desde]);
    $porIp = (int) valor('SELECT COUNT(*) FROM tentativas_login WHERE chave = ? AND criado_em > ?', [$ci, $desde]);
    return $porEmail >= MAX_FALHAS_EMAIL || $porIp >= MAX_FALHAS_IP;
}

function registrar_falha_login(string $email): void
{
    [$ce, $ci] = chaves_login($email);
    q('INSERT INTO tentativas_login (chave, criado_em) VALUES (?,?), (?,?)', [$ce, agora(), $ci, agora()]);
    q('DELETE FROM tentativas_login WHERE criado_em < ?', [date('Y-m-d H:i:s', time() - JANELA_LOGIN)]);
}

function limpar_falhas_login(string $email): void
{
    q('DELETE FROM tentativas_login WHERE chave = ?', ["email:$email"]);
}

/** Link de uso único, válido por 24 horas. Só o hash do token fica no banco. */
function criar_link_senha(string $uid, string $criadoPor): string
{
    $token = bin2hex(random_bytes(32));
    q('DELETE FROM redefinicoes WHERE usuario_id = ? AND usado_em IS NULL', [$uid]);
    q(
        'INSERT INTO redefinicoes (token_hash, usuario_id, criado_por, expira_em, criado_em) VALUES (?,?,?,?,?)',
        [hash('sha256', $token), $uid, $criadoPor, date('Y-m-d H:i:s', time() + 86400), agora()]
    );
    return url_absoluta('redefinir', ['token' => $token]);
}

function buscar_redefinicao(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) return null;
    return um(
        'SELECT r.*, u.nome FROM redefinicoes r JOIN usuarios u ON u.id = r.usuario_id
         WHERE r.token_hash = ? AND r.usado_em IS NULL AND r.expira_em > ?',
        [hash('sha256', $token), agora()]
    );
}
