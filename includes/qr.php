<?php

function urlParaCelular(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
        || apacheSslAtivo();
    $esquema = $https ? 'https' : 'http';
    $porta = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    if ($https && $porta !== 443 && (int) ($_SERVER['SERVER_PORT'] ?? 80) !== 443) {
        $porta = 443;
    }
    $host = ipDaRede();
    $sufixo = ($https && $porta === 443) || (!$https && $porta === 80) ? '' : ':' . $porta;

    return $esquema . '://' . $host . $sufixo . BASE_URL . '/';
}

function apacheSslAtivo(): bool
{
    static $ativo = null;
    if ($ativo !== null) {
        return $ativo;
    }
    $conexao = @fsockopen('127.0.0.1', 443, $errno, $erro, 0.4);
    if (is_resource($conexao)) {
        fclose($conexao);
        return $ativo = true;
    }
    return $ativo = false;
}

function ipDaRede(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $host = $_SERVER['SERVER_ADDR'] ?? '';
    if ($host && $host !== '127.0.0.1' && $host !== '::1') {
        return $cache = $host;
    }

    $porNome = gethostbyname(gethostname() ?: '');
    if (filter_var($porNome, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $porNome !== '127.0.0.1') {
        return $cache = $porNome;
    }

    return $cache = '127.0.0.1';
}

function qrSvg(string $texto): string
{
    $matriz = qrMatriz($texto);
    $n = count($matriz);
    $cel = 8;
    $margem = 4;
    $lado = ($n + $margem * 2) * $cel;
    $rects = '';
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if (!$matriz[$y][$x]) {
                continue;
            }
            $px = ($x + $margem) * $cel;
            $py = ($y + $margem) * $cel;
            $rects .= '<rect x="' . $px . '" y="' . $py . '" width="' . $cel . '" height="' . $cel . '"/>';
        }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $lado . ' ' . $lado . '" role="img" aria-label="QR Code">'
        . '<rect width="100%" height="100%" fill="#fff"/>'
        . '<g fill="#0f1728">' . $rects . '</g></svg>';
}

function qrMatriz(string $texto): array
{
    $bytes = array_values(unpack('C*', $texto));
    $tamanho = count($bytes);
    $versoes = [
        1 => [19, 7],
        2 => [34, 10],
        3 => [55, 15],
        4 => [80, 20],
        5 => [108, 26],
        6 => [136, 36],
    ];
    $bitsNecessarios = 4 + 8 + ($tamanho * 8);
    $versao = 1;
    foreach ($versoes as $v => [$dados]) {
        if ($bitsNecessarios <= $dados * 8) {
            $versao = $v;
            break;
        }
        $versao = $v;
    }
    [$dadosCw, $eccCw] = $versoes[$versao];
    $bits = qrBits($bytes, $dadosCw * 8);
    $dados = qrAgrupar($bits);
    $ecc = qrReedSolomon($dados, $eccCw);
    $codewords = array_merge($dados, $ecc);

    $n = 21 + ($versao - 1) * 4;
    $modulos = array_fill(0, $n, array_fill(0, $n, false));
    $reservado = array_fill(0, $n, array_fill(0, $n, false));
    qrFuncao($modulos, $reservado, $versao);

    $i = 0;
    $total = count($codewords) * 8;
    $subir = true;
    for ($col = $n - 1; $col > 0; $col -= 2) {
        if ($col === 6) {
            $col--;
        }
        for ($passo = 0; $passo < $n; $passo++) {
            $y = $subir ? $n - 1 - $passo : $passo;
            foreach ([0, -1] as $dx) {
                $x = $col + $dx;
                if ($reservado[$y][$x]) {
                    continue;
                }
                $bit = $i < $total ? (($codewords[intdiv($i, 8)] >> (7 - ($i % 8))) & 1) : 0;
                $modulos[$y][$x] = (bool) $bit;
                $i++;
            }
        }
        $subir = !$subir;
    }

    $melhor = null;
    $melhorNota = PHP_INT_MAX;
    for ($mascara = 0; $mascara < 8; $mascara++) {
        $copia = $modulos;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($reservado[$y][$x]) {
                    continue;
                }
                if (qrMascara($mascara, $x, $y)) {
                    $copia[$y][$x] = !$copia[$y][$x];
                }
            }
        }
        qrFormato($copia, $mascara);
        $nota = qrPenalidade($copia);
        if ($nota < $melhorNota) {
            $melhorNota = $nota;
            $melhor = array_map(static fn (array $linha): array => $linha, $copia);
        }
    }

    return $melhor;
}

function qrBits(array $bytes, int $capacidade): array
{
    $bits = [0, 1, 0, 0];
    $len = count($bytes);
    for ($i = 7; $i >= 0; $i--) {
        $bits[] = ($len >> $i) & 1;
    }
    foreach ($bytes as $byte) {
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($byte >> $i) & 1;
        }
    }
    for ($i = 0; $i < 4 && count($bits) < $capacidade; $i++) {
        $bits[] = 0;
    }
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }
    $pads = [0xEC, 0x11];
    $p = 0;
    while (count($bits) < $capacidade) {
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($pads[$p % 2] >> $i) & 1;
        }
        $p++;
    }

    return array_slice($bits, 0, $capacidade);
}

function qrAgrupar(array $bits): array
{
    $bytes = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $valor = 0;
        for ($b = 0; $b < 8; $b++) {
            $valor = ($valor << 1) | $bits[$i + $b];
        }
        $bytes[] = $valor;
    }
    return $bytes;
}

