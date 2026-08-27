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

$hoje = new DateTime('today');
$ano = (int) ($_GET['ano'] ?? $hoje->format('Y'));
$mes = (int) ($_GET['mes'] ?? $hoje->format('n'));

if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
    $ano = (int) $hoje->format('Y');
    $mes = (int) $hoje->format('n');
}

$inicioMes = new DateTime(sprintf('%04d-%02d-01', $ano, $mes));
$diasNoMes = (int) $inicioMes->format('t');
$anterior = (clone $inicioMes)->modify('-1 month');
$proximo = (clone $inicioMes)->modify('+1 month');
$inicioSemana = (int) $inicioMes->format('N');

$stmt = $pdo->prepare(
    'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora, e.turma_id, t.nome AS turma_nome
     FROM eventos e
     INNER JOIN turmas t ON t.id = e.turma_id
     WHERE e.professor_id = :professor_id
       AND MONTH(e.data_evento) = :mes
       AND YEAR(e.data_evento) = :ano
     ORDER BY e.data_evento ASC, e.hora ASC'
);
$stmt->execute([
    'professor_id' => $professor['id'],
    'mes'          => $mes,
    'ano'          => $ano,
]);
$eventos = $stmt->fetchAll();
$porData = agruparEventosPorData($eventos);

$meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$nomeMes = ($meses[$mes] ?? $inicioMes->format('m')) . ' ' . $ano;
$diasSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

$tituloPagina = 'Horários';
$menuAtivo = 'horarios';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <div class="cal-head">
        <a class="btn btn-ghost" href="<?= BASE_URL ?>/professor/horarios.php?ano=<?= $anterior->format('Y') ?>&mes=<?= $anterior->format('n') ?>">Anterior</a>
        <h2 class="cal-month"><?= e($nomeMes) ?></h2>
        <a class="btn btn-ghost" href="<?= BASE_URL ?>/professor/horarios.php?ano=<?= $proximo->format('Y') ?>&mes=<?= $proximo->format('n') ?>">Próximo</a>
    </div>
    <p class="panel-lead"><?= $eventos ? 'Clique no dia para ver as atividades, a turma e o horário.' : 'Nenhuma atividade neste mês.' ?></p>

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
                    'turma_nome' => $evento['turma_nome'],
                ];
            }
            ?>
            <button
                type="button"
                class="cal-day <?= $ehHoje ? 'is-today' : '' ?> <?= $eventosDoDia ? 'has-events' : '' ?>"
                data-modal="dia"
                data-dia="<?= e(formatarData($data)) ?>"
                data-itens="<?= e(json_encode($resumo, JSON_UNESCAPED_UNICODE)) ?>"
            >
                <span class="cal-num"><?= $dia ?></span>
                <?php if ($eventosDoDia): ?>
                    <span class="cal-count"><?= count($eventosDoDia) ?> atividade<?= count($eventosDoDia) === 1 ? '' : 's' ?></span>
                <?php endif; ?>
                <?php foreach ($eventosDoDia as $evento): ?>
                    <span class="cal-event tipo-<?= e($evento['tipo']) ?>">
                        <?= e($evento['titulo']) ?>
                        <small><?= e($evento['turma_nome']) ?><?= $evento['hora'] ? ' · ' . formatarHora($evento['hora']) : '' ?></small>
                    </span>
                <?php endforeach; ?>
            </button>
        <?php endfor; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
