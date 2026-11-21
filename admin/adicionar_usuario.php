<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

$pdo = getPDO();
$turmas = listarTurmas($pdo);
$tipos = ['aluno', 'professor', 'administrador'];
$erros = [];
$dados = [
    'nome'      => '',
    'email'     => '',
    'tipo'      => in_array($_GET['tipo'] ?? '', $tipos, true) ? $_GET['tipo'] : 'aluno',
    'turma_id'        => '',
    'turma_ids'       => [],
    'turma_materias'  => [],
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

    if ($dados['nome'] === '') {
        $erros[] = 'Informe o nome.';
    }

    if ($dados['email'] === '' || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    } elseif (emailEmUso($pdo, $dados['email'])) {
        $erros[] = 'Este e-mail já está cadastrado.';
    }

    if (!in_array($dados['tipo'], $tipos, true)) {
        $erros[] = 'Selecione um perfil válido.';
    }

    if (strlen($senha) < 5) {
        $erros[] = 'A senha deve ter pelo menos 5 caracteres.';
    }

    if ($dados['tipo'] === 'aluno' && !in_array($dados['turma_id'], $idsTurmas, true)) {
        $erros[] = 'Selecione a turma do aluno.';
    }

    if ($dados['tipo'] === 'professor') {
        $dados['turma_ids'] = array_values(array_intersect($dados['turma_ids'], $idsTurmas));
        if (!$dados['turma_ids']) {
            $erros[] = 'Selecione pelo menos uma turma para o representante.';
        }
    }

    if (!$erros) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nome, email, senha, tipo)
                 VALUES (:nome, :email, :senha, :tipo)'
            );
            $stmt->execute([
                'nome'  => $dados['nome'],
                'email' => $dados['email'],
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
                'tipo'  => $dados['tipo'],
            ]);

            sincronizarPerfilUsuario($pdo, (int) $pdo->lastInsertId(), $dados['tipo'], $dados);
            $pdo->commit();
            redirecionar('/admin/usuarios.php?ok=1');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível salvar. Tente novamente.';
        }
    }
}

$tituloPagina = 'Novo usuário';
$menuAtivo = 'usuarios';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <h2>Cadastrar usuário</h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= BASE_URL ?>/admin/adicionar_usuario.php" data-tipo-form>
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
                    <option value="professor" <?= $dados['tipo'] === 'professor' ? 'selected' : '' ?>>Representante de turma</option>
                    <option value="administrador" <?= $dados['tipo'] === 'administrador' ? 'selected' : '' ?>>Desenvolvedor / Conselheiro</option>
                </select>
                <small class="field-help">Aluno usa uma turma. O representante pode ter várias.</small>
            </div>

            <div class="field">
                <label for="senha">Senha</label>
                <input id="senha" name="senha" type="password" required autocomplete="new-password">
            </div>

            <?php require __DIR__ . '/../includes/usuario_turmas_fields.php'; ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-stamp" type="submit">Salvar usuário</button>
            <a class="btn btn-ghost" href="<?= BASE_URL ?>/admin/usuarios.php">Cancelar</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
