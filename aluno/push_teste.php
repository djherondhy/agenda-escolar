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
$inscricoes = inscricoesDoUsuario($pdo, $usuarioId);
if (!$inscricoes) {
    $aluno = obterAlunoPorUsuario($pdo, $usuarioId);
    if ($aluno) {
        $stmt = $pdo->prepare('SELECT id, endpoint, p256dh, auth FROM push_inscricoes WHERE aluno_id = :aluno_id');
        $stmt->execute(['aluno_id' => (int) $aluno['id']]);
        $inscricoes = $stmt->fetchAll();
    }
}

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
