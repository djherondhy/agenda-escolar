<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAluno();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/aluno/atividades.php?origem=pessoais');
}

$pdo = getPDO();
$aluno = obterAlunoPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$aluno) {
    encerrarSessao();
    redirecionar('/login.php');
}

$id = (int) ($_POST['id'] ?? 0);
$atividade = obterAtividadePessoal($pdo, $id, (int) $aluno['id']);

if ($atividade) {
    $novo = $atividade['concluido'] ? 0 : 1;
    $stmt = $pdo->prepare(
        'UPDATE aluno_atividades
         SET concluido = :concluido, data_conclusao = :data_conclusao
         WHERE id = :id AND aluno_id = :aluno_id'
    );
    $stmt->execute([
        'concluido'      => $novo,
        'data_conclusao' => $novo ? date('Y-m-d H:i:s') : null,
        'id'             => $id,
        'aluno_id'       => $aluno['id'],
    ]);
}

$voltar = destinovoltarAluno($_POST['voltar'] ?? '');
header('Location: ' . $voltar);
exit;
