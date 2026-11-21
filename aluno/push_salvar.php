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

$pdo = getPDO();
$aluno = obterAlunoPorUsuario($pdo, (int) $_SESSION['usuario_id']);
$dados = json_decode(file_get_contents('php://input') ?: '', true);

if (!$aluno || !is_array($dados)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

// Desativar avisos neste aparelho.
if (($dados['acao'] ?? '') === 'remover') {
    $stmt = $pdo->prepare('DELETE FROM push_inscricoes WHERE aluno_id = :aluno_id AND endpoint = :endpoint');
    $stmt->execute(['aluno_id' => (int) $aluno['id'], 'endpoint' => (string) ($dados['endpoint'] ?? '')]);
    echo json_encode(['ok' => true]);
    exit;
}

if (!salvarInscricaoPush($pdo, (int) $aluno['id'], $dados)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true]);
