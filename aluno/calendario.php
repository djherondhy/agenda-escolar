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
    'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora, e.descricao,
            COALESCE(ae.concluido, 0) AS concluido
     FROM eventos e
     LEFT JOIN aluno_eventos ae ON ae.evento_id = e.id AND ae.aluno_id = :aluno_id
     WHERE e.turma_id = :turma_id
       AND MONTH(e.data_evento) = :mes
       AND YEAR(e.data_evento) = :ano
     ORDER BY e.data_evento ASC, e.hora ASC'
);
$stmt->execute([
    'aluno_id' => $aluno['id'],
    'turma_id' => $aluno['turma_id'],
    'mes'      => $mes,
    'ano'      => $ano,
]);
$eventos = $stmt->fetchAll();

$pessoais = $pdo->prepare(
    'SELECT id, titulo, data_evento, hora, materia, concluido
     FROM aluno_atividades
     WHERE aluno_id = :aluno_id
       AND MONTH(data_evento) = :mes
       AND YEAR(data_evento) = :ano
     ORDER BY data_evento ASC, hora ASC'
);
$pessoais->execute([
    'aluno_id' => $aluno['id'],
    'mes'      => $mes,
    'ano'      => $ano,
]);

foreach ($pessoais->fetchAll() as $atividade) {
    $atividade['tipo'] = 'pessoal';
    $eventos[] = $atividade;
}

$porData = agruparEventosPorData($eventos);

$meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$nomeMes = ($meses[$mes] ?? $inicioMes->format('m')) . ' ' . $ano;
$diasSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

$tituloPagina = 'Calendário';
$menuAtivo = 'calendario';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <div class="cal-head">
        <a class="btn btn-ghost" href="<?= BASE_URL ?>/aluno/calendario.php?ano=<?= $anterior->format('Y') ?>&mes=<?= $anterior->format('n') ?>">Anterior</a>
        <h2 class="cal-month"><?= e($nomeMes) ?></h2>
        <a class="btn btn-ghost" href="<?= BASE_URL ?>/aluno/calendario.php?ano=<?= $proximo->format('Y') ?>&mes=<?= $proximo->format('n') ?>">Próximo</a>
    </div>

    <?php if (!$eventos): ?>
        <p class="panel-lead">Nenhuma atividade da turma ou pessoal neste mês.</p>
    <?php endif; ?>

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
            ?>
            <?php
            $resumo = [];
            foreach ($eventosDoDia as $evento) {
                $resumo[] = [
                    'id'         => (int) $evento['id'],
                    'titulo'     => $evento['titulo'],
                    'tipo'       => $evento['tipo'],
                    'hora'       => formatarHora($evento['hora']),
                    'turma_id'   => (int) $aluno['turma_id'],
                    'turma_nome' => $evento['tipo'] === 'pessoal' ? ($evento['materia'] ?: 'Pessoal') : $aluno['turma_nome'],
                    'origem'     => $evento['tipo'] === 'pessoal' ? 'pessoal' : 'turma',
                ];
            }
            ?>
            <div class="cal-day <?= $ehHoje ? 'is-today' : '' ?> <?= $eventosDoDia ? 'has-events' : '' ?>">
                <button
                    type="button"
                    class="cal-num <?= $eventosDoDia ? 'is-button' : '' ?>"
                    <?php if ($eventosDoDia): ?>
                        data-modal="dia"
                        data-dia="<?= e(formatarData($data)) ?>"
                        data-itens="<?= e(json_encode($resumo, JSON_UNESCAPED_UNICODE)) ?>"
                    <?php endif; ?>
                ><?= $dia ?></button>
                <?php foreach ($eventosDoDia as $evento): ?>
                    <button class="cal-event tipo-<?= $evento['tipo'] === 'pessoal' ? 'outro' : e($evento['tipo']) ?>"
                       type="button"
                       data-modal="<?= $evento['tipo'] === 'pessoal' ? 'pessoal' : 'evento' ?>"
                       data-id="<?= (int) $evento['id'] ?>"
                       title="<?= e($evento['titulo']) ?>">
                        <?= e($evento['titulo']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
