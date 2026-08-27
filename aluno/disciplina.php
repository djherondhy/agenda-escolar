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

$materia = trim((string) ($_GET['materia'] ?? ''));
$turmaId = (int) $aluno['turma_id'];
$disciplinas = listarDisciplinasDaTurma($pdo, $turmaId);
$disciplina = null;

foreach ($disciplinas as $item) {
    if (strcasecmp((string) $item['materia'], $materia) === 0) {
        $disciplina = $item;
        $materia = $item['materia'];
        break;
    }
}

if (!$disciplina) {
    redirecionar('/aluno/disciplinas.php');
}

$eventos = listarEventosDaDisciplina($pdo, $turmaId, (int) $aluno['id'], $materia);
$pessoais = listarAtividadesPessoais($pdo, (int) $aluno['id'], $materia);
$aulas = listarHorariosDaTurma($pdo, $turmaId, $materia);
$nomes = array_unique(array_column($disciplina['professores'], 'nome'));

$tituloPagina = rotuloMateria($materia);
$menuAtivo = 'disciplinas';
$acaoTopo = '<button class="btn btn-stamp" type="button" data-modal="pessoal" data-materia="' . e($materia) . '">Atividade pessoal</button>';

require __DIR__ . '/../includes/header.php';
?>

<p class="back-link"><a href="<?= BASE_URL ?>/aluno/disciplinas.php">Disciplinas</a></p>

<section class="hello">
    <h2 class="display-name"><?= e(rotuloMateria($materia)) ?></h2>
    <p><?= e(implode(', ', $nomes) ?: 'Professor não informado') ?> · <?= e($aluno['turma_nome']) ?></p>
</section>

<section class="panel">
    <h2>Horários</h2>
    <?php if (!$aulas): ?>
        <p class="empty">Nenhum horário de aula cadastrado para esta disciplina.</p>
    <?php else: ?>
        <ul class="slot-list">
            <?php foreach ($aulas as $aula): ?>
                <li>
                    <strong><?= e(rotuloDiaSemana((int) $aula['dia_semana'])) ?></strong>
                    <?= formatarHora($aula['hora_inicio']) ?><?= $aula['hora_fim'] ? ' – ' . formatarHora($aula['hora_fim']) : '' ?>
                    · <?= e($aula['professor_nome']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Atividades da turma</h2>
        <a href="<?= BASE_URL ?>/aluno/atividades.php">Ver todas</a>
    </div>
    <?php if (!$eventos): ?>
        <p class="empty">Nenhuma atividade da turma nesta disciplina.</p>
    <?php endif; ?>
    <?php foreach ($eventos as $evento): ?>
        <button class="agenda-item <?= $evento['concluido'] ? 'is-done' : '' ?>" type="button" data-modal="evento" data-id="<?= (int) $evento['id'] ?>">
            <span class="agenda-mark mark-<?= e($evento['tipo']) ?>"></span>
            <div>
                <div class="agenda-title"><?= e($evento['titulo']) ?></div>
                <p class="agenda-meta">
                    <span class="prazo <?= classePrazo($evento['data_evento']) ?>"><?= e(rotuloPrazo($evento['data_evento'])) ?></span>
                    <?= $evento['hora'] ? ' · ' . formatarHora($evento['hora']) : '' ?>
                </p>
            </div>
            <span class="chip <?= $evento['concluido'] ? 'chip-ok' : 'chip-' . e($evento['tipo']) ?>">
                <?= $evento['concluido'] ? 'Feito' : e(rotuloTipo($evento['tipo'])) ?>
            </span>
        </button>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Suas atividades pessoais</h2>
        <button class="link-modal" type="button" data-modal="pessoal" data-materia="<?= e($materia) ?>">Nova</button>
    </div>
    <?php if (!$pessoais): ?>
        <p class="empty">Nada pessoal nesta disciplina. Use para provas extras, revisão ou tarefas de casa.</p>
    <?php endif; ?>
    <?php foreach ($pessoais as $atividade): ?>
        <button class="agenda-item <?= $atividade['concluido'] ? 'is-done' : '' ?>" type="button" data-modal="pessoal" data-id="<?= (int) $atividade['id'] ?>">
            <span class="agenda-mark mark-outro"></span>
            <div>
                <div class="agenda-title"><?= e($atividade['titulo']) ?></div>
                <p class="agenda-meta">
                    <span class="prazo <?= classePrazo($atividade['data_evento']) ?>"><?= e(rotuloPrazo($atividade['data_evento'])) ?></span>
                    <?= $atividade['hora'] ? ' · ' . formatarHora($atividade['hora']) : '' ?>
                </p>
            </div>
            <span class="chip <?= $atividade['concluido'] ? 'chip-ok' : 'chip-outro' ?>">
                <?= $atividade['concluido'] ? 'Feito' : 'Pessoal' ?>
            </span>
        </button>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
