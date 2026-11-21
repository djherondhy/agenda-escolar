<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

$pdo = getPDO();
$erros = [];
$nome = '';
$ano = date('Y');
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['popular'])) {
        $inseridas = popularTurmasPadrao($pdo);
        $mensagem = $inseridas > 0
            ? $inseridas . ' turma(s) padrão adicionada(s).'
            : 'As turmas padrão já estavam cadastradas.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $ano = trim($_POST['ano'] ?? '');

        if ($nome === '') {
            $erros[] = 'Informe o nome da turma.';
        }

        if (!$erros) {
            $stmt = $pdo->prepare('INSERT INTO turmas (nome, ano) VALUES (:nome, :ano)');
            $stmt->execute([
                'nome' => $nome,
                'ano'  => $ano !== '' ? $ano : null,
            ]);
            redirecionar('/admin/turmas.php?ok=1');
        }
    }
}

$sql = 'SELECT t.id, t.nome, t.ano,
               (SELECT COUNT(*) FROM alunos a WHERE a.turma_id = t.id) AS total_alunos,
               (SELECT COUNT(DISTINCT pt.professor_id) FROM professor_turmas pt WHERE pt.turma_id = t.id) AS total_professores
        FROM turmas t
        ORDER BY t.nome';
$turmas = $pdo->query($sql)->fetchAll();

$tituloPagina = 'Turmas';
$menuAtivo = 'turmas';

require __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-ok" role="status">Turma cadastrada.</div>
<?php endif; ?>

<?php if (!empty($_GET['excluido'])): ?>
    <div class="alert alert-ok" role="status">Turma excluída.</div>
<?php endif; ?>

<?php if ($mensagem !== ''): ?>
    <div class="alert alert-ok" role="status"><?= e($mensagem) ?></div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <h2>Turmas padrão</h2>
    </div>
    <p class="panel-lead">Adiciona, se ainda não existirem, 1º, 2º e 3º anos de Informática, Administração e Agropecuária.</p>
    <form method="post" action="<?= BASE_URL ?>/admin/turmas.php">
        <input type="hidden" name="popular" value="1">
        <button class="btn btn-stamp empty-action" type="submit">Popular turmas padrão</button>
    </form>
</section>

<section class="panel">
    <h2>Nova turma</h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= BASE_URL ?>/admin/turmas.php">
        <div class="form-grid">
            <div class="field">
                <label for="nome">Nome</label>
                <input id="nome" name="nome" type="text" maxlength="100" value="<?= e($nome) ?>" required placeholder="Ex.: 1º Ano Informática">
            </div>
            <div class="field">
                <label for="ano">Ano</label>
                <input id="ano" name="ano" type="text" maxlength="20" value="<?= e($ano) ?>">
            </div>
        </div>
        <button class="btn btn-stamp" type="submit">Salvar turma</button>
    </form>
</section>

<section class="panel">
    <h2>Turmas cadastradas</h2>

    <?php if (!$turmas): ?>
        <p class="empty">Nenhuma turma cadastrada ainda.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Turma</th>
                        <th>Ano</th>
                        <th>Alunos</th>
                        <th>Representantes</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($turmas as $turma): ?>
                        <tr>
                            <td data-label="Turma">
                                <a href="<?= BASE_URL ?>/admin/turma.php?id=<?= (int) $turma['id'] ?>"><?= e($turma['nome']) ?></a>
                            </td>
                            <td data-label="Ano"><?= e($turma['ano'] ?: '—') ?></td>
                            <td data-label="Alunos"><?= (int) $turma['total_alunos'] ?></td>
                            <td data-label="Representantes"><?= (int) $turma['total_professores'] ?></td>
                            <td data-label="Ações">
                                <div class="row-actions">
                                    <a class="btn btn-ghost" href="<?= BASE_URL ?>/admin/turma.php?id=<?= (int) $turma['id'] ?>">Abrir</a>
                                    <a class="btn btn-ghost" href="<?= BASE_URL ?>/admin/editar_turma.php?id=<?= (int) $turma['id'] ?>">Editar</a>
                                    <?php if ((int) $turma['total_alunos'] === 0): ?>
                                        <form method="post" action="<?= BASE_URL ?>/admin/excluir_turma.php" data-confirm="Excluir esta turma e as atividades dela?">
                                            <input type="hidden" name="id" value="<?= (int) $turma['id'] ?>">
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
