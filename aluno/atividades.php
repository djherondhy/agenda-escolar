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

$tiposValidos = ['prova', 'trabalho', 'tarefa', 'outro'];
$statusValidos = ['pendentes', 'concluidas'];
$origensValidas = ['turma', 'pessoais'];

$filtroTipo = $_GET['tipo'] ?? 'todas';
$filtroStatus = $_GET['status'] ?? 'todas';
$filtroOrigem = $_GET['origem'] ?? 'todas';

if (!in_array($filtroTipo, $tiposValidos, true)) {
    $filtroTipo = 'todas';
}

if (!in_array($filtroStatus, $statusValidos, true)) {
    $filtroStatus = 'todas';
}

if (!in_array($filtroOrigem, $origensValidas, true)) {
    $filtroOrigem = 'todas';
}

$sql = 'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora, e.materia,
               COALESCE(ae.concluido, 0) AS concluido
        FROM eventos e
        LEFT JOIN aluno_eventos ae ON ae.evento_id = e.id AND ae.aluno_id = :aluno_id
        WHERE e.turma_id = :turma_id';

$params = [
    'aluno_id' => $aluno['id'],
    'turma_id' => $aluno['turma_id'],
];

if ($filtroTipo !== 'todas') {
    $sql .= ' AND e.tipo = :filtro_tipo';
    $params['filtro_tipo'] = $filtroTipo;
}

if ($filtroStatus === 'pendentes') {
    $sql .= ' AND COALESCE(ae.concluido, 0) = 0';
}

if ($filtroStatus === 'concluidas') {
    $sql .= ' AND COALESCE(ae.concluido, 0) = 1';
}

$sql .= ' ORDER BY e.data_evento ASC, e.hora ASC';

$eventos = [];
if ($filtroOrigem !== 'pessoais') {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $eventos = $stmt->fetchAll();
}

$pessoais = [];
if ($filtroOrigem !== 'turma') {
    $pessoais = listarAtividadesPessoais($pdo, (int) $aluno['id']);
    if ($filtroStatus === 'pendentes') {
        $pessoais = array_values(array_filter($pessoais, static fn ($item) => !$item['concluido']));
    }
    if ($filtroStatus === 'concluidas') {
        $pessoais = array_values(array_filter($pessoais, static fn ($item) => $item['concluido']));
    }
}

$tituloPagina = 'Atividades';
$menuAtivo = 'atividades';
$acaoTopo = '<button class="btn btn-stamp" type="button" data-modal="pessoal">Nova pessoal</button>';

require __DIR__ . '/../includes/header.php';

$base = BASE_URL . '/aluno/atividades.php';
$qs = static function (array $extra) use ($filtroTipo, $filtroStatus, $filtroOrigem, $base): string {
    $query = array_merge([
        'origem' => $filtroOrigem,
        'tipo'   => $filtroTipo,
        'status' => $filtroStatus,
    ], $extra);
    return $base . '?' . http_build_query($query);
};
?>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-ok" role="status">Atividade pessoal salva.</div>
<?php endif; ?>

<?php if (!empty($_GET['excluido'])): ?>
    <div class="alert alert-ok" role="status">Atividade pessoal excluída.</div>
<?php endif; ?>

<?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-error">Confira os dados da atividade pessoal e tente de novo.</div>
<?php endif; ?>

<nav class="filter-bar" aria-label="Origem das atividades">
    <a class="<?= $filtroOrigem === 'todas' ? 'is-active' : '' ?>" href="<?= $qs(['origem' => 'todas']) ?>">Todas</a>
    <a class="<?= $filtroOrigem === 'turma' ? 'is-active' : '' ?>" href="<?= $qs(['origem' => 'turma']) ?>">Da turma</a>
    <a class="<?= $filtroOrigem === 'pessoais' ? 'is-active' : '' ?>" href="<?= $qs(['origem' => 'pessoais']) ?>">Pessoais</a>
