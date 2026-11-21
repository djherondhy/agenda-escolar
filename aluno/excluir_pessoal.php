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
$existente = obterAtividadePessoal($pdo, $id, (int) $aluno['id']);

if ($existente) {
    foreach (listarAnexosDaLinha($pdo, 'aluno_atividade_anexos', 'atividade_id', $id) as $anexo) {
        apagarArquivoUpload($anexo['caminho']);
    }
    $stmt = $pdo->prepare('DELETE FROM aluno_atividades WHERE id = :id AND aluno_id = :aluno_id');
    $stmt->execute([
        'id'       => $id,
        'aluno_id' => $aluno['id'],
    ]);
}

redirecionar('/aluno/atividades.php?origem=pessoais&excluido=1');
