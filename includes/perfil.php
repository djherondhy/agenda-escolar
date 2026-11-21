<?php

/**
 * Foto de perfil e troca de senha (valem para todos os perfis).
 */

const FOTO_PERFIL_MAX_BYTES = 5 * 1024 * 1024;
const FOTO_PERFIL_LADO = 320;
const SENHA_MINIMA = 6;

function fotoUsuario(int $usuarioId): ?string
{
    static $cache = [];

    if ($usuarioId <= 0) {
        return null;
    }
    if (array_key_exists($usuarioId, $cache)) {
        return $cache[$usuarioId];
    }

    try {
        $stmt = getPDO()->prepare('SELECT foto FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $usuarioId]);
        $foto = $stmt->fetchColumn();
    } catch (Throwable $e) {
        $foto = null;
    }

    return $cache[$usuarioId] = (is_string($foto) && $foto !== '') ? $foto : null;
}

function urlFotoPerfil(string $caminho): string
{
    $arquivo = UPLOAD_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $caminho);
    $versao = is_file($arquivo) ? '?v=' . filemtime($arquivo) : '';

    return UPLOAD_URL . '/' . $caminho . $versao;
}

function senhaProvisoriaAtiva(int $usuarioId): bool
{
    try {
        $stmt = getPDO()->prepare('SELECT senha_provisoria FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $usuarioId]);

        return (int) $stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        return false;
    }
}

function tokenPerfil(): string
{
    if (empty($_SESSION['perfil_token'])) {
        $_SESSION['perfil_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['perfil_token'];
}

function tokenPerfilValido(string $token): bool
{
    return !empty($_SESSION['perfil_token']) && hash_equals($_SESSION['perfil_token'], $token);
}

function alterarSenhaUsuario(PDO $pdo, int $usuarioId, string $atual, string $nova, string $confirma, array &$erros): bool
{
    $stmt = $pdo->prepare('SELECT senha FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $usuarioId]);
    $hash = (string) $stmt->fetchColumn();

    if ($atual === '' || !password_verify($atual, $hash)) {
        $erros[] = 'A senha atual não confere.';
    }
    if (strlen($nova) < SENHA_MINIMA) {
        $erros[] = 'A nova senha deve ter pelo menos ' . SENHA_MINIMA . ' caracteres.';
    } elseif ($nova !== $confirma) {
        $erros[] = 'A confirmação não é igual à nova senha.';
    } elseif ($nova === $atual) {
        $erros[] = 'Escolha uma senha diferente da atual.';
    } elseif (strcasecmp($nova, SENHA_PADRAO_USUARIO) === 0) {
        $erros[] = 'Essa é a senha padrão. Escolha outra.';
    }

    if ($erros) {
        return false;
    }

    $pdo->prepare('UPDATE usuarios SET senha = :senha, senha_provisoria = 0 WHERE id = :id')
        ->execute(['senha' => password_hash($nova, PASSWORD_DEFAULT), 'id' => $usuarioId]);

    return true;
}

/**
 * Recebe o upload da foto, valida o conteúdo real da imagem e grava em uploads/perfil/.
 * Com GD disponível, recorta em quadrado e reduz (também limpa metadados da imagem).
 */
function salvarFotoPerfil(PDO $pdo, int $usuarioId, array $arquivo, array &$erros): bool
{
    $codigo = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($codigo === UPLOAD_ERR_NO_FILE) {
        $erros[] = 'Escolha uma imagem.';
        return false;
    }
    if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE || ($arquivo['size'] ?? 0) > FOTO_PERFIL_MAX_BYTES) {
        $erros[] = 'A foto deve ter no máximo 5 MB.';
        return false;
    }
    if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'] ?? '')) {
        $erros[] = 'Não foi possível enviar a foto.';
        return false;
    }

    $info = @getimagesize($arquivo['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
    if (defined('IMAGETYPE_WEBP')) {
        $tipos[IMAGETYPE_WEBP] = 'webp';
    }
    if (!$info || !isset($tipos[$info[2]])) {
        $erros[] = 'Use uma imagem JPG, PNG, GIF ou WebP.';
        return false;
    }
    if ($info[0] * $info[1] > 16000000) {
        $erros[] = 'A imagem é grande demais. Use uma com menos de 16 megapixels.';
        return false;
    }

    $pasta = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'perfil';
    if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
        $erros[] = 'Não foi possível criar a pasta de fotos.';
        return false;
    }

    $base = 'u' . $usuarioId . '-' . bin2hex(random_bytes(6));
    $processada = function_exists('imagecreatetruecolor') && recortarFotoPerfil($arquivo['tmp_name'], (int) $info[2], $pasta . DIRECTORY_SEPARATOR . $base . '.jpg');

    if ($processada) {
        $nome = $base . '.jpg';
    } else {
        $nome = $base . '.' . $tipos[$info[2]];
        if (!move_uploaded_file($arquivo['tmp_name'], $pasta . DIRECTORY_SEPARATOR . $nome)) {
            $erros[] = 'Falha ao gravar a foto.';
            return false;
        }
    }

    $antiga = fotoUsuario($usuarioId);
    $pdo->prepare('UPDATE usuarios SET foto = :foto WHERE id = :id')
        ->execute(['foto' => 'perfil/' . $nome, 'id' => $usuarioId]);
    if ($antiga) {
        apagarArquivoUpload($antiga);
    }

    return true;
}

function removerFotoPerfil(PDO $pdo, int $usuarioId): void
{
    $antiga = fotoUsuario($usuarioId);
    $pdo->prepare('UPDATE usuarios SET foto = NULL WHERE id = :id')->execute(['id' => $usuarioId]);
    if ($antiga) {
        apagarArquivoUpload($antiga);
    }
}

function recortarFotoPerfil(string $origem, int $tipo, string $destino): bool
{
    $imagem = match ($tipo) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($origem),
        IMAGETYPE_PNG  => @imagecreatefrompng($origem),
        IMAGETYPE_GIF  => @imagecreatefromgif($origem),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($origem) : false,
        default        => false,
    };
    if (!$imagem) {
        return false;
    }

    if ($tipo === IMAGETYPE_JPEG && function_exists('exif_read_data') && function_exists('imagerotate')) {
        $exif = @exif_read_data($origem);
        $giro = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($giro !== 0 && ($girada = imagerotate($imagem, $giro, 0))) {
            imagedestroy($imagem);
            $imagem = $girada;
        }
    }

    $largura = imagesx($imagem);
    $altura = imagesy($imagem);
    $lado = min($largura, $altura);
    $x = (int) (($largura - $lado) / 2);
    $y = (int) (($altura - $lado) / 2);

    $saida = imagecreatetruecolor(FOTO_PERFIL_LADO, FOTO_PERFIL_LADO);
    imagefill($saida, 0, 0, imagecolorallocate($saida, 255, 255, 255));
    imagecopyresampled($saida, $imagem, 0, 0, $x, $y, FOTO_PERFIL_LADO, FOTO_PERFIL_LADO, $lado, $lado);
    $ok = imagejpeg($saida, $destino, 86);
    imagedestroy($imagem);
    imagedestroy($saida);

    return $ok;
}
