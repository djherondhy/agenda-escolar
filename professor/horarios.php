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

$errosHorario = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turmaId = (int) ($_POST['turma_id'] ?? 0);
    if (str_contains((string) ($_POST['vinculo_turma'] ?? ''), '|')) {
        [$turmaId, $materia] = explode('|', (string) $_POST['vinculo_turma'], 2);
        $turmaId = (int) $turmaId;
        $_POST['vinculo'] = $professor['id'] . '|' . $materia;
    }
    if ($turmaId && professorTemTurma($pdo, (int) $professor['id'], $turmaId)) {
        $resultado = processarHorarioTurma($pdo, $_POST, $turmaId, (int) $professor['id'], $errosHorario);
        if ($resultado) {
            redirecionar('/professor/horarios.php?ok=' . $resultado);
        }
    } else {
        $errosHorario[] = 'Selecione uma turma e uma disciplina válidas.';
    }
}

$disciplinas = $pdo->prepare(
    'SELECT pt.turma_id, pt.materia, t.nome AS turma_nome
     FROM professor_turmas pt
     INNER JOIN turmas t ON t.id = pt.turma_id
     WHERE pt.professor_id = :professor_id
     ORDER BY t.nome, pt.materia'
);
$disciplinas->execute(['professor_id' => $professor['id']]);
$disciplinas = $disciplinas->fetchAll();

$horarios = listarHorariosDoProfessor($pdo, (int) $professor['id']);
$porDia = agruparHorariosPorDia($horarios);
$hoje = (int) (new DateTime('today'))->format('N');
$proxima = proximaAula($horarios);

$tituloPagina = 'Horários';
$menuAtivo = 'horarios';

require __DIR__ . '/../includes/header.php';
?>

<?php if (($_GET['ok'] ?? '') === 'horario_ok'): ?>
    <div class="alert alert-ok" role="status">Horário da turma cadastrado.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_removido'): ?>
    <div class="alert alert-ok" role="status">Horário removido.</div>
<?php endif; ?>
<?php foreach ($errosHorario as $erro): ?>
    <div class="alert alert-error"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="hello">
    <h2 class="display-name">Grade da semana</h2>
    <p>Cadastre as aulas de cada turma. Os alunos veem essa grade em Horários.</p>
</section>

<?php if ($proxima): ?>
    <div class="dash-hero tipo-trabalho">
        <span class="dash-hero-kicker">
            <?= (int) $proxima['dias_ate'] === 0 ? 'Próxima aula hoje' : 'Próxima aula · ' . e(rotuloDiaSemana((int) $proxima['dia_semana'])) ?>
        </span>
        <strong><?= e(rotuloMateria($proxima['materia'])) ?></strong>
        <span class="dash-hero-meta">
            <?= formatarHora($proxima['hora_inicio']) ?>
            <?= $proxima['hora_fim'] ? ' – ' . formatarHora($proxima['hora_fim']) : '' ?>
            · <?= e($proxima['turma_nome']) ?>
        </span>
    </div>
<?php endif; ?>

<section class="panel">
    <?php if (!$horarios): ?>
        <div class="empty-state">
            <p>Nenhum horário de aula cadastrado para as suas turmas.</p>
            <a class="btn btn-ghost empty-action" href="<?= BASE_URL ?>/professor/turmas.php">Ver turmas</a>
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
                        <?php $ehProxima = $proxima && (int) $aula['id'] === (int) $proxima['id']; ?>
                        <div class="week-slot <?= $ehProxima ? 'is-next' : '' ?>">
                            <?php if ($ehProxima): ?>
                                <span class="week-next">Próxima aula</span>
                            <?php endif; ?>
                            <strong><?= e(rotuloMateria($aula['materia'])) ?></strong>
                            <span><?= formatarHora($aula['hora_inicio']) ?><?= $aula['hora_fim'] ? ' – ' . formatarHora($aula['hora_fim']) : '' ?></span>
                            <span><?= e($aula['turma_nome']) ?></span>
                            <form method="post" action="<?= BASE_URL ?>/professor/horarios.php" data-confirm="Remover este horário?">
                                <input type="hidden" name="acao" value="remover_horario">
                                <input type="hidden" name="turma_id" value="<?= (int) $aula['turma_id'] ?>">
                                <input type="hidden" name="horario_id" value="<?= (int) $aula['id'] ?>">
                                <button class="btn btn-danger" type="submit">Excluir</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Cadastrar aula da turma</h2>
    <p class="panel-lead">Escolha a turma, a disciplina, o dia e o horário. Os alunos dessa turma passam a ver a aula na grade.</p>
    <?php if (!$disciplinas): ?>
        <p class="empty">Você ainda não está ligado a uma turma. Peça ao conselheiro para associar você.</p>
    <?php else: ?>
        <form class="stack-form" method="post" action="<?= BASE_URL ?>/professor/horarios.php">
            <input type="hidden" name="acao" value="adicionar_horario">
            <div class="form-grid">
                <div class="field span-2">
                    <label for="vinculo_turma">Turma e disciplina</label>
                    <select id="vinculo_turma" name="vinculo_turma" required>
                        <option value="">Selecione</option>
                        <?php foreach ($disciplinas as $disciplina): ?>
                            <option value="<?= (int) $disciplina['turma_id'] ?>|<?= e($disciplina['materia']) ?>">
                                <?= e($disciplina['turma_nome']) ?> · <?= e(rotuloMateria($disciplina['materia'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="dia_semana">Dia</label>
                    <select id="dia_semana" name="dia_semana" required>
                        <option value="">Selecione</option>
                        <?php foreach (diasDaSemana() as $numero => $nome): ?>
                            <option value="<?= $numero ?>"><?= e($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="hora_inicio">Início</label>
                    <input id="hora_inicio" name="hora_inicio" type="time" required>
                </div>
                <div class="field">
                    <label for="hora_fim">Término</label>
                    <input id="hora_fim" name="hora_fim" type="time">
                </div>
            </div>
            <button class="btn btn-stamp" type="submit">Adicionar horário</button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