function qrReedSolomon(array $dados, int $eccLen): array
{
    $gen = [1];
    for ($i = 0; $i < $eccLen; $i++) {
        $proximo = [];
        $fator = qrPot(2, $i);
        foreach ($gen as $coef) {
            $proximo[] = $coef;
        }
        $proximo[] = 0;
        for ($j = 0, $n = count($gen); $j < $n; $j++) {
            $proximo[$j + 1] ^= qrMul($gen[$j], $fator);
        }
        $gen = $proximo;
    }

    $res = array_fill(0, $eccLen, 0);
    foreach ($dados as $byte) {
        $fator = $byte ^ $res[0];
        array_shift($res);
        $res[] = 0;
        for ($i = 0; $i < $eccLen; $i++) {
            $res[$i] ^= qrMul($gen[$i + 1], $fator);
        }
    }
    return $res;
}

function qrPot(int $x, int $e): int
{
    $r = 1;
    while ($e--) {
        $r = qrMul($r, $x);
    }
    return $r;
}

function qrMul(int $a, int $b): int
{
    $r = 0;
    while ($b) {
        if ($b & 1) {
            $r ^= $a;
        }
        $a <<= 1;
        if ($a & 0x100) {
            $a ^= 0x11d;
        }
        $b >>= 1;
    }
    return $r;
}

function qrFuncao(array &$m, array &$r, int $versao): void
{
    $n = count($m);
    $desenhar = function (int $x, int $y) use (&$m, &$r, $n) {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx < 0 || $yy < 0 || $xx >= $n || $yy >= $n) {
                    continue;
                }
                $borda = $dx === -1 || $dy === -1 || $dx === 7 || $dy === 7;
                $anel = $dx === 0 || $dy === 0 || $dx === 6 || $dy === 6;
                $m[$yy][$xx] = !$borda && ($anel || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                $r[$yy][$xx] = true;
            }
        }
    };
    $desenhar(0, 0);
    $desenhar($n - 7, 0);
    $desenhar(0, $n - 7);

    for ($i = 8; $i < $n - 8; $i++) {
        $escuro = $i % 2 === 0;
        $m[6][$i] = $m[$i][6] = $escuro;
        $r[6][$i] = $r[$i][6] = true;
    }

    $alinhamentos = [2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34]][$versao] ?? [];
    foreach ($alinhamentos as $y) {
        foreach ($alinhamentos as $x) {
            if (($x < 9 && $y < 9) || ($x > $n - 10 && $y < 9) || ($x < 9 && $y > $n - 10)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $m[$y + $dy][$x + $dx] = max(abs($dx), abs($dy)) !== 1;
                    $r[$y + $dy][$x + $dx] = true;
                }
            }
        }
    }

    $m[$n - 8][8] = true;
    $r[$n - 8][8] = true;
    for ($i = 0; $i < 9; $i++) {
        if ($i !== 6) {
            $r[8][$i] = $r[$i][8] = true;
        }
    }
    for ($i = 0; $i < 8; $i++) {
        $r[8][$n - 1 - $i] = $r[$n - 1 - $i][8] = true;
    }
}

function qrMascara(int $mascara, int $x, int $y): bool
{
    return match ($mascara) {
        0 => ($x + $y) % 2 === 0,
        1 => $y % 2 === 0,
        2 => $x % 3 === 0,
        3 => ($x + $y) % 3 === 0,
        4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
        5 => (($x * $y) % 2 + ($x * $y) % 3) === 0,
        6 => ((($x * $y) % 2 + ($x * $y) % 3) % 2) === 0,
        default => ((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0,
    };
}

function qrFormato(array &$m, int $mascara): void
{
    $n = count($m);
    $data = (1 << 3) | $mascara;
    $rem = $data;
    for ($i = 0; $i < 10; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
    }
    $bits = (($data << 10) | ($rem & 0x3ff)) ^ 0x5412;
    $bit = static fn (int $i): bool => (bool) (($bits >> $i) & 1);

    for ($i = 0; $i <= 5; $i++) {
        $m[$i][8] = $bit($i);
    }
    $m[7][8] = $bit(6);
    $m[8][8] = $bit(7);
    $m[8][7] = $bit(8);
    for ($i = 9; $i < 15; $i++) {
        $m[8][14 - $i] = $bit($i);
    }
    for ($i = 0; $i < 8; $i++) {
        $m[8][$n - 1 - $i] = $bit($i);
    }
    for ($i = 8; $i < 15; $i++) {
        $m[$n - 15 + $i][8] = $bit($i);
    }
}

function qrPenalidade(array $m): int
{
    $n = count($m);
    $nota = 0;
    $escuros = 0;
    for ($y = 0; $y < $n; $y++) {
        $seq = 1;
        for ($x = 0; $x < $n; $x++) {
            $escuros += $m[$y][$x] ? 1 : 0;
            if ($x > 0 && $m[$y][$x] === $m[$y][$x - 1]) {
                $seq++;
                if ($seq === 5) {
                    $nota += 3;
                } elseif ($seq > 5) {
                    $nota++;
                }
            } else {
                $seq = 1;
            }
        }
    }
    $proporcao = abs(($escuros / ($n * $n) * 100) - 50);
    return $nota + (int) (floor($proporcao / 5) * 10);
}
