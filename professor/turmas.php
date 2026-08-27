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

$sql = 'SELECT t.id, t.nome, t.ano,
               (SELECT COUNT(*) FROM alunos a WHERE a.turma_id = t.id) AS total_alunos,
               (SELECT COUNT(*) FROM eventos e WHERE e.turma_id = t.id AND e.professor_id = :professor_id) AS total_atividades,
               GROUP_CONCAT(DISTINCT NULLIF(pt.materia, "") ORDER BY pt.materia SEPARATOR ", ") AS materias
        FROM turmas t
        INNER JOIN professor_turmas pt ON pt.turma_id = t.id
        WHERE pt.professor_id = :professor_id2
        GROUP BY t.id, t.nome, t.ano
        ORDER BY t.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'professor_id'  => $professor['id'],
    'professor_id2' => $professor['id'],
]);
$turmas = $stmt->fetchAll();

$tituloPagina = 'Turmas';
$menuAtivo = 'turmas';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <h2>Minhas turmas</h2>

    <?php if (!$turmas): ?>
        <p class="empty">Você ainda não está associado a nenhuma turma.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Turma</th>
                        <th>Ano</th>
                        <th>Alunos</th>
                        <th>Suas matérias</th>
                        <th>Atividades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($turmas as $turma): ?>
                        <tr>
                            <td data-label="Turma">
                                <a href="<?= BASE_URL ?>/professor/turma.php?id=<?= (int) $turma['id'] ?>"><?= e($turma['nome']) ?></a>
                            </td>
                            <td data-label="Ano"><?= e($turma['ano'] ?: '—') ?></td>
                            <td data-label="Alunos"><?= (int) $turma['total_alunos'] ?></td>
                            <td data-label="Suas matérias"><?= e($turma['materias'] ?: '—') ?></td>
                            <td data-label="Atividades"><?= (int) $turma['total_atividades'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
