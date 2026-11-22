<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/push.php';

exigirEscola();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = getPDO();
$usuarioId = (int) $_SESSION['usuario_id'];
$dados = json_decode(file_get_contents('php://input') ?: '', true);

if (!is_array($dados)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

if (($dados['acao'] ?? '') === 'remover') {
    $aluno = obterAlunoPorUsuario($pdo, $usuarioId);
    $stmt = $pdo->prepare(
        'DELETE FROM push_inscricoes
         WHERE endpoint = :endpoint AND (usuario_id = :usuario_id OR aluno_id = :aluno_id)'
    );
    $stmt->execute([
        'endpoint'   => (string) ($dados['endpoint'] ?? ''),
        'usuario_id' => $usuarioId,
        'aluno_id'   => $aluno ? (int) $aluno['id'] : 0,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if (!salvarInscricaoPush($pdo, $usuarioId, $dados)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true]);
