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
$porDia = agruparHorariosPorDia($horarios);
$hoje = (int) (new DateTime('today'))->format('N');
$proxima = proximaAulaHoje($horarios);

$tituloPagina = 'Horários';
$menuAtivo = 'horarios';

require __DIR__ . '/../includes/header.php';
?>

<section class="hello">
    <h2 class="display-name">Grade da semana</h2>
    <p><?= e($aluno['turma_nome']) ?> · aulas cadastradas pelos professores.</p>
</section>

<?php if ($proxima): ?>
    <div class="dash-hero tipo-trabalho">
        <span class="dash-hero-kicker">Próxima aula hoje</span>
        <strong><?= e(rotuloMateria($proxima['materia'])) ?></strong>
        <span class="dash-hero-meta">
            <?= formatarHora($proxima['hora_inicio']) ?>
            <?= $proxima['hora_fim'] ? ' – ' . formatarHora($proxima['hora_fim']) : '' ?>
            · <?= e($proxima['professor_nome']) ?>
        </span>
    </div>
<?php endif; ?>

<section class="panel">
    <?php if (!$horarios): ?>
        <div class="empty-state">
            <p>Os horários desta turma ainda não foram definidos. Peça ao professor ou ao administrador para cadastrar a grade.</p>
            <a class="btn btn-ghost empty-action" href="<?= BASE_URL ?>/aluno/disciplinas.php">Ver disciplinas</a>
        </div>
    <?php else: ?>
        <div class="week-board">
            <?php foreach ($porDia as $coluna): ?>
                <section class="week-col <?= (int) $coluna['dia'] === $hoje ? 'is-today' : '' ?>">
                    <h3><?= e($coluna['nome']) ?></h3>
                    <?php if (!$coluna['aulas']): ?>
                        <p class="week-empty">Sem aula</p>
                    <?php endif; ?>
                    <?php foreach ($coluna['aulas'] as $aula): ?>
                        <a class="week-slot" href="<?= BASE_URL ?>/aluno/disciplina.php?materia=<?= urlencode($aula['materia']) ?>">
                            <strong><?= e(rotuloMateria($aula['materia'])) ?></strong>
                            <span><?= formatarHora($aula['hora_inicio']) ?><?= $aula['hora_fim'] ? ' – ' . formatarHora($aula['hora_fim']) : '' ?></span>
                            <span><?= e($aula['professor_nome']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
