<?php
declare(strict_types=1);

function iniciar_sessao(): void
{
    $pasta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    session_name('cursos_sessao');
    session_set_cookie_params([
        'lifetime' => 30 * 24 * 3600,
        'path' => $pasta,
        'secure' => https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) (30 * 24 * 3600));
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/** Usuário logado com as escolas dele, ou null. */
function usuario_atual(): ?array
{
    static $carregado = false, $u = null;
    if ($carregado) return $u;
    $carregado = true;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return null;
    $u = um('SELECT * FROM usuarios WHERE id = ?', [$id]);
    // Senha trocada em outro lugar derruba esta sessão.
    if (!$u || (int) $u['senha_versao'] !== (int) ($_SESSION['versao'] ?? -1)) {
        $u = null;
        unset($_SESSION['uid'], $_SESSION['versao']);
        return null;
    }
    $u['membros'] = todos(
        'SELECT m.*, o.nome AS org_nome FROM membros m JOIN organizacoes o ON o.id = m.org_id
         WHERE m.usuario_id = ? AND o.ativa = 1 ORDER BY o.nome',
        [$id]
    );
    return $u;
}

function entrar_como(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $usuario['id'];
    $_SESSION['versao'] = (int) $usuario['senha_versao'];
}

function encerrar_sessao(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function exigir_usuario(): array
{
    $u = usuario_atual();
    if (!$u) ir('entrar');
    return $u;
}

function eh_admin(?array $u): bool
{
    return $u !== null && (int) $u['admin'] === 1;
}

function exigir_admin(): array
{
    $u = exigir_usuario();
    if (!eh_admin($u)) ir('painel');
    return $u;
}

/** Escolas que o usuário administra (coordenador ou admin da escola): [id => nome]. */
function orgs_geridas(array $u): array
{
    $r = [];
    foreach ($u['membros'] as $m) {
        if (in_array($m['papel'], ['COORDENADOR', 'ADMIN_ORG'], true)) $r[$m['org_id']] = $m['org_nome'];
    }
    return $r;
}

function orgs_professor(array $u): array
{
    $r = [];
    foreach ($u['membros'] as $m) if ($m['papel'] === 'PROFESSOR') $r[$m['org_id']] = $m['org_nome'];
    return $r;
}

function eh_equipe(array $u): bool
{
    if (eh_admin($u)) return true;
    foreach ($u['membros'] as $m) if ($m['papel'] !== 'ALUNO') return true;
    return false;
}

/** Nunca confie no org_id vindo do formulário: confira aqui. */
function exigir_gestao_org(string $orgId): array
{
    $u = exigir_usuario();
    if (!eh_admin($u) && !isset(orgs_geridas($u)[$orgId])) ir('painel');
    return $u;
}
