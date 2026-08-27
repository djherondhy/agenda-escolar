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

$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM eventos WHERE turma_id = :turma_id');
$stmtTotal->execute(['turma_id' => $aluno['turma_id']]);
$totalAtividades = (int) $stmtTotal->fetchColumn();

$stmtConcluidas = $pdo->prepare(
    'SELECT COUNT(*)
     FROM aluno_eventos ae
     INNER JOIN eventos e ON e.id = ae.evento_id
     WHERE ae.aluno_id = :aluno_id AND ae.concluido = 1 AND e.turma_id = :turma_id'
);
$stmtConcluidas->execute([
    'aluno_id' => $aluno['id'],
    'turma_id' => $aluno['turma_id'],
]);
$totalConcluidas = (int) $stmtConcluidas->fetchColumn();

$totalPendentes = max($totalAtividades - $totalConcluidas, 0);
$percentual = $totalAtividades > 0 ? (int) round(($totalConcluidas / $totalAtividades) * 100) : 0;

$stmtLista = $pdo->prepare(
    'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora,
            COALESCE(ae.concluido, 0) AS concluido
     FROM eventos e
     LEFT JOIN aluno_eventos ae ON ae.evento_id = e.id AND ae.aluno_id = :aluno_id
     WHERE e.turma_id = :turma_id AND e.data_evento >= CURDATE()
     ORDER BY e.data_evento ASC, e.hora ASC
     LIMIT 6'
);
$stmtLista->execute([
    'aluno_id' => $aluno['id'],
    'turma_id' => $aluno['turma_id'],
]);
$proximas = $stmtLista->fetchAll();

$proximaPendente = null;
foreach ($proximas as $evento) {
    if (!$evento['concluido']) {
        $proximaPendente = $evento;
        break;
    }
}

$horarios = listarHorariosDaTurma($pdo, (int) $aluno['turma_id']);
$proximaAula = proximaAulaHoje($horarios);
$pessoais = listarAtividadesPessoais($pdo, (int) $aluno['id']);
$pessoaisFuturas = array_values(array_filter(
    $pessoais,
    static fn ($item) => $item['data_evento'] >= date('Y-m-d') && !$item['concluido']
));

$agenda = [];
foreach ($proximas as $evento) {
    $agenda[] = [
        'quando'    => $evento['data_evento'] . ' ' . ($evento['hora'] ?: '23:59:59'),
        'tipo'      => 'turma',
        'item'      => $evento,
    ];
}
foreach (array_slice($pessoaisFuturas, 0, 6) as $atividade) {
    $agenda[] = [
        'quando' => $atividade['data_evento'] . ' ' . ($atividade['hora'] ?: '23:59:59'),
        'tipo'   => 'pessoal',
        'item'   => $atividade,
    ];
}
usort($agenda, static fn ($a, $b) => $a['quando'] <=> $b['quando']);
$agenda = array_slice($agenda, 0, 6);

$tituloPagina = dataPorExtenso();
$menuAtivo = 'dashboard';
$acaoTopo = '<button class="btn btn-stamp" type="button" data-modal="pessoal">Nova pessoal</button>';

require __DIR__ . '/../includes/header.php';
?>

<section class="hello">
    <h2 class="display-name">Olá, <?= e(primeiroNome($aluno['nome'])) ?></h2>
    <p>
        <button class="hello-turma" type="button" data-modal="turma" data-id="<?= (int) $aluno['turma_id'] ?>"><?= e($aluno['turma_nome']) ?></button>
        Acompanhe aulas, disciplinas e o que ainda precisa fazer.
    </p>
</section>

<?php if ($proximaAula): ?>
    <a class="dash-hero tipo-trabalho" href="<?= BASE_URL ?>/aluno/horarios.php">
        <span class="dash-hero-kicker">Próxima aula hoje</span>
        <strong><?= e(rotuloMateria($proximaAula['materia'])) ?></strong>
        <span class="dash-hero-meta">
            <?= formatarHora($proximaAula['hora_inicio']) ?>
            <?= $proximaAula['hora_fim'] ? ' – ' . formatarHora($proximaAula['hora_fim']) : '' ?>
            · <?= e($proximaAula['professor_nome']) ?>
        </span>
    </a>
