<?php

/**
 * Carrega o arquivo .env (se existir) sem sobrescrever variáveis
 * já definidas no ambiente do servidor (painel de hospedagem, Docker, etc.).
 */
function carregarEnv(string $arquivo): void
{
    if (!is_readable($arquivo)) {
        return;
    }

    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) {
            continue;
        }

        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);

        if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
            $valor = substr($valor, 1, -1);
        }

        if (getenv($chave) === false && !isset($_ENV[$chave])) {
            putenv($chave . '=' . $valor);
            $_ENV[$chave] = $valor;
        }
    }
}

function env(string $chave, ?string $padrao = null): ?string
{
    $valor = getenv($chave);
    if ($valor === false || $valor === '') {
        $valor = $_ENV[$chave] ?? $_SERVER[$chave] ?? false;
    }

    return ($valor === false || $valor === '') ? $padrao : (string) $valor;
}

/**
 * Descobre as credenciais do banco automaticamente, nesta ordem:
 *  1. URL completa: DATABASE_URL, MYSQL_URL, JAWSDB_URL, CLEARDB_DATABASE_URL
 *  2. Variáveis DB_*
 *  3. Variáveis MYSQL* (Railway, Render, etc.) e MYSQL_ADDON_* (Clever Cloud)
 *  4. Padrão local do XAMPP
 */
function configurarBanco(): array
{
    $cfg = [
        'host'    => 'localhost',
        'port'    => '3306',
        'name'    => 'calendario_estudantil',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ];

    $url = env('DATABASE_URL') ?? env('MYSQL_URL') ?? env('JAWSDB_URL') ?? env('CLEARDB_DATABASE_URL');
    if ($url && ($p = parse_url($url)) && !empty($p['host'])) {
        $cfg['host'] = $p['host'];
        $cfg['port'] = (string) ($p['port'] ?? $cfg['port']);
        $cfg['user'] = isset($p['user']) ? urldecode($p['user']) : $cfg['user'];
        $cfg['pass'] = isset($p['pass']) ? urldecode($p['pass']) : $cfg['pass'];
        $cfg['name'] = isset($p['path']) ? ltrim($p['path'], '/') : $cfg['name'];
        return $cfg;
    }

    $mapa = [
        'host' => ['DB_HOST', 'MYSQLHOST', 'MYSQL_ADDON_HOST'],
        'port' => ['DB_PORT', 'MYSQLPORT', 'MYSQL_ADDON_PORT'],
        'name' => ['DB_NAME', 'MYSQLDATABASE', 'MYSQL_ADDON_DB'],
        'user' => ['DB_USER', 'MYSQLUSER', 'MYSQL_ADDON_USER'],
        'pass' => ['DB_PASS', 'MYSQLPASSWORD', 'MYSQL_ADDON_PASSWORD'],
    ];

    foreach ($mapa as $campo => $chaves) {
        foreach ($chaves as $chave) {
            $valor = env($chave);
            if ($valor !== null) {
                $cfg[$campo] = $valor;
                break;
            }
        }
    }

    $cfg['charset'] = env('DB_CHARSET', $cfg['charset']);

    return $cfg;
}

/**
 * BASE_URL: usa APP_BASE_URL se definido; senão, em localhost usa /c-estudantil
 * e em produção detecta a pasta onde o projeto está publicado.
 */
function detectarBaseUrl(): string
{
    $definido = env('APP_BASE_URL');
    if ($definido !== null) {
        return rtrim($definido, '/');
    }

    $raiz = realpath(dirname(__DIR__));
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

    if ($raiz && $docRoot && strpos($raiz, $docRoot) === 0) {
        return rtrim(str_replace('\\', '/', substr($raiz, strlen($docRoot))), '/');
    }

    return '/c-estudantil';
}
