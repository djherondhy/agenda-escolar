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
if (!$aluno) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensagem' => 'Conta de aluno não encontrada.']);
    exit;
}

$inscricoes = inscricoesDoAluno($pdo, (int) $aluno['id']);
if (!$inscricoes) {
    echo json_encode(['ok' => false, 'mensagem' => 'Nenhum aparelho ativado. Toque em "Ativar avisos" primeiro.']);
    exit;
}

try {
    $statuses = dispararPush($pdo, $inscricoes, montarPayloadPush(
        'Teste de aviso',
        'Pronto! Os avisos da Agenda Acadêmica chegam neste aparelho.',
        '/aluno/notificacoes.php',
        'teste'
    ));
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'mensagem' => 'O servidor não conseguiu preparar o aviso (OpenSSL/cURL).']);
    exit;
}

$entregues = count(array_filter($statuses, static fn (int $s): bool => $s >= 200 && $s < 300));
echo json_encode([
    'ok'        => $entregues > 0,
    'enviados'  => $entregues,
    'total'     => count($statuses),
    'status'    => $statuses,
    'mensagem'  => $entregues > 0
        ? 'Aviso de teste enviado. Ele deve aparecer em instantes.'
        : 'O serviço de avisos recusou o envio (código ' . implode(', ', $statuses) . ').',
]);
