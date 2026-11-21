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

$vista = $_GET['vista'] ?? 'lista';
if (!in_array($vista, ['lista', 'agenda'], true)) {
    $vista = 'lista';
}

$hoje = new DateTime('today');
$ano = (int) ($_GET['ano'] ?? $hoje->format('Y'));
$mes = (int) ($_GET['mes'] ?? $hoje->format('n'));
if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
    $ano = (int) $hoje->format('Y');
    $mes = (int) $hoje->format('n');
}

$sql = 'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora, e.turma_id, e.concluido, t.nome AS turma_nome
        FROM eventos e
        INNER JOIN turmas t ON t.id = e.turma_id
        WHERE e.professor_id = :professor_id
          AND e.turma_id IN (SELECT pt.turma_id FROM professor_turmas pt WHERE pt.professor_id = :professor_id_turmas)';
$params = ['professor_id' => $professor['id'], 'professor_id_turmas' => $professor['id']];

if ($vista === 'agenda') {
    $sql .= ' AND MONTH(e.data_evento) = :mes AND YEAR(e.data_evento) = :ano';
    $params['mes'] = $mes;
    $params['ano'] = $ano;
}

$sql .= ' ORDER BY e.data_evento ASC, e.hora ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$eventos = $stmt->fetchAll();

$tituloPagina = 'Atividades';
$menuAtivo = 'eventos';
$acaoTopo = '<a class="btn btn-stamp" href="' . BASE_URL . '/professor/adicionar_evento.php">Nova atividade</a>';

require __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-ok" role="status">Atividade salva com sucesso.</div>
<?php endif; ?>

<?php if (!empty($_GET['excluido'])): ?>
    <div class="alert alert-ok" role="status">Atividade excluída.</div>
<?php endif; ?>

<nav class="filter-bar" aria-label="Visualização das atividades">
    <a class="<?= $vista === 'lista' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/professor/eventos.php?vista=lista">Lista</a>
    <a class="<?= $vista === 'agenda' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/professor/eventos.php?vista=agenda&ano=<?= $ano ?>&mes=<?= $mes ?>">Agenda</a>
</nav>

