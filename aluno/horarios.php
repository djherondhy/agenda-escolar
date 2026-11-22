<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAluno();

$pdo = getPDO();
$aluno = obterAlunoPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$aluno) {
    encerrarSessao();
    redirecionar('/login.php');
}

$horarios = listarHorariosDaTurma($pdo, (int) $aluno['turma_id']);
$proxima = proximaAulaHoje($horarios);

$tituloPagina = 'Horários';
$menuAtivo = 'horarios';

require __DIR__ . '/../includes/header.php';
?>

<section class="hello">
    <h2 class="display-name">Grade da semana</h2>
    <p><?= e($aluno['turma_nome']) ?> · segunda a sexta.</p>
</section>

<?php if ($proxima): ?>
    <div class="dash-hero tipo-trabalho">
        <span class="dash-hero-kicker">Próxima aula hoje</span>
        <strong><?= e(rotuloMateria($proxima['materia'])) ?></strong>
        <span class="dash-hero-meta">
            <?= formatarHora($proxima['hora_inicio']) ?>
            <?= $proxima['hora_fim'] ? ' – ' . formatarHora($proxima['hora_fim']) : '' ?>
            <?= $proxima['professor_nome'] !== '' ? ' · ' . e($proxima['professor_nome']) : '' ?>
        </span>
    </div>
<?php endif; ?>

<section class="panel">
    <?php if (!$horarios && !listarGradeDaTurma($pdo, (int) $aluno['turma_id'])): ?>
        <div class="empty-state">
            <p>Os horários desta turma ainda não foram definidos.</p>
            <a class="btn btn-ghost empty-action" href="<?= BASE_URL ?>/aluno/disciplinas.php">Ver disciplinas</a>
        </div>
    <?php else: ?>
        <?php
        $podeEditarQuadro = false;
        $grade = listarGradeDaTurma($pdo, (int) $aluno['turma_id']);
        $horariosPainel = $horarios;
        require __DIR__ . '/../includes/quadro_grade.php';
        ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
