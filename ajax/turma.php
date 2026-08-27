<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$usuario = usuarioAtual();
$turmaId = (int) ($_GET['id'] ?? 0);
$pdo = getPDO();

if (!$turmaId || !usuarioPodeVerTurma($pdo, $usuario, $turmaId)) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $pdo->prepare('SELECT id, nome, ano FROM turmas WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $turmaId]);
$turma = $stmt->fetch();

if (!$turma) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$alunos = $pdo->prepare('SELECT COUNT(*) FROM alunos WHERE turma_id = :turma_id');
$alunos->execute(['turma_id' => $turmaId]);

$atividades = $pdo->prepare('SELECT COUNT(*) FROM eventos WHERE turma_id = :turma_id');
$atividades->execute(['turma_id' => $turmaId]);

$professores = $pdo->prepare(
    'SELECT u.nome,
            GROUP_CONCAT(DISTINCT NULLIF(pt.materia, "") ORDER BY pt.materia SEPARATOR ", ") AS materias
     FROM professor_turmas pt
     INNER JOIN professores p ON p.id = pt.professor_id
     INNER JOIN usuarios u ON u.id = p.usuario_id
     WHERE pt.turma_id = :turma_id
     GROUP BY u.id, u.nome
     ORDER BY u.nome'
);
$professores->execute(['turma_id' => $turmaId]);

$proximas = $pdo->prepare(
    'SELECT id, titulo, tipo, data_evento, hora
     FROM eventos
     WHERE turma_id = :turma_id
     ORDER BY data_evento ASC, hora ASC
     LIMIT 6'
);
$proximas->execute(['turma_id' => $turmaId]);

$lista = [];
foreach ($proximas->fetchAll() as $evento) {
    $lista[] = [
        'id'     => (int) $evento['id'],
        'titulo' => $evento['titulo'],
        'tipo'   => $evento['tipo'],
        'data'   => formatarData($evento['data_evento']),
        'prazo'  => rotuloPrazo($evento['data_evento']),
        'hora'   => formatarHora($evento['hora']),
    ];
}

echo json_encode([
    'ok'            => true,
    'id'            => (int) $turma['id'],
    'nome'          => $turma['nome'],
    'ano'           => $turma['ano'],
    'total_alunos'  => (int) $alunos->fetchColumn(),
    'total_eventos' => (int) $atividades->fetchColumn(),
    'professores'   => array_map(static function (array $linha): string {
        $nome = $linha['nome'];
        return $linha['materias'] ? $nome . ' · ' . $linha['materias'] : $nome;
    }, $professores->fetchAll()),
    'proximas'      => $lista,
]);
