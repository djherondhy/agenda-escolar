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

$turmas = listarTurmasDoProfessor($pdo, (int) $professor['id']);
$turmaId = (int) ($_GET['turma'] ?? $_POST['turma_id'] ?? 0);
if ($turmaId && !professorTemTurma($pdo, (int) $professor['id'], $turmaId)) {
    $turmaId = 0;
}
if (!$turmaId && count($turmas) === 1) {
    $turmaId = (int) $turmas[0]['id'];
}
if (!$turmaId && $turmas) {
    $turmaId = (int) $turmas[0]['id'];
}

$errosHorario = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $turmaId) {
    $acao = (string) ($_POST['acao'] ?? '');
    if (in_array($acao, ['mover_horario', 'definir_horario', 'esvaziar_horario', 'gerar_horario'], true)) {
        $resultadoQuadro = processarQuadroHorario($pdo, $_POST, $turmaId, $errosHorario);
        $destino = '/professor/horarios.php' . (count($turmas) > 1 ? '?turma=' . $turmaId : '');
        if (($_POST['ajax'] ?? '') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'   => $resultadoQuadro !== null,
                'erro' => $errosHorario[0] ?? '',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($resultadoQuadro) {
            $sep = str_contains($destino, '?') ? '&' : '?';
            redirecionar($destino . $sep . 'ok=' . $resultadoQuadro);
        }
    }
}

$turma = $turmaId ? obterTurma($pdo, $turmaId) : null;
$grade = $turma ? listarGradeDaTurma($pdo, $turmaId) : [];
$horariosPainel = $turma ? listarHorariosDaTurma($pdo, $turmaId) : [];

$tituloPagina = 'Horários';
$menuAtivo = 'horarios';

require __DIR__ . '/../includes/header.php';
?>

<?php if (($_GET['ok'] ?? '') === 'horario_gerado'): ?>
    <div class="alert alert-ok" role="status">Grade de horários gerada. Arraste as aulas para ajustar.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_quadro'): ?>
    <div class="alert alert-ok" role="status">Grade de horários atualizada.</div>
<?php endif; ?>
<?php foreach ($errosHorario as $erro): ?>
    <div class="alert alert-error"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="hello">
    <h2 class="display-name">Grade da semana</h2>
    <p><?= $turma ? e($turma['nome']) . ' · arraste as aulas para organizar.' : 'Sua turma ainda não foi definida.' ?></p>
</section>

<?php if (!$turma): ?>
    <section class="panel">
        <p class="empty">Peça ao conselheiro para escolher você como representante de uma turma.</p>
    </section>
<?php else: ?>
    <?php if (count($turmas) > 1): ?>
        <nav class="turma-abas" aria-label="Turmas">
            <?php foreach ($turmas as $item): ?>
                <a class="<?= (int) $item['id'] === $turmaId ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/professor/horarios.php?turma=<?= (int) $item['id'] ?>">
                    <?= e($item['nome']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <section class="panel">
        <?php
        $podeEditarQuadro = true;
        $actionQuadro = BASE_URL . '/professor/horarios.php' . (count($turmas) > 1 ? '?turma=' . $turmaId : '');
        require __DIR__ . '/../includes/quadro_grade.php';
        ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
