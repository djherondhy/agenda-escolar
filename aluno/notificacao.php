<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAluno();

$pdo = getPDO();
$aluno = obterAlunoPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$aluno) {
    encerrarSessao();
    redirecionar('/login.php');
}

$eventoId = marcarNotificacaoLida($pdo, (int) ($_GET['id'] ?? 0), (int) $aluno['id']);
if (!$eventoId) {
    redirecionar('/aluno/notificacoes.php');
}

redirecionar('/aluno/atividade.php?id=' . $eventoId);
