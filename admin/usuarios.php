<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

$pdo = getPDO();
$tipos = ['aluno', 'professor', 'administrador'];
$filtro = $_GET['tipo'] ?? 'todos';

if (!in_array($filtro, $tipos, true)) {
    $filtro = 'todos';
}

$sql = 'SELECT u.id, u.nome, u.email, u.tipo, t.nome AS turma_nome
        FROM usuarios u
        LEFT JOIN alunos a ON a.usuario_id = u.id
        LEFT JOIN turmas t ON t.id = a.turma_id';
$params = [];

if ($filtro !== 'todos') {
    $sql .= ' WHERE u.tipo = :tipo';
    $params['tipo'] = $filtro;
}

$sql .= ' ORDER BY u.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$tituloPagina = 'Usuários';
$menuAtivo = 'usuarios';
$acaoTopo = '<a class="btn btn-stamp" href="' . BASE_URL . '/admin/adicionar_usuario.php">Novo usuário</a>';

require __DIR__ . '/../includes/header.php';

$base = BASE_URL . '/admin/usuarios.php';
?>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-ok" role="status">Usuário salvo com sucesso.</div>
<?php endif; ?>

<?php if (!empty($_GET['excluido'])): ?>
    <div class="alert alert-ok" role="status">Usuário excluído.</div>
<?php endif; ?>

<nav class="filter-bar" aria-label="Filtrar usuários">
    <a class="<?= $filtro === 'todos' ? 'is-active' : '' ?>" href="<?= $base ?>">Todos</a>
    <a class="<?= $filtro === 'aluno' ? 'is-active' : '' ?>" href="<?= $base ?>?tipo=aluno">Alunos</a>
    <a class="<?= $filtro === 'professor' ? 'is-active' : '' ?>" href="<?= $base ?>?tipo=professor">Professores</a>
    <a class="<?= $filtro === 'administrador' ? 'is-active' : '' ?>" href="<?= $base ?>?tipo=administrador">Administradores</a>
</nav>

<section class="panel">
    <?php if (!$usuarios): ?>
        <div class="empty-state">
            <p>Nenhum usuário neste filtro.</p>
            <a class="btn btn-stamp empty-action" href="<?= BASE_URL ?>/admin/adicionar_usuario.php">Novo usuário</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Turma</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td data-label="Nome"><?= e($usuario['nome']) ?></td>
                            <td data-label="E-mail"><?= e($usuario['email']) ?></td>
                            <td data-label="Perfil"><span class="chip chip-<?= e($usuario['tipo']) ?>"><?= e(rotuloPerfil($usuario['tipo'])) ?></span></td>
                            <td data-label="Turma"><?= $usuario['tipo'] === 'aluno' ? e($usuario['turma_nome'] ?: '—') : '—' ?></td>
                            <td data-label="Ações">
                                <div class="row-actions">
                                    <a class="btn btn-ghost" href="<?= BASE_URL ?>/admin/editar_usuario.php?id=<?= (int) $usuario['id'] ?>">Editar</a>
                                    <?php if ((int) $usuario['id'] !== (int) $_SESSION['usuario_id']): ?>
                                        <form method="post" action="<?= BASE_URL ?>/admin/excluir_usuario.php" data-confirm="Excluir este usuário?">
                                            <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                                            <button class="btn btn-danger" type="submit">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
