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

$voltar = destinovoltarAluno($_POST['voltar'] ?? '');
$disciplinas = listarDisciplinasDaTurma($pdo, (int) $aluno['turma_id']);
$validacao = validarAtividadePessoal($_POST, $disciplinas);

if ($validacao['erros']) {
    redirecionar('/aluno/atividades.php?origem=pessoais&erro=1');
}

$dados = $validacao['dados'];
$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    $existente = obterAtividadePessoal($pdo, $id, (int) $aluno['id']);
    if (!$existente) {
        redirecionar('/aluno/atividades.php?origem=pessoais');
    }

    $stmt = $pdo->prepare(
        'UPDATE aluno_atividades
         SET titulo = :titulo, descricao = :descricao, data_evento = :data_evento, hora = :hora, materia = :materia
         WHERE id = :id AND aluno_id = :aluno_id'
    );
    $stmt->execute([
        'titulo'      => $dados['titulo'],
        'descricao'   => $dados['descricao'],
        'data_evento' => $dados['data_evento'],
        'hora'        => $dados['hora'],
        'materia'     => $dados['materia'],
        'id'          => $id,
        'aluno_id'    => $aluno['id'],
    ]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO aluno_atividades (aluno_id, titulo, descricao, data_evento, hora, materia)
         VALUES (:aluno_id, :titulo, :descricao, :data_evento, :hora, :materia)'
    );
    $stmt->execute([
        'aluno_id'    => $aluno['id'],
        'titulo'      => $dados['titulo'],
        'descricao'   => $dados['descricao'],
        'data_evento' => $dados['data_evento'],
        'hora'        => $dados['hora'],
        'materia'     => $dados['materia'],
    ]);
}

if (str_contains($voltar, '?')) {
    header('Location: ' . $voltar . (str_contains($voltar, 'ok=') ? '' : '&ok=pessoal'));
} else {
    header('Location: ' . $voltar . '?ok=pessoal');
}
exit;
