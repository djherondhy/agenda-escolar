<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirProfessor();

$pdo = getPDO();
$professor = obterProfessorPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$professor) {
    encerrarSessao();
    redirecionar('/login.php');
}

$turmas = listarTurmasDoProfessor($pdo, (int) $professor['id']);
$erros = [];
$dados = [
    'titulo'      => '',
    'descricao'   => '',
    'tipo'        => '',
    'turma_id'    => '',
    'data_evento' => '',
    'hora'        => '',
    'materia'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validacao = validarEvento($_POST, $turmas);
    $erros = $validacao['erros'];
    $dados = array_merge($dados, $_POST);

    if (!$erros) {
        $sql = 'INSERT INTO eventos (professor_id, turma_id, titulo, descricao, tipo, data_evento, hora, materia)
                VALUES (:professor_id, :turma_id, :titulo, :descricao, :tipo, :data_evento, :hora, :materia)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'professor_id' => $professor['id'],
            'turma_id'     => $validacao['dados']['turma_id'],
            'titulo'       => $validacao['dados']['titulo'],
            'descricao'    => $validacao['dados']['descricao'],
            'tipo'         => $validacao['dados']['tipo'],
            'data_evento'  => $validacao['dados']['data_evento'],
            'hora'         => $validacao['dados']['hora'],
            'materia'      => $validacao['dados']['materia'],
        ]);

        $eventoId = (int) $pdo->lastInsertId();
        if (!empty($_FILES['anexos'])) {
            salvarAnexosEvento($pdo, $eventoId, $_FILES['anexos'], $erros);
        }

        redirecionar('/professor/eventos.php?ok=1');
    }
}

$tituloPagina = 'Nova atividade';
$menuAtivo = 'eventos';
$usarEditor = true;
$action = BASE_URL . '/professor/adicionar_evento.php';
$botaoTexto = 'Salvar atividade';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <h2>Cadastrar atividade</h2>
    <p class="panel-lead">Descreva o que a turma precisa fazer, defina o prazo e anexe materiais.</p>

    <?php foreach ($erros as $erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <?php if (!$turmas): ?>
        <p class="empty">Você precisa estar associado a uma turma para cadastrar atividades.</p>
    <?php else: ?>
        <?php require __DIR__ . '/../includes/evento_form.php'; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
