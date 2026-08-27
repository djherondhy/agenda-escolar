<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/admin/turmas.php');
}

$pdo = getPDO();
$turmaId = (int) ($_POST['id'] ?? 0);
$turma = obterTurma($pdo, $turmaId);

if (!$turma) {
    redirecionar('/admin/turmas.php');
}

$alunos = $pdo->prepare('SELECT COUNT(*) FROM alunos WHERE turma_id = :turma_id');
$alunos->execute(['turma_id' => $turmaId]);

if ((int) $alunos->fetchColumn() > 0) {
    redirecionar('/admin/turma.php?id=' . $turmaId . '&erro=alunos');
}

$eventos = $pdo->prepare('SELECT id FROM eventos WHERE turma_id = :turma_id');
$eventos->execute(['turma_id' => $turmaId]);

foreach ($eventos->fetchAll() as $evento) {
    excluirArquivosEvento($pdo, (int) $evento['id']);
}

$delete = $pdo->prepare('DELETE FROM turmas WHERE id = :id');
$delete->execute(['id' => $turmaId]);

redirecionar('/admin/turmas.php?excluido=1');
