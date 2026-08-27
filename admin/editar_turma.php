<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

$pdo = getPDO();
$turmaId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$turma = obterTurma($pdo, $turmaId);

if (!$turma) {
    redirecionar('/admin/turmas.php');
}

$erros = [];
$nome = $turma['nome'];
$ano = $turma['ano'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $ano = trim($_POST['ano'] ?? '');

    if ($nome === '') {
        $erros[] = 'Informe o nome da turma.';
    }

    if (!$erros) {
        $stmt = $pdo->prepare('UPDATE turmas SET nome = :nome, ano = :ano WHERE id = :id');
        $stmt->execute([
            'nome' => $nome,
            'ano'  => $ano !== '' ? $ano : null,
            'id'   => $turmaId,
        ]);
        redirecionar('/admin/turma.php?id=' . $turmaId . '&ok=editada');
    }
}

$tituloPagina = 'Editar turma';
$menuAtivo = 'turmas';

require __DIR__ . '/../includes/header.php';
?>

<p class="back-link"><a href="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>"><?= e($turma['nome']) ?></a></p>

<section class="panel">
    <h2>Editar turma</h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= BASE_URL ?>/admin/editar_turma.php?id=<?= $turmaId ?>">
        <input type="hidden" name="id" value="<?= $turmaId ?>">
        <div class="form-grid">
            <div class="field">
                <label for="nome">Nome</label>
                <input id="nome" name="nome" type="text" maxlength="100" value="<?= e($nome) ?>" required>
            </div>
            <div class="field">
                <label for="ano">Ano</label>
                <input id="ano" name="ano" type="text" maxlength="20" value="<?= e($ano) ?>">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-stamp" type="submit">Salvar alterações</button>
            <a class="btn btn-ghost" href="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>">Cancelar</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