<?php if ($vista === 'agenda'): ?>
    <?php
    $inicioMes = new DateTime(sprintf('%04d-%02d-01', $ano, $mes));
    $diasNoMes = (int) $inicioMes->format('t');
    $anterior = (clone $inicioMes)->modify('-1 month');
    $proximo = (clone $inicioMes)->modify('+1 month');
    $inicioSemana = (int) $inicioMes->format('N');
    $porData = agruparEventosPorData($eventos);
    $meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $nomeMes = ($meses[$mes] ?? $inicioMes->format('m')) . ' ' . $ano;
    $diasSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    $baseAgenda = BASE_URL . '/professor/eventos.php?vista=agenda';
    ?>
    <section class="panel">
        <div class="cal-head">
            <a class="btn btn-ghost" href="<?= $baseAgenda ?>&ano=<?= $anterior->format('Y') ?>&mes=<?= $anterior->format('n') ?>">Anterior</a>
            <h2 class="cal-month"><?= e($nomeMes) ?></h2>
            <a class="btn btn-ghost" href="<?= $baseAgenda ?>&ano=<?= $proximo->format('Y') ?>&mes=<?= $proximo->format('n') ?>">Próximo</a>
        </div>
        <p class="panel-lead">Clique no dia para ver as atividades ou cadastrar uma nova.</p>
        <div class="cal-grid">
            <?php foreach ($diasSemana as $dia): ?>
                <div class="cal-dow"><?= $dia ?></div>
            <?php endforeach; ?>
            <?php for ($i = 1; $i < $inicioSemana; $i++): ?>
                <div class="cal-day is-empty"></div>
            <?php endfor; ?>
            <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                <?php
                $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                $eventosDoDia = $porData[$data] ?? [];
                $ehHoje = $data === $hoje->format('Y-m-d');
                $resumo = [];
                foreach ($eventosDoDia as $evento) {
                    $resumo[] = [
                        'id'         => (int) $evento['id'],
                        'titulo'     => $evento['titulo'],
                        'tipo'       => $evento['tipo'],
                        'hora'       => formatarHora($evento['hora']),
                        'turma_id'   => (int) $evento['turma_id'],
                        'turma_nome'    => $evento['turma_nome'],
                        'concluido'     => (int) $evento['concluido'],
                        'pode_concluir' => true,
                    ];
                }
                $nova = BASE_URL . '/professor/adicionar_evento.php?data=' . $data;
                ?>
                <div class="cal-day <?= $ehHoje ? 'is-today' : '' ?> <?= $eventosDoDia ? 'has-events' : '' ?>">
                    <button
                        type="button"
                        class="cal-num is-button"
                        data-modal="dia"
                        data-dia="<?= e(formatarData($data)) ?>"
                        data-nova="<?= e($nova) ?>"
                        data-itens="<?= e(json_encode($resumo, JSON_UNESCAPED_UNICODE)) ?>"
                    ><?= $dia ?></button>
                    <?php foreach ($eventosDoDia as $evento): ?>
                        <button class="cal-event <?= $evento['concluido'] ? 'is-done' : 'is-pending' ?> tipo-<?= e($evento['tipo']) ?>" type="button" data-modal="evento" data-id="<?= (int) $evento['id'] ?>" title="<?= e($evento['titulo']) ?>">
                            <?= e($evento['titulo']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>
<?php else: ?>
<section class="panel">
    <h2>Atividades</h2>

    <?php if (!$eventos): ?>
        <div class="empty-state">
            <p>Nenhuma atividade cadastrada ainda.</p>
            <a class="btn btn-stamp empty-action" href="<?= BASE_URL ?>/professor/adicionar_evento.php">Nova atividade</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Data</th>
                        <th>Turma</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventos as $evento): ?>
                        <tr class="<?= $evento['concluido'] ? 'is-done' : 'is-pending' ?>">
                            <td data-label="Título">
                                <button class="link-modal" type="button" data-modal="evento" data-id="<?= (int) $evento['id'] ?>">
                                    <?= e($evento['titulo']) ?>
                                </button>
                            </td>
                            <td data-label="Tipo"><span class="chip chip-<?= e($evento['tipo']) ?>"><?= e(rotuloTipo($evento['tipo'])) ?></span></td>
                            <td data-label="Data">
                                <?= formatarDataCurta($evento['data_evento']) ?>
                                <?= $evento['hora'] ? ' · ' . formatarHora($evento['hora']) : '' ?>
                            </td>
                            <td data-label="Turma">
                                <button class="link-modal" type="button" data-modal="turma" data-id="<?= (int) $evento['turma_id'] ?>">
                                    <?= e($evento['turma_nome']) ?>
                                </button>
                            </td>
                            <td data-label="Ações">
                                <div class="row-actions">
                                    <form method="post" action="<?= BASE_URL ?>/professor/concluir_evento.php">
                                        <input type="hidden" name="id" value="<?= (int) $evento['id'] ?>">
                                        <input type="hidden" name="voltar" value="<?= e($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/professor/eventos.php')) ?>">
                                        <button class="btn btn-concluir<?= $evento['concluido'] ? ' is-done' : '' ?>" type="submit" aria-label="<?= $evento['concluido'] ? 'Desmarcar conclusão' : 'Marcar como concluída' ?>" title="<?= $evento['concluido'] ? 'Desmarcar conclusão' : 'Marcar como concluída' ?>">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.6 9.2 16.8 19 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </button>
                                    </form>
                                    <a class="btn btn-ghost" href="<?= BASE_URL ?>/professor/editar_evento.php?id=<?= (int) $evento['id'] ?>">Editar</a>
                                    <form method="post" action="<?= BASE_URL ?>/professor/excluir_evento.php" data-confirm="Excluir esta atividade?">
                                        <input type="hidden" name="id" value="<?= (int) $evento['id'] ?>">
                                        <button class="btn btn-danger" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
