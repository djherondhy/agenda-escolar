<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAluno();
header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$aluno = obterAlunoPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$aluno) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$atividade = obterAtividadePessoal($pdo, (int) ($_GET['id'] ?? 0), (int) $aluno['id']);

if (!$atividade) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode([
    'ok'          => true,
    'id'          => (int) $atividade['id'],
    'titulo'      => $atividade['titulo'],
    'descricao'   => $atividade['descricao'],
    'data_evento' => $atividade['data_evento'],
    'hora'        => formatarHora($atividade['hora']),
    'materia'     => $atividade['materia'],
    'concluido'   => (bool) $atividade['concluido'],
    'prazo'       => rotuloPrazo($atividade['data_evento']),
]);
