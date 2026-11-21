<?php

function opcoesChaveEc(): array
{
    $opcoes = [
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ];
    foreach ([
        'C:/xampp/apache/conf/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/php/extras/openssl/openssl.cnf',
        getenv('OPENSSL_CONF') ?: '',
    ] as $caminho) {
        if ($caminho !== '' && is_file($caminho)) {
            $opcoes['config'] = str_replace('\\', '/', $caminho);
            break;
        }
    }

    return $opcoes;
}

function garantirChavesVapid(): array
{
    $arquivo = dirname(__DIR__) . '/config/vapid.php';
    if (is_file($arquivo)) {
        $chaves = include $arquivo;
        if (is_array($chaves) && !empty($chaves['public']) && !empty($chaves['private'])) {
            return $chaves;
        }
    }

    $opcoes = opcoesChaveEc();
    $chave = openssl_pkey_new($opcoes);
    if ($chave === false) {
        throw new RuntimeException('Não foi possível gerar a chave de aviso do celular.');
    }
    if (!openssl_pkey_export($chave, $privada, null, $opcoes)) {
        throw new RuntimeException('Não foi possível guardar a chave de aviso do celular.');
    }
    $detalhes = openssl_pkey_get_details($chave);
    $publica = "\x04"
        . str_pad($detalhes['ec']['x'], 32, "\0", STR_PAD_LEFT)
        . str_pad($detalhes['ec']['y'], 32, "\0", STR_PAD_LEFT);

    $conteudo = "<?php\nreturn " . var_export([
        'public' => base64Url($publica),
        'private' => $privada,
    ], true) . ";\n";
    file_put_contents($arquivo, $conteudo);

    return include $arquivo;
}

function base64Url(string $binario): string
{
    return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
}

function base64UrlDecode(string $texto): string
{
    $resto = strlen($texto) % 4;
    if ($resto) {
        $texto .= str_repeat('=', 4 - $resto);
    }
    return base64_decode(strtr($texto, '-_', '+/')) ?: '';
}

function enviarPushNovaAtividade(PDO $pdo, int $eventoId, int $turmaId): void
{
    if (!function_exists('curl_init') || !function_exists('openssl_pkey_new')) {
        return;
    }

    $evento = $pdo->prepare(
        'SELECT titulo, tipo FROM eventos WHERE id = :id LIMIT 1'
    );
    $evento->execute(['id' => $eventoId]);
    $linha = $evento->fetch();
    if (!$linha) {
        return;
    }

    $inscritos = $pdo->prepare(
        'SELECT s.id, s.endpoint, s.p256dh, s.auth
         FROM push_inscricoes s
         INNER JOIN alunos a ON a.id = s.aluno_id
         WHERE a.turma_id = :turma_id'
    );
    $inscritos->execute(['turma_id' => $turmaId]);
    $lista = $inscritos->fetchAll();
    if (!$lista) {
        return;
    }

    $chaves = garantirChavesVapid();
    $payload = json_encode([
        'title' => 'Agenda Acadêmica',
        'body'  => rotuloTipo($linha['tipo']) . ': ' . $linha['titulo'],
        'url'   => urlBaseDoPedido() . '/aluno/atividade.php?id=' . $eventoId,
    ], JSON_UNESCAPED_UNICODE);

    foreach ($lista as $inscricao) {
        $status = enviarUmPush($inscricao, $payload, $chaves);
        if ($status === 404 || $status === 410) {
            $apagar = $pdo->prepare('DELETE FROM push_inscricoes WHERE id = :id');
            $apagar->execute(['id' => $inscricao['id']]);
        }
    }
}

function urlBaseDoPedido(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $porta = (int) ($_SERVER['SERVER_PORT'] ?? ($https ? 443 : 80));
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $esquema = $https ? 'https' : 'http';

    return $esquema . '://' . $host . BASE_URL;
}