<?php elseif ($proximaPendente): ?>
    <button class="dash-hero tipo-<?= e($proximaPendente['tipo']) ?>" type="button" data-modal="evento" data-id="<?= (int) $proximaPendente['id'] ?>">
        <span class="dash-hero-kicker">Próxima pendência</span>
        <strong><?= e($proximaPendente['titulo']) ?></strong>
        <span class="dash-hero-meta">
            <span class="prazo <?= classePrazo($proximaPendente['data_evento']) ?>"><?= e(rotuloPrazo($proximaPendente['data_evento'])) ?></span>
            <?= $proximaPendente['hora'] ? ' · ' . formatarHora($proximaPendente['hora']) : '' ?>
            · <?= e(rotuloTipo($proximaPendente['tipo'])) ?>
        </span>
    </button>
<?php elseif ($proximas): ?>
    <div class="dash-hero is-done">
        <span class="dash-hero-kicker">Tudo em dia nesta lista</span>
        <strong>As próximas atividades já estão concluídas.</strong>
        <span class="dash-hero-meta">O histórico continua disponível em Atividades.</span>
    </div>
<?php endif; ?>

<section class="progress-card">
    <div class="progress-copy">
        <strong><?= $totalPendentes ?> pendente<?= $totalPendentes === 1 ? '' : 's' ?></strong>
        <span><?= $totalConcluidas ?> de <?= $totalAtividades ?> concluída<?= $totalAtividades === 1 ? '' : 's' ?></span>
    </div>
    <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percentual ?>" aria-label="Progresso das atividades">
        <span class="progress-fill" style="width: <?= $percentual ?>%"></span>
    </div>
</section>

<nav class="dash-shortcuts" aria-label="Atalhos">
    <a href="<?= BASE_URL ?>/aluno/horarios.php">Horários da semana</a>
    <a href="<?= BASE_URL ?>/aluno/disciplinas.php">Disciplinas</a>
    <a href="<?= BASE_URL ?>/aluno/atividades.php?origem=pessoais">Atividades pessoais</a>
    <a href="<?= BASE_URL ?>/aluno/atividades.php?status=pendentes">Pendentes da turma</a>
</nav>

<section class="panel">
    <div class="panel-head">
        <h2>O que vem pela frente</h2>
        <a href="<?= BASE_URL ?>/aluno/atividades.php">Ver lista</a>
    </div>

    <?php if (!$agenda): ?>
        <div class="empty-state">
            <p>Nada marcado daqui pra frente. Você pode criar uma atividade pessoal ou ver o histórico da turma.</p>
            <button class="btn btn-stamp empty-action" type="button" data-modal="pessoal">Nova pessoal</button>
        </div>
    <?php endif; ?>

    <?php foreach ($agenda as $entrada): ?>
        <?php $item = $entrada['item']; ?>
        <?php if ($entrada['tipo'] === 'pessoal'): ?>
            <button class="agenda-item <?= $item['concluido'] ? 'is-done' : '' ?>" type="button" data-modal="pessoal" data-id="<?= (int) $item['id'] ?>">
                <span class="agenda-mark mark-outro"></span>
                <div>
                    <div class="agenda-title"><?= e($item['titulo']) ?></div>
                    <p class="agenda-meta">
                        <span class="prazo <?= classePrazo($item['data_evento']) ?>"><?= e(rotuloPrazo($item['data_evento'])) ?></span>
                        <?= $item['hora'] ? ' · ' . formatarHora($item['hora']) : '' ?>
                        <?= $item['materia'] !== '' ? ' · ' . e($item['materia']) : '' ?>
                    </p>
                </div>
                <span class="chip chip-outro">Pessoal</span>
            </button>
        <?php else: ?>
            <button class="agenda-item <?= $item['concluido'] ? 'is-done' : '' ?>" type="button" data-modal="evento" data-id="<?= (int) $item['id'] ?>">
                <span class="agenda-mark mark-<?= e($item['tipo']) ?>"></span>
                <div>
                    <div class="agenda-title"><?= e($item['titulo']) ?></div>
                    <p class="agenda-meta">
                        <span class="prazo <?= classePrazo($item['data_evento']) ?>"><?= e(rotuloPrazo($item['data_evento'])) ?></span>
                        <?= $item['hora'] ? ' · ' . formatarHora($item['hora']) : '' ?>
                    </p>
                </div>
                <span class="chip <?= $item['concluido'] ? 'chip-ok' : 'chip-' . e($item['tipo']) ?>">
                    <?= $item['concluido'] ? 'Feito' : e(rotuloTipo($item['tipo'])) ?>
                </span>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
