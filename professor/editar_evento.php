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

$eventoId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$evento = obterEventoDoProfessor($pdo, $eventoId, (int) $professor['id']);

if (!$evento) {
    redirecionar('/professor/eventos.php');
}

$turmas = listarTurmasDoProfessor($pdo, (int) $professor['id']);
$anexos = listarAnexos($pdo, $eventoId);
$erros = [];
$dados = [
    'titulo'      => $evento['titulo'],
    'descricao'   => $evento['descricao'],
    'tipo'        => $evento['tipo'],
    'turma_id'    => $evento['turma_id'],
    'data_evento' => $evento['data_evento'],
    'hora'        => formatarHora($evento['hora']),
    'materia'     => $evento['materia'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validacao = validarEvento($_POST, $turmas);
    $erros = $validacao['erros'];
    $dados = array_merge($dados, $_POST);

    if (!$erros) {
        $sql = 'UPDATE eventos
                SET turma_id = :turma_id,
                    titulo = :titulo,
                    descricao = :descricao,
                    tipo = :tipo,
                    data_evento = :data_evento,
                    hora = :hora,
                    materia = :materia
                WHERE id = :id AND professor_id = :professor_id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'turma_id'     => $validacao['dados']['turma_id'],
            'titulo'       => $validacao['dados']['titulo'],
            'descricao'    => $validacao['dados']['descricao'],
            'tipo'         => $validacao['dados']['tipo'],
            'data_evento'  => $validacao['dados']['data_evento'],
            'hora'         => $validacao['dados']['hora'],
            'materia'      => $validacao['dados']['materia'],
            'id'           => $eventoId,
            'professor_id' => $professor['id'],
        ]);

        removerAnexosEvento($pdo, $eventoId, $_POST['remover_anexos'] ?? []);

        if (!empty($_FILES['anexos'])) {
            salvarAnexosEvento($pdo, $eventoId, $_FILES['anexos'], $erros);
        }

        redirecionar('/professor/eventos.php?ok=1');
    }
}

$tituloPagina = 'Editar atividade';
$menuAtivo = 'eventos';
$usarEditor = true;
$action = BASE_URL . '/professor/editar_evento.php?id=' . $eventoId;
$botaoTexto = 'Salvar alterações';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <h2>Editar atividade</h2>
    <p class="panel-lead">Atualize o texto, o prazo ou os arquivos desta atividade.</p>

    <?php foreach ($erros as $erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <?php require __DIR__ . '/../includes/evento_form.php'; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
