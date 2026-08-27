<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$usuario = usuarioAtual();
$evento = usuarioPodeVerEvento(getPDO(), $usuario, (int) ($_GET['id'] ?? 0));

if (!$evento) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = getPDO();
$anexos = [];

foreach (listarAnexos($pdo, (int) $evento['id']) as $anexo) {
    $anexos[] = [
        'id'       => (int) $anexo['id'],
        'nome'     => $anexo['nome_original'],
        'url'      => UPLOAD_URL . '/' . $anexo['caminho'],
        'imagem'   => anexoEhImagem($anexo['tipo_mime']),
        'tamanho'  => (int) $anexo['tamanho'],
    ];
}

echo json_encode([
    'ok'           => true,
    'id'           => (int) $evento['id'],
    'titulo'       => $evento['titulo'],
    'tipo'         => $evento['tipo'],
    'tipo_rotulo'  => rotuloTipo($evento['tipo']),
    'data'         => formatarData($evento['data_evento']),
    'prazo'        => rotuloPrazo($evento['data_evento']),
    'hora'         => formatarHora($evento['hora']),
    'turma_id'     => (int) $evento['turma_id'],
    'turma_nome'   => $evento['turma_nome'],
    'professor'    => $evento['professor_nome'],
    'descricao'    => descricaoHtml($evento['descricao']),
    'anexos'       => $anexos,
    'pode_editar'  => !empty($evento['pode_editar']),
    'concluido'    => !empty($evento['concluido']),
    'eh_aluno'     => $usuario['tipo'] === 'aluno',
]);
