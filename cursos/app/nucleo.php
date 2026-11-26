<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

define('RAIZ', dirname(__DIR__));

function config(): array
{
    static $c = null;
    if ($c === null) {
        $arquivo = RAIZ . '/config.php';
        if (!is_file($arquivo)) {
            header('Location: instalar.php');
            exit;
        }
        $c = require $arquivo;
    }
    return $c;
}

function nome_site(): string
{
    return (string) (config()['nome_site'] ?? 'Cursos');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'];
        $pdo = conectar_banco($c['host'], $c['nome'], $c['usuario'], $c['senha']);
    }
    return $pdo;
}

function conectar_banco(string $host, string $nome, string $usuario, string $senha): PDO
{
    return new PDO("mysql:host=$host;dbname=$nome;charset=utf8mb4", $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

// ---------- Consultas ----------

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}

function um(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r === false ? null : $r;
}

function todos(string $sql, array $p = []): array
{
    return q($sql, $p)->fetchAll();
}

function valor(string $sql, array $p = [])
{
    return q($sql, $p)->fetchColumn();
}

/** Gera "?, ?, ?" para usar com IN (...). */
function marcas(array $itens): string
{
    return implode(',', array_fill(0, max(1, count($itens)), '?'));
}

function transacao(callable $f)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $f();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ---------- Utilidades ----------

function novo_id(): string
{
    $alfabeto = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $s = '';
    for ($i = 0; $i < 20; $i++) $s .= $alfabeto[random_int(0, 35)];
    return $s;
}

/** Código fácil de digitar: sem 0/O/1/I. */
function codigo_aleatorio(int $n): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    return $s;
}

function agora(): string
{
    return date('Y-m-d H:i:s');
}

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function brl(int $centavos): string
{
    return $centavos === 0 ? 'Gratuito' : 'R$ ' . number_format($centavos / 100, 2, ',', '.');
}

/** Sempre em reais, mesmo quando zero (para valores a pagar e mensalidades). */
function reais(int $centavos): string
{
    return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
}

function data_br(?string $d): string
{
    return $d ? date('d/m/Y', strtotime($d)) : '';
}

function sem_acentos(string $s): string
{
    $mapa = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A', 'É' => 'E', 'Ê' => 'E', 'È' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C', 'Ñ' => 'N',
    ];
    return strtr($s, $mapa);
}

function slugify(string $s): string
{
    $s = strtolower(sem_acentos($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

const ROTULO_NIVEL = ['INICIANTE' => 'Iniciante', 'INTERMEDIARIO' => 'Intermediário', 'AVANCADO' => 'Avançado'];
const ROTULO_TIPO_AULA = ['VIDEO' => 'Vídeo', 'TEXTO' => 'Leitura', 'QUIZ' => 'Quiz', 'SIMULADOR' => 'Simulador', 'EXERCICIO' => 'Exercício'];
const ROTULO_PAPEL = ['ALUNO' => 'Aluno', 'PROFESSOR' => 'Professor', 'COORDENADOR' => 'Coordenador', 'ADMIN_ORG' => 'Administrador da escola'];
const ROTULO_ENTREGA = ['ENVIADA' => 'Aguardando correção', 'APROVADA' => 'Aprovada', 'PRECISA_AJUSTE' => 'Precisa de ajuste'];
const ROTULO_TIPO_ENTREGA = ['LINK_SIMULADOR' => 'Link do Tinkercad/Wokwi', 'LINK_VIDEO' => 'Link do projeto ou de vídeo, ou fotos', 'ARQUIVO' => 'Fotos ou arquivos do projeto', 'CODIGO' => 'Código'];
const ROTULO_STATUS_CURSO = ['RASCUNHO' => 'Rascunho', 'PUBLICADO' => 'Publicado', 'ARQUIVADO' => 'Arquivado'];

// ---------- Requisição ----------

function post(string $k): string
{
    return trim((string) ($_POST[$k] ?? ''));
}

function get(string $k): string
{
    return trim((string) ($_GET[$k] ?? ''));
}

function url(string $rota = 'inicio', array $p = []): string
{
    $q = http_build_query(['r' => $rota] + $p);
    return 'index.php?' . $q;
}

function ir(string $rota = 'inicio', array $p = []): void
{
    header('Location: ' . url($rota, $p));
    exit;
}

function flash(string $tipo, string $msg): void
{
    $_SESSION['flash'] = [$tipo, $msg];
}

/** Grava a mensagem e redireciona. */
function voltar(string $tipo, string $msg, string $rota, array $p = []): void
{
    flash($tipo, $msg);
    ir($rota, $p);
}

function mostrar_flash(): string
{
    if (empty($_SESSION['flash'])) return '';
    [$tipo, $msg] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $papel = $tipo === 'erro' ? 'alert' : 'status';
    return '<p class="aviso ' . e($tipo) . '" role="' . $papel . '">' . e($msg) . '</p>';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verificar_csrf(): void
{
    $enviado = (string) ($_POST['csrf'] ?? '');
    if (!$enviado || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
        http_response_code(400);
        exit('Formulário expirado. Volte, recarregue a página e tente de novo.');
    }
}

function https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** Endereço completo da pasta da plataforma, para links enviados a usuários. */
function url_absoluta(string $rota, array $p = []): string
{
    return url_base() . url($rota, $p);
}

/** Endereço completo da pasta da plataforma, terminado em "/". */
function url_base(): string
{
    $pasta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (https() ? 'https' : 'http') . '://' . $host . $pasta . '/';
}

function auditar(?string $ator, ?string $org, string $acao, string $tipo, string $alvo, array $dados = []): void
{
    q(
        'INSERT INTO auditoria (ator_id, org_id, acao, alvo_tipo, alvo_id, dados, criado_em) VALUES (?,?,?,?,?,?,?)',
        [$ator, $org, $acao, $tipo, $alvo, $dados ? json_encode($dados, JSON_UNESCAPED_UNICODE) : null, agora()]
    );
}

function nao_encontrado(): void
{
    http_response_code(404);
    cabecalho('Página não encontrada');
    echo '<h1>Página não encontrada</h1><p><a href="' . e(url()) . '">Voltar ao início</a></p>';
    rodape();
    exit;
}
