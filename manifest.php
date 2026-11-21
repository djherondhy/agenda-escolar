<?php

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$base = BASE_URL;

echo json_encode([
    'name'             => 'Agenda Acadêmica',
    'short_name'       => 'Agenda',
    'description'      => 'Prazos, provas e atividades da sua turma.',
    'lang'             => 'pt-BR',
    'start_url'        => $base . '/login.php',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'background_color' => '#f6f7fb',
    'theme_color'      => '#1d4ed8',
    'icons'            => [
        ['src' => $base . '/assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/assets/img/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
