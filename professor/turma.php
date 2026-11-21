<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirProfessor();

$pdo = getPDO();
$professor = obterProfessorPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$professor) {
    encerrarSessao();
    redirecionar('/login.php');
}

$turmaId = (int) ($_GET['id'] ?? 0);
$turma = obterTurma($pdo, $turmaId);

if (!$turma || !professorTemTurma($pdo, (int) $professor['id'], $turmaId)) {
    redirecionar('/professor/turmas.php');
}

$errosHorario = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = processarHorarioTurma($pdo, $_POST, $turmaId, (int) $professor['id'], $errosHorario);
    if ($resultado) {
        redirecionar('/professor/turma.php?id=' . $turmaId . '&ok=' . $resultado);
    }
}

$alunos = listarAlunosDaTurma($pdo, $turmaId);
$professores = agruparProfessoresDaTurma(listarVinculosDaTurma($pdo, $turmaId));
$horariosPainel = listarHorariosDaTurma($pdo, $turmaId);
$opcoesHorario = opcoesHorarioDaTurma($professores, (int) $professor['id']);
$actionHorario = BASE_URL . '/professor/turma.php?id=' . $turmaId;

$minhasMaterias = [];
foreach ($professores as $item) {
    if ((int) $item['professor_id'] === (int) $professor['id']) {
        $minhasMaterias = array_map(
            static fn ($materia) => rotuloMateria($materia['materia']),
            $item['materias']
        );
        break;
    }
}

$tituloPagina = $turma['nome'];
$menuAtivo = 'turmas';

require __DIR__ . '/../includes/header.php';
?>

<p class="back-link"><a href="<?= BASE_URL ?>/professor/turmas.php">Turmas</a></p>

<?php if (($_GET['ok'] ?? '') === 'horario_ok'): ?>
    <div class="alert alert-ok" role="status">Horário adicionado. Os alunos já veem na grade da turma.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_removido'): ?>
    <div class="alert alert-ok" role="status">Horário removido.</div>
<?php endif; ?>

<?php foreach ($errosHorario as $erro): ?>
    <div class="alert alert-error"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="hello">
    <h2 class="display-name"><?= e($turma['nome']) ?></h2>
    <p>
        <?= e($turma['ano'] ?: 'Ano não informado') ?>
        · <?= count($alunos) ?> aluno<?= count($alunos) === 1 ? '' : 's' ?>
        <?php if ($minhasMaterias): ?>
            · Você leciona <?= e(implode(', ', $minhasMaterias)) ?>
        <?php endif; ?>
    </p>
</section>

<section class="panel">
    <h2>Representantes</h2>
    <p class="panel-lead">Um professor pode dar mais de uma matéria nesta turma.</p>

    <?php if (!$professores): ?>
        <p class="empty">Nenhum representante vinculado.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Representante</th>
                        <th>Matérias</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($professores as $item): ?>
                        <tr>
                            <td data-label="Representante">
                                <?= e($item['nome']) ?>
                                <?php if ((int) $item['professor_id'] === (int) $professor['id']): ?>
                                    <span class="chip chip-ok">Você</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Matérias">
                                <?php foreach ($item['materias'] as $materia): ?>
                                    <span class="chip chip-professor"><?= e(rotuloMateria($materia['materia'])) ?></span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/horario_painel.php'; ?>

<section class="panel">
    <h2>Alunos</h2>

    <?php if (!$alunos): ?>
        <p class="empty">Nenhum aluno nesta turma.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunos as $aluno): ?>
                        <tr>
                            <td data-label="Nome"><?= e($aluno['nome']) ?></td>
                            <td data-label="E-mail"><?= e($aluno['email']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