function enviarUmPush(array $inscricao, string $payload, array $chaves): int
{
    $publica = base64UrlDecode($inscricao['p256dh']);
    $auth = base64UrlDecode($inscricao['auth']);
    $corpo = criptografarPush($payload, $publica, $auth);
    if ($corpo === null) {
        return 0;
    }

    $partes = parse_url($inscricao['endpoint']);
    $aud = ($partes['scheme'] ?? 'https') . '://' . ($partes['host'] ?? '');
    $jwt = jwtVapid($aud, $chaves['private']);
    $curl = curl_init($inscricao['endpoint']);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $corpo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'TTL: 86400',
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Authorization: vapid t=' . $jwt . ', k=' . $chaves['public'],
            'Content-Length: ' . strlen($corpo),
        ],
    ]);
    curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return $status;
}

function jwtVapid(string $aud, string $privada): string
{
    $cabeca = base64Url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $corpo = base64Url(json_encode([
        'aud' => $aud,
        'exp' => time() + 12 * 3600,
        'sub' => 'mailto:admin@admin.com',
    ]));
    openssl_sign($cabeca . '.' . $corpo, $assinatura, $privada, OPENSSL_ALGO_SHA256);

    return $cabeca . '.' . $corpo . '.' . base64Url(assinaturaEcdsaBruta($assinatura));
}

function assinaturaEcdsaBruta(string $der): string
{
    $pos = 0;
    if (ord($der[$pos++]) !== 0x30) {
        return $der;
    }
    $len = ord($der[$pos++]);
    if ($len & 0x80) {
        $pos += $len & 0x7f;
    }
    $ler = function () use (&$der, &$pos): string {
        $pos++;
        $n = ord($der[$pos++]);
        $valor = substr($der, $pos, $n);
        $pos += $n;
        return $valor;
    };
    $r = ltrim($ler(), "\x00");
    $s = ltrim($ler(), "\x00");

    return str_pad($r, 32, "\0", STR_PAD_LEFT) . str_pad($s, 32, "\0", STR_PAD_LEFT);
}

function criptografarPush(string $payload, string $publicaUsuario, string $auth): ?string
{
    $local = openssl_pkey_new(opcoesChaveEc());
    if (!$local) {
        return null;
    }
    $detalhes = openssl_pkey_get_details($local);
    $publicaLocal = "\x04"
        . str_pad($detalhes['ec']['x'], 32, "\0", STR_PAD_LEFT)
        . str_pad($detalhes['ec']['y'], 32, "\0", STR_PAD_LEFT);

    $pemUsuario = "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode("\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00" . $publicaUsuario), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
    $chaveUsuario = openssl_pkey_get_public($pemUsuario);
    if (!$chaveUsuario) {
        return null;
    }
    $segredo = openssl_pkey_derive($chaveUsuario, $local, 32);
    if ($segredo === false) {
        return null;
    }

    $sal = random_bytes(16);
    $ikm = hash_hkdf('sha256', $segredo, 32, "WebPush: info\x00" . $publicaUsuario . $publicaLocal, $auth);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $sal);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $sal);
    $tag = '';
    $cifrado = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($cifrado === false) {
        return null;
    }

    return $sal . pack('N', 4096) . "\x41" . $publicaLocal . $cifrado . $tag;
}

function salvarInscricaoPush(PDO $pdo, int $alunoId, array $dados): bool
{
    $endpoint = $dados['endpoint'] ?? '';
    $p256dh = $dados['keys']['p256dh'] ?? '';
    $auth = $dados['keys']['auth'] ?? '';
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO push_inscricoes (aluno_id, endpoint, p256dh, auth)
         VALUES (:aluno_id, :endpoint, :p256dh, :auth)
         ON DUPLICATE KEY UPDATE aluno_id = VALUES(aluno_id), p256dh = VALUES(p256dh), auth = VALUES(auth)'
    );

    return $stmt->execute([
        'aluno_id' => $alunoId,
        'endpoint' => $endpoint,
        'p256dh'   => $p256dh,
        'auth'     => $auth,
    ]);
}
