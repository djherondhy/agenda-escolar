<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

$pdo = getPDO();

$totalAdmins = contarAdministradores($pdo);
$totalProfessores = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'professor'")->fetchColumn();
$totalAlunos = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno'")->fetchColumn();
$totalTurmas = (int) $pdo->query('SELECT COUNT(*) FROM turmas')->fetchColumn();

$recentes = $pdo->query(
    'SELECT id, nome, email, tipo, data_criacao
     FROM usuarios
     ORDER BY data_criacao DESC, id DESC
     LIMIT 6'
)->fetchAll();

$tituloPagina = dataPorExtenso();
$menuAtivo = 'dashboard';
$acaoTopo = '<a class="btn btn-stamp" href="' . BASE_URL . '/admin/adicionar_usuario.php">Novo usuário</a>';

require __DIR__ . '/../includes/header.php';
?>

<section class="hello">
    <h2 class="display-name">Administração</h2>
    <p>Cadastre alunos, representantes de turma e conselheiros da escola.</p>
</section>

<section class="stat-row stat-row-4">
    <a class="stat" href="<?= BASE_URL ?>/admin/usuarios.php?tipo=aluno">
        <span class="stat-value"><?= $totalAlunos ?></span>
        <span class="stat-label">Alunos</span>
    </a>
    <a class="stat" href="<?= BASE_URL ?>/admin/usuarios.php?tipo=professor">
        <span class="stat-value"><?= $totalProfessores ?></span>
        <span class="stat-label">Representantes</span>
    </a>
    <a class="stat" href="<?= BASE_URL ?>/admin/usuarios.php?tipo=administrador">
        <span class="stat-value"><?= $totalAdmins ?></span>
        <span class="stat-label">Administradores</span>
    </a>
    <a class="stat" href="<?= BASE_URL ?>/admin/turmas.php">
        <span class="stat-value"><?= $totalTurmas ?></span>
        <span class="stat-label">Turmas</span>
    </a>
</section>

<nav class="dash-shortcuts" aria-label="Cadastros">
    <a href="<?= BASE_URL ?>/admin/adicionar_usuario.php?tipo=aluno">Cadastrar aluno</a>
    <a href="<?= BASE_URL ?>/admin/adicionar_usuario.php?tipo=professor">Cadastrar representante</a>
    <a href="<?= BASE_URL ?>/admin/adicionar_usuario.php?tipo=administrador">Cadastrar administrador</a>
</nav>

<section class="panel">
    <div class="panel-head">
        <h2>Últimos cadastros</h2>
        <a href="<?= BASE_URL ?>/admin/usuarios.php">Ver todos</a>
    </div>

    <?php if (!$recentes): ?>
        <div class="empty-state">
            <p>Nenhum usuário cadastrado ainda.</p>
            <a class="btn btn-stamp empty-action" href="<?= BASE_URL ?>/admin/adicionar_usuario.php">Novo usuário</a>
        </div>
    <?php endif; ?>

    <?php foreach ($recentes as $usuario): ?>
        <a class="agenda-item" href="<?= BASE_URL ?>/admin/editar_usuario.php?id=<?= (int) $usuario['id'] ?>">
            <span class="agenda-mark mark-<?= $usuario['tipo'] === 'aluno' ? 'tarefa' : ($usuario['tipo'] === 'professor' ? 'trabalho' : 'outro') ?>"></span>
            <div>
                <div class="agenda-title"><?= e($usuario['nome']) ?></div>
                <p class="agenda-meta"><?= e($usuario['email']) ?></p>
            </div>
            <span class="chip chip-<?= e($usuario['tipo']) ?>"><?= e(rotuloPerfil($usuario['tipo'])) ?></span>
        </a>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
