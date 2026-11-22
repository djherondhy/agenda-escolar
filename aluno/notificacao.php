<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirEscola();

$pdo = getPDO();
$caminho = marcarNotificacaoLida($pdo, (int) ($_GET['id'] ?? 0), (int) $_SESSION['usuario_id']);
if ($caminho === null || $caminho === '' || $caminho[0] !== '/') {
    redirecionar('/aluno/notificacoes.php');
}

redirecionar($caminho);
