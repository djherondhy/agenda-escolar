<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

$pdo = getPDO();
$usuarioId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, nome, email, tipo FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $usuarioId]);
$usuario = $stmt->fetch();

if (!$usuario) {
    redirecionar('/admin/usuarios.php');
}

$turmas = listarTurmas($pdo);
$tipos = ['aluno', 'professor', 'administrador'];
$erros = [];

$aluno = obterAlunoPorUsuario($pdo, $usuarioId);
$professor = obterProfessorPorUsuario($pdo, $usuarioId);
$turmasProfessor = $professor ? listarTurmasDoProfessor($pdo, (int) $professor['id']) : [];

$dados = [
    'nome'           => $usuario['nome'],
    'email'          => $usuario['email'],
    'tipo'           => $usuario['tipo'],
    'turma_id'       => $aluno['turma_id'] ?? '',
    'turma_ids'      => array_map(static fn ($turma) => (int) $turma['id'], $turmasProfessor),
    'turma_materias' => $professor ? materiasDoProfessorPorTurma($pdo, (int) $professor['id']) : [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados['nome'] = trim($_POST['nome'] ?? '');
    $dados['email'] = trim($_POST['email'] ?? '');
    $dados['tipo'] = $_POST['tipo'] ?? '';
    $dados['turma_id'] = (int) ($_POST['turma_id'] ?? 0);
    $dados['turma_ids'] = array_map('intval', $_POST['turma_ids'] ?? []);
    $dados['turma_materias'] = $_POST['turma_materias'] ?? [];
    $senha = $_POST['senha'] ?? '';

    $idsTurmas = array_map(static fn ($turma) => (int) $turma['id'], $turmas);
    $ehProprio = $usuarioId === (int) $_SESSION['usuario_id'];

    if ($dados['nome'] === '') {
        $erros[] = 'Informe o nome.';
    }

    if ($dados['email'] === '' || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    } elseif (emailEmUso($pdo, $dados['email'], $usuarioId)) {
        $erros[] = 'Este e-mail já está cadastrado.';
    }

    if (!in_array($dados['tipo'], $tipos, true)) {
        $erros[] = 'Selecione um perfil válido.';
    }

    if ($senha !== '' && strlen($senha) < 5) {
        $erros[] = 'A nova senha deve ter pelo menos 5 caracteres.';
    }

    if ($ehProprio && $dados['tipo'] !== 'administrador') {
        $erros[] = 'Você não pode remover o próprio acesso de administrador.';
    }

    if ($usuario['tipo'] === 'administrador' && $dados['tipo'] !== 'administrador' && contarAdministradores($pdo) <= 1) {
        $erros[] = 'Mantenha pelo menos um administrador no sistema.';
    }

    if ($dados['tipo'] === 'aluno' && !in_array($dados['turma_id'], $idsTurmas, true)) {
        $erros[] = 'Selecione a turma do aluno.';
    }

    if ($dados['tipo'] === 'professor') {
        $dados['turma_ids'] = array_values(array_intersect($dados['turma_ids'], $idsTurmas));
        if (!$dados['turma_ids']) {
            $erros[] = 'Selecione pelo menos uma turma para o professor.';
        }
    }

    if (!$erros) {
        try {
            $pdo->beginTransaction();

            $sql = 'UPDATE usuarios SET nome = :nome, email = :email, tipo = :tipo';
            $params = [
                'nome'  => $dados['nome'],
                'email' => $dados['email'],
                'tipo'  => $dados['tipo'],
                'id'    => $usuarioId,
            ];

            if ($senha !== '') {
                $sql .= ', senha = :senha';
                $params['senha'] = password_hash($senha, PASSWORD_DEFAULT);
            }

            $sql .= ' WHERE id = :id';
            $update = $pdo->prepare($sql);
            $update->execute($params);

            sincronizarPerfilUsuario($pdo, $usuarioId, $dados['tipo'], $dados);
            $pdo->commit();

            if ($ehProprio) {
                $_SESSION['usuario_nome'] = $dados['nome'];
                $_SESSION['usuario_email'] = $dados['email'];
            }

            redirecionar('/admin/usuarios.php?ok=1');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível salvar. Tente novamente.';
        }
    }
}

$tituloPagina = 'Editar usuário';
$menuAtivo = 'usuarios';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <h2>Editar usuário</h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= BASE_URL ?>/admin/editar_usuario.php?id=<?= $usuarioId ?>" data-tipo-form>
        <input type="hidden" name="id" value="<?= $usuarioId ?>">

        <div class="form-grid">
            <div class="field">
                <label for="nome">Nome</label>
                <input id="nome" name="nome" type="text" maxlength="100" value="<?= e($dados['nome']) ?>" required>
            </div>

            <div class="field">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" maxlength="150" value="<?= e($dados['email']) ?>" required>
            </div>

            <div class="field">
                <label for="tipo">Perfil</label>
                <select id="tipo" name="tipo" data-tipo-select required>
                    <option value="aluno" <?= $dados['tipo'] === 'aluno' ? 'selected' : '' ?>>Aluno</option>
                    <option value="professor" <?= $dados['tipo'] === 'professor' ? 'selected' : '' ?>>Professor</option>
                    <option value="administrador" <?= $dados['tipo'] === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                </select>
                <small class="field-help">Aluno usa uma turma. Professor pode ter várias.</small>
            </div>

            <div class="field">
                <label for="senha">Nova senha</label>
                <input id="senha" name="senha" type="password" autocomplete="new-password">
                <small class="field-help">Deixe em branco para manter a senha atual.</small>
            </div>

            <?php require __DIR__ . '/../includes/usuario_turmas_fields.php'; ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-stamp" type="submit">Salvar alterações</button>
            <a class="btn btn-ghost" href="<?= BASE_URL ?>/admin/usuarios.php">Cancelar</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
