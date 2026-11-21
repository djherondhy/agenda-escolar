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
$stmt = $pdo->prepare(
    'SELECT id, concluido FROM eventos WHERE id = :id AND professor_id = :professor_id LIMIT 1'
);
$stmt->execute([
    'id'           => $eventoId,
    'professor_id' => $professor['id'],
]);
$evento = $stmt->fetch();

if ($evento) {
    $novo = $evento['concluido'] ? 0 : 1;
    $atualizar = $pdo->prepare('UPDATE eventos SET concluido = :concluido WHERE id = :id');
    $atualizar->execute([
        'concluido' => $novo,
        'id'        => $eventoId,
    ]);
}

$voltar = (string) ($_POST['voltar'] ?? '');
if ($voltar !== '' && str_starts_with(parse_url($voltar, PHP_URL_PATH) ?: '', BASE_URL . '/professor/')) {
    header('Location: ' . $voltar);
    exit;
}

redirecionar('/professor/eventos.php');
