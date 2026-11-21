<?php

require_once __DIR__ . '/../config/database.php';

const SESSAO_ADMIN = 'ce_admin';
const SESSAO_USER = 'ce_user';

function nomeSessaoPorTipo(string $tipo): string
{
    return $tipo === 'administrador' ? SESSAO_ADMIN : SESSAO_USER;
}

function iniciarSessaoNome(string $nome): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() === $nome) {
            return;
        }
        session_write_close();
    }

    session_name($nome);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function usuarioLogado(): bool
{
    return session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['usuario_id']);
}

function usuarioAtual(): ?array
{
    if (!usuarioLogado()) {
        return null;
    }

    return [
        'id'    => (int) $_SESSION['usuario_id'],
        'nome'  => $_SESSION['usuario_nome'],
        'email' => $_SESSION['usuario_email'],
        'tipo'  => $_SESSION['usuario_tipo'],
    ];
}

function lerSessaoNome(string $nome): ?array
{
    iniciarSessaoNome($nome);
    $dados = usuarioAtual();
    session_write_close();

    return $dados;
}

function sessoesAtivas(): array
{
    $ativas = [];

    foreach ([SESSAO_ADMIN, SESSAO_USER] as $nome) {
        $dados = lerSessaoNome($nome);
        if ($dados) {
            $ativas[] = $dados;
        }
    }

    return $ativas;
}

function redirecionar(string $caminho): void
{
    header('Location: ' . BASE_URL . $caminho);
    exit;
}

function destinoPorTipo(string $tipo): string
{
    return match ($tipo) {
        'professor'      => '/professor/dashboard.php',
        'administrador'  => '/admin/dashboard.php',
        default          => '/aluno/dashboard.php',
    };
}

function iniciarSessaoDoPedido(): void
{
    $origem = ($_SERVER['HTTP_REFERER'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '');

    if (str_contains($origem, '/admin/')) {
        iniciarSessaoNome(SESSAO_ADMIN);
        if (usuarioLogado()) {
            return;
        }
    }

    if (str_contains($origem, '/professor/') || str_contains($origem, '/aluno/')) {
        iniciarSessaoNome(SESSAO_USER);
        if (usuarioLogado()) {
            return;
        }
    }

    iniciarSessaoNome(SESSAO_USER);
    if (usuarioLogado()) {
        return;
    }

    iniciarSessaoNome(SESSAO_ADMIN);
}

function exigirLogin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciarSessaoDoPedido();
    }

    if (!usuarioLogado()) {
        redirecionar('/login.php');
    }
}

/**
 * Representante de turma: conta de professor (várias turmas) ou aluno marcado como representante,
 * com uma única turma e os mesmos poderes de controle da turma.
 */
function ehRepresentantePorUsuario(PDO $pdo, int $usuarioId, string $tipo): bool
{
    if ($tipo === 'professor') {
        return true;
    }

    if ($tipo !== 'aluno') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM alunos a
         INNER JOIN professores p ON p.usuario_id = a.usuario_id
         WHERE a.usuario_id = :usuario_id AND a.representante = 1
         LIMIT 1'
    );
    $stmt->execute(['usuario_id' => $usuarioId]);

    return (bool) $stmt->fetchColumn();
}

function usuarioEhRepresentante(): bool
{
    if (!usuarioLogado()) {
        return false;
    }

    return ehRepresentantePorUsuario(getPDO(), (int) $_SESSION['usuario_id'], (string) $_SESSION['usuario_tipo']);
}

function exigirProfessor(): void
{
    iniciarSessaoNome(SESSAO_USER);

    if (!usuarioLogado()) {
        redirecionar('/login.php');
    }

    if (!usuarioEhRepresentante()) {
        redirecionar(destinoPorTipo($_SESSION['usuario_tipo']));
    }
}

function exigirAluno(): void
{
    iniciarSessaoNome(SESSAO_USER);

    if (!usuarioLogado()) {
        redirecionar('/login.php');
    }

    if ($_SESSION['usuario_tipo'] !== 'aluno') {
        redirecionar(destinoPorTipo($_SESSION['usuario_tipo']));
    }
}

function exigirEscola(): void
{
    iniciarSessaoNome(SESSAO_USER);

    if (!usuarioLogado()) {
        redirecionar('/login.php');
    }

    if (!in_array($_SESSION['usuario_tipo'], ['aluno', 'professor'], true)) {
        redirecionar(destinoPorTipo($_SESSION['usuario_tipo']));
    }
}

function exigirAdministrador(): void
{
    iniciarSessaoNome(SESSAO_ADMIN);

    if (!usuarioLogado()) {
        redirecionar('/login.php');
    }

    if ($_SESSION['usuario_tipo'] !== 'administrador') {
        redirecionar(destinoPorTipo($_SESSION['usuario_tipo']));
    }
}

function iniciarSessaoUsuario(array $usuario): void
{
    iniciarSessaoNome(nomeSessaoPorTipo($usuario['tipo']));
    session_regenerate_id(true);

    $_SESSION['usuario_id']    = (int) $usuario['id'];
    $_SESSION['usuario_nome']  = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_tipo']  = $usuario['tipo'];
}

function encerrarSessao(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
