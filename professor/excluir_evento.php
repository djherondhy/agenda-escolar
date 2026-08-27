<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirProfessor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/professor/eventos.php');
}

$pdo = getPDO();
$professor = obterProfessorPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$professor) {
    encerrarSessao();
    redirecionar('/login.php');
}

$eventoId = (int) ($_POST['id'] ?? 0);
$evento = obterEventoDoProfessor($pdo, $eventoId, (int) $professor['id']);

if ($evento) {
    excluirArquivosEvento($pdo, $eventoId);
    $stmt = $pdo->prepare('DELETE FROM eventos WHERE id = :id AND professor_id = :professor_id');
    $stmt->execute([
        'id'           => $eventoId,
        'professor_id' => $professor['id'],
    ]);
}

redirecionar('/professor/eventos.php?excluido=1');