</nav>

<?php if ($filtroOrigem !== 'pessoais'): ?>
    <nav class="filter-bar" aria-label="Filtrar por tipo">
        <a class="<?= $filtroTipo === 'todas' ? 'is-active' : '' ?>" href="<?= $qs(['tipo' => 'todas']) ?>">Todas</a>
        <a class="<?= $filtroTipo === 'prova' ? 'is-active' : '' ?>" href="<?= $qs(['tipo' => 'prova']) ?>">Provas</a>
        <a class="<?= $filtroTipo === 'trabalho' ? 'is-active' : '' ?>" href="<?= $qs(['tipo' => 'trabalho']) ?>">Trabalhos</a>
        <a class="<?= $filtroTipo === 'tarefa' ? 'is-active' : '' ?>" href="<?= $qs(['tipo' => 'tarefa']) ?>">Tarefas</a>
        <a class="<?= $filtroTipo === 'outro' ? 'is-active' : '' ?>" href="<?= $qs(['tipo' => 'outro']) ?>">Outros</a>
    </nav>
<?php endif; ?>

<nav class="filter-bar" aria-label="Filtrar por status">
    <a class="<?= $filtroStatus === 'todas' ? 'is-active' : '' ?>" href="<?= $qs(['status' => 'todas']) ?>">Todas</a>
    <a class="<?= $filtroStatus === 'pendentes' ? 'is-active' : '' ?>" href="<?= $qs(['status' => 'pendentes']) ?>">Pendentes</a>
    <a class="<?= $filtroStatus === 'concluidas' ? 'is-active' : '' ?>" href="<?= $qs(['status' => 'concluidas']) ?>">Concluídas</a>
</nav>

<?php if (!$eventos && !$pessoais): ?>
    <div class="empty-state">
        <p>Nenhuma atividade encontrada com esses filtros.</p>
        <button class="btn btn-stamp empty-action" type="button" data-modal="pessoal">Nova atividade pessoal</button>
    </div>
<?php endif; ?>

<?php foreach ($eventos as $evento): ?>
    <button class="activity-card" type="button" data-modal="evento" data-id="<?= (int) $evento['id'] ?>">
        <div>
            <div class="agenda-title"><?= e($evento['titulo']) ?></div>
            <p class="agenda-meta">
                <?= formatarData($evento['data_evento']) ?>
                <?= $evento['hora'] ? ' · ' . formatarHora($evento['hora']) : '' ?>
                <?php if (!empty($evento['materia'])): ?>
                    · <?= e($evento['materia']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="activity-chips">
            <span class="chip chip-<?= e($evento['tipo']) ?>"><?= e(rotuloTipo($evento['tipo'])) ?></span>
            <span class="chip <?= $evento['concluido'] ? 'chip-ok' : 'chip-wait' ?>">
                <?= $evento['concluido'] ? 'Concluída' : 'Pendente' ?>
            </span>
        </div>
    </button>
<?php endforeach; ?>

<?php foreach ($pessoais as $atividade): ?>
    <button class="activity-card" type="button" data-modal="pessoal" data-id="<?= (int) $atividade['id'] ?>">
        <div>
            <div class="agenda-title"><?= e($atividade['titulo']) ?></div>
            <p class="agenda-meta">
                <?= formatarData($atividade['data_evento']) ?>
                <?= $atividade['hora'] ? ' · ' . formatarHora($atividade['hora']) : '' ?>
                <?= $atividade['materia'] !== '' ? ' · ' . e($atividade['materia']) : '' ?>
            </p>
        </div>
        <div class="activity-chips">
            <span class="chip chip-outro">Pessoal</span>
            <span class="chip <?= $atividade['concluido'] ? 'chip-ok' : 'chip-wait' ?>">
                <?= $atividade['concluido'] ? 'Concluída' : 'Pendente' ?>
            </span>
        </div>
    </button>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
