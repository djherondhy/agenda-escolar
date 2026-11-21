<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/push.php';

exigirAluno();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$aluno = obterAlunoPorUsuario(getPDO(), (int) $_SESSION['usuario_id']);
$dados = json_decode(file_get_contents('php://input') ?: '', true);
if (!$aluno || !is_array($dados) || !salvarInscricaoPush(getPDO(), (int) $aluno['id'], $dados)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true]);
