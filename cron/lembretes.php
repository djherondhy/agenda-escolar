<?php

/**
 * Lembrete do dia anterior.
 *
 * Na Hostinger, crie um Cron Job do tipo PHP, uma vez por dia (por exemplo 08:00):
 *   php /home/SEU_USUARIO/domains/academico.wararadev.com/public_html/cron/lembretes.php
 *
 * Também aceita uma URL, se o painel só tiver wget/curl:
 *   https://academico.wararadev.com/cron/lembretes.php?token=O_TOKEN
 * O token fica em config/cron.php (gerado na primeira execução).
 *
 * O site também envia esse lembrete na primeira visita do dia, então o cron
 * cobre o caso em que ninguém abre a Agenda antes da aula.
 */

function tokenCron(): string
{
    $arquivo = dirname(__DIR__) . '/config/cron.php';
    if (is_file($arquivo)) {
        $token = include $arquivo;
        if (is_string($token) && $token !== '') {
            return $token;
        }
    }

    $token = bin2hex(random_bytes(16));
    $conteudo = "<?php\nreturn " . var_export($token, true) . ";\n";
    if (@file_put_contents($arquivo, $conteudo) === false) {
        return '';
    }

    return $token;
}

if (PHP_SAPI !== 'cli') {
    $informado = (string) ($_GET['token'] ?? '');
    $esperado = tokenCron();
    if ($esperado === '' || $informado === '' || !hash_equals($esperado, $informado)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Acesso negado.\n";
        exit;
    }
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/avisos.php';

$enviados = enviarLembretesVespera(getPDO());
echo 'Lembretes da véspera enviados: ' . $enviados . "\n";
