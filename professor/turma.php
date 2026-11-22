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
    $acao = (string) ($_POST['acao'] ?? '');
    if (in_array($acao, ['mover_horario', 'definir_horario', 'esvaziar_horario', 'gerar_horario'], true)) {
        $resultadoQuadro = processarQuadroHorario($pdo, $_POST, $turmaId, $errosHorario);
        if (($_POST['ajax'] ?? '') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'   => $resultadoQuadro !== null,
                'erro' => $errosHorario[0] ?? '',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($resultadoQuadro) {
            redirecionar('/professor/turma.php?id=' . $turmaId . '&ok=' . $resultadoQuadro);
        }
    } else {
        $resultado = processarHorarioTurma($pdo, $_POST, $turmaId, (int) $professor['id'], $errosHorario);
        if ($resultado) {
            redirecionar('/professor/turma.php?id=' . $turmaId . '&ok=' . $resultado);
        }
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
        $minhasMaterias = [];
        foreach ($item['materias'] as $materia) {
            if (trim((string) $materia['materia']) === '') {
                continue;
            }
            $minhasMaterias[] = rotuloMateria($materia['materia']);
        }
        break;
    }
}

$tituloPagina = $turma['nome'];
$menuAtivo = 'turmas';

require __DIR__ . '/../includes/header.php';
?>

<p class="back-link"><a href="<?= BASE_URL ?>/professor/turmas.php">Turmas</a></p>

<?php if (($_GET['ok'] ?? '') === 'horario_gerado'): ?>
    <div class="alert alert-ok" role="status">Grade de horários gerada. Arraste as aulas para ajustar.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_quadro'): ?>
    <div class="alert alert-ok" role="status">Grade de horários atualizada.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_ok'): ?>
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
    <h2>Representante</h2>
    <?php $representante = obterRepresentanteDaTurma($pdo, $turmaId); ?>
    <?php if (!$representante): ?>
        <p class="empty">Esta turma ainda não tem um aluno representante.</p>
    <?php else: ?>
        <div class="rep-card">
            <div>
                <strong><?= e($representante['nome']) ?></strong>
                <span><?= (int) $representante['usuario_id'] === (int) $_SESSION['usuario_id'] ? 'Você representa esta turma.' : e($representante['email']) ?></span>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Horários</h2>
    </div>
    <p class="panel-lead">Segunda a sexta, no turno da manhã. Arraste uma aula para trocar de lugar, ou solte uma disciplina em um horário.</p>
<?php
$podeEditarQuadro = true;
$grade = listarGradeDaTurma($pdo, $turmaId);
$actionQuadro = BASE_URL . '/professor/turma.php?id=' . $turmaId;
require __DIR__ . '/../includes/quadro_grade.php';
?>
</section>

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
