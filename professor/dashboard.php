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

$stmtTurmas = $pdo->prepare(
    'SELECT COUNT(*) FROM professor_turmas WHERE professor_id = :professor_id'
);
$stmtTurmas->execute(['professor_id' => $professor['id']]);
$totalTurmas = (int) $stmtTurmas->fetchColumn();

$stmtSemana = $pdo->prepare(
    'SELECT COUNT(*)
     FROM eventos
     WHERE professor_id = :professor_id
       AND data_evento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'
);
$stmtSemana->execute(['professor_id' => $professor['id']]);
$totalSemana = (int) $stmtSemana->fetchColumn();

$stmtProximas = $pdo->prepare(
    'SELECT COUNT(*)
     FROM eventos
     WHERE professor_id = :professor_id AND data_evento >= CURDATE()'
);
$stmtProximas->execute(['professor_id' => $professor['id']]);
$totalProximas = (int) $stmtProximas->fetchColumn();

$stmtLista = $pdo->prepare(
    'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora, e.turma_id, t.nome AS turma_nome
     FROM eventos e
     INNER JOIN turmas t ON t.id = e.turma_id
     WHERE e.professor_id = :professor_id AND e.data_evento >= CURDATE()
     ORDER BY e.data_evento ASC, e.hora ASC
     LIMIT 6'
);
$stmtLista->execute(['professor_id' => $professor['id']]);
$proximas = $stmtLista->fetchAll();
$proxima = $proximas[0] ?? null;

$tituloPagina = dataPorExtenso();
$menuAtivo = 'dashboard';
$acaoTopo = '<a class="btn btn-stamp" href="' . BASE_URL . '/professor/adicionar_evento.php">Nova atividade</a>';

require __DIR__ . '/../includes/header.php';
?>

<section class="hello">
    <h2 class="display-name">Olá, Professor <?= e(primeiroNome($professor['nome'])) ?></h2>
    <p>O que a turma precisa saber agora.</p>
</section>

<?php if ($proxima): ?>
    <button class="dash-hero tipo-<?= e($proxima['tipo']) ?>" type="button" data-modal="evento" data-id="<?= (int) $proxima['id'] ?>">
        <span class="dash-hero-kicker">Próxima atividade</span>
        <strong><?= e($proxima['titulo']) ?></strong>
        <span class="dash-hero-meta">
            <span class="prazo <?= classePrazo($proxima['data_evento']) ?>"><?= e(rotuloPrazo($proxima['data_evento'])) ?></span>
            <?= $proxima['hora'] ? ' · ' . formatarHora($proxima['hora']) : '' ?>
            · <?= e($proxima['turma_nome']) ?>
        </span>
    </button>
<?php endif; ?>

<section class="stat-row">
    <a class="stat" href="<?= BASE_URL ?>/professor/turmas.php">
        <span class="stat-value"><?= $totalTurmas ?></span>
        <span class="stat-label">Turmas</span>
    </a>
    <a class="stat" href="<?= BASE_URL ?>/professor/eventos.php">
        <span class="stat-value"><?= $totalSemana ?></span>
        <span class="stat-label">Nesta semana</span>
    </a>
    <a class="stat" href="<?= BASE_URL ?>/professor/eventos.php">
        <span class="stat-value"><?= $totalProximas ?></span>
        <span class="stat-label">Ainda pela frente</span>
    </a>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Agenda que vem</h2>
        <a href="<?= BASE_URL ?>/professor/eventos.php">Ver todas</a>
    </div>

    <?php if (!$proximas): ?>
        <div class="empty-state">
            <p>Nenhuma atividade futura ainda. Cadastre a próxima prova ou tarefa da turma.</p>
            <a class="btn btn-stamp empty-action" href="<?= BASE_URL ?>/professor/adicionar_evento.php">Nova atividade</a>
        </div>
    <?php endif; ?>

    <?php foreach ($proximas as $evento): ?>
        <button class="agenda-item" type="button" data-modal="evento" data-id="<?= (int) $evento['id'] ?>">
            <span class="agenda-mark mark-<?= e($evento['tipo']) ?>"></span>
            <div>
                <div class="agenda-title"><?= e($evento['titulo']) ?></div>
                <p class="agenda-meta">
                    <span class="prazo <?= classePrazo($evento['data_evento']) ?>"><?= e(rotuloPrazo($evento['data_evento'])) ?></span>
                    <?= $evento['hora'] ? ' · ' . formatarHora($evento['hora']) : '' ?>
                    · <?= e($evento['turma_nome']) ?>
                </p>
            </div>
            <span class="chip chip-<?= e($evento['tipo']) ?>"><?= e(rotuloTipo($evento['tipo'])) ?></span>
        </button>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
