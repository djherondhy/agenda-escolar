<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirProfessor();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['imagem'])) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$erros = [];
$salvo = salvarArquivoEnviado($_FILES['imagem'], 'editor', $erros);

if (!$salvo || !anexoEhImagem($salvo['tipo_mime'])) {
    if ($salvo && is_file(UPLOAD_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $salvo['caminho']))) {
        unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $salvo['caminho']));
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => $erros[0] ?? 'Envie uma imagem válida.']);
    exit;
}

echo json_encode([
    'ok'  => true,
    'url' => UPLOAD_URL . '/' . $salvo['caminho'],
]);
