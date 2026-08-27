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

$stmt = $pdo->prepare(
    'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora, e.turma_id, t.nome AS turma_nome
     FROM eventos e
     INNER JOIN turmas t ON t.id = e.turma_id
     WHERE e.professor_id = :professor_id
     ORDER BY e.data_evento ASC, e.hora ASC'
);
$stmt->execute(['professor_id' => $professor['id']]);
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
                        <tr>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
