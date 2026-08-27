<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAluno();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/aluno/atividades.php');
}

$pdo = getPDO();
$aluno = obterAlunoPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$aluno) {
    encerrarSessao();
    redirecionar('/login.php');
}

$eventoId = (int) ($_POST['id'] ?? 0);
$evento = obterEventoDaTurma($pdo, $eventoId, (int) $aluno['turma_id']);

if ($evento) {
    alternarConclusao($pdo, (int) $aluno['id'], $eventoId);
}

$voltar = $_POST['voltar'] ?? '';
$destinoPermitido = BASE_URL . '/aluno/';

if ($voltar !== '' && str_starts_with($voltar, $destinoPermitido)) {
    header('Location: ' . $voltar);
    exit;
}

redirecionar('/aluno/atividade.php?id=' . $eventoId);
