<?php
/**
 * Conteúdo da página "Meu perfil" (foto + senha).
 * Quem inclui já exigiu login e define $perfilAcao (URL desta página).
 */

$pdo = getPDO();
$usuarioId = (int) $_SESSION['usuario_id'];
$tipoPerfil = (string) $_SESSION['usuario_tipo'];
$erros = [];
$erroSenha = [];
$erroFoto = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if (!tokenPerfilValido((string) ($_POST['token'] ?? ''))) {
        $erros[] = 'Sessão expirada. Recarregue a página e tente de novo.';
    } elseif ($acao === 'senha') {
        $ok = alterarSenhaUsuario(
            $pdo,
            $usuarioId,
            (string) ($_POST['senha_atual'] ?? ''),
            (string) ($_POST['senha_nova'] ?? ''),
            (string) ($_POST['senha_confirma'] ?? ''),
            $erroSenha
        );
        if ($ok) {
            session_regenerate_id(true);
            header('Location: ' . $perfilAcao . '?ok=senha');
            exit;
        }
    } elseif ($acao === 'foto') {
        if (salvarFotoPerfil($pdo, $usuarioId, $_FILES['foto'] ?? [], $erroFoto)) {
            header('Location: ' . $perfilAcao . '?ok=foto');
            exit;
        }
    } elseif ($acao === 'remover_foto') {
        removerFotoPerfil($pdo, $usuarioId);
        header('Location: ' . $perfilAcao . '?ok=removida');
        exit;
    }
}

$mensagens = [
    'senha'    => 'Senha alterada com sucesso.',
    'foto'     => 'Foto de perfil atualizada.',
    'removida' => 'Foto de perfil removida.',
];
$sucesso = $mensagens[$_GET['ok'] ?? ''] ?? '';

$tituloPagina = 'Meu perfil';
$menuAtivo = 'perfil';
$fotoAtual = fotoUsuario($usuarioId);
$nomeUsuario = (string) $_SESSION['usuario_nome'];
$emailUsuario = (string) $_SESSION['usuario_email'];
$token = tokenPerfil();

require __DIR__ . '/header.php';
?>

<?php if ($sucesso !== ''): ?>
    <div class="alert alert-ok" role="status"><?= e($sucesso) ?></div>
<?php endif; ?>
<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="panel perfil-conta">
    <div class="perfil-conta-topo">
        <?= avatarSocial($usuarioId, $nomeUsuario, 'xl') ?>
        <div>
            <h2><?= e($nomeUsuario) ?></h2>
            <p class="panel-lead"><?= e($emailUsuario) ?> · <?= e(rotuloPerfil($tipoPerfil)) ?></p>
        </div>
    </div>

    <h3>Foto de perfil</h3>
    <?php foreach ($erroFoto as $erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e($perfilAcao) ?>" enctype="multipart/form-data" class="perfil-form">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="acao" value="foto">
        <div class="field">
            <label for="foto">Escolher imagem</label>
            <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
            <small class="field-help">JPG, PNG, GIF ou WebP, até 5 MB. A foto aparece recortada em círculo.</small>
        </div>
        <div class="form-actions">
            <button class="btn btn-stamp" type="submit">Salvar foto</button>
        </div>
    </form>
    <?php if ($fotoAtual !== null): ?>
        <form method="post" action="<?= e($perfilAcao) ?>" class="perfil-form">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="acao" value="remover_foto">
            <button class="btn btn-ghost" type="submit">Remover foto</button>
        </form>
    <?php endif; ?>
</section>

<section class="panel perfil-conta" id="senha">
    <h2>Alterar senha</h2>
    <p class="panel-lead">Use pelo menos <?= (int) SENHA_MINIMA ?> caracteres. Não compartilhe a senha com ninguém.</p>
    <?php foreach ($erroSenha as $erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e($perfilAcao) ?>#senha" class="perfil-form">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="acao" value="senha">
        <input type="text" name="usuario_oculto" value="<?= e($emailUsuario) ?>" autocomplete="username" hidden tabindex="-1" aria-hidden="true">
        <div class="form-grid">
            <div class="field span-2">
                <label for="senha_atual">Senha atual</label>
                <input id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password">
            </div>
            <div class="field">
                <label for="senha_nova">Nova senha</label>
                <input id="senha_nova" name="senha_nova" type="password" required minlength="<?= (int) SENHA_MINIMA ?>" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="senha_confirma">Repita a nova senha</label>
                <input id="senha_confirma" name="senha_confirma" type="password" required minlength="<?= (int) SENHA_MINIMA ?>" autocomplete="new-password">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-stamp" type="submit">Alterar senha</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/footer.php'; ?>
