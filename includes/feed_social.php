<?php
/** @var array $feedPosts */
/** @var bool $feedPodePublicar */
/** @var int $feedUsuarioId */
/** @var bool $feedModeraPosts */
/** @var bool $feedModeraComentarios */
/** @var string $feedContexto */
/** @var int $feedAlvo */
/** @var string $feedVoltar */

$feedPosts = $feedPosts ?? [];
$feedPodePublicar = $feedPodePublicar ?? false;
$feedUsuarioId = (int) ($feedUsuarioId ?? 0);
$feedUsuarioNome = (string) ($_SESSION['usuario_nome'] ?? '');
$feedModeraPosts = $feedModeraPosts ?? false;
$feedModeraComentarios = $feedModeraComentarios ?? false;
$feedContexto = $feedContexto ?? 'mural';
$feedAlvo = (int) ($feedAlvo ?? 0);
$feedVoltar = $feedVoltar ?? '';
$feedChave = e($feedContexto) . '-' . $feedAlvo;
$feedPlaceholder = $feedContexto === 'sala'
    ? 'Compartilhe um resumo, uma dúvida ou um material com a sala'
    : 'Compartilhe um resumo, um material de prova ou uma dúvida com a turma';

require_once __DIR__ . '/feed_render.php';

$ctxFeed = [
    'contexto'          => $feedContexto,
    'alvo'              => $feedAlvo,
    'voltar'            => $feedVoltar,
    'usuarioId'         => $feedUsuarioId,
    'usuarioNome'       => $feedUsuarioNome,
    'podePublicar'      => $feedPodePublicar,
    'moderaPosts'       => $feedModeraPosts,
    'moderaComentarios' => $feedModeraComentarios,
];
?>
<div class="social-stream" data-eu="<?= $feedUsuarioId ?>">
    <?php if ($feedPodePublicar): ?>
        <form class="social-card composer" method="post" action="<?= BASE_URL ?>/aluno/social.php" enctype="multipart/form-data" data-composer>
            <?= camposSocial('publicar', $ctxFeed) ?>
            <div class="composer-top">
                <?= avatarSocial($feedUsuarioId, $feedUsuarioNome) ?>
                <label class="sr-only" for="feed-texto-<?= $feedChave ?>"><?= $feedContexto === 'sala' ? 'Publicar na sala' : 'Publicar no mural' ?></label>
                <textarea id="feed-texto-<?= $feedChave ?>" name="texto" rows="4" maxlength="4000" required placeholder="<?= e($feedPlaceholder) ?>" data-autogrow></textarea>
            </div>
            <div class="composer-files" data-composer-files hidden></div>
            <div class="composer-bar">
                <label class="composer-tool tool-foto">
                    <input class="sr-only" name="anexos[]" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp" data-composer-input>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="10" r="1.8" fill="currentColor"/><path d="m5 17 5-5 3 3 2-2 4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    Foto
                </label>
                <label class="composer-tool tool-doc">
                    <input class="sr-only" name="anexos[]" type="file" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip" data-composer-input>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l5 5v13H7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 3v5h5M10 13h6M10 17h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Documento
                </label>
                <span class="composer-hint">PDF, Word, slides e imagens até 5 MB</span>
                <button class="btn btn-stamp composer-send" type="submit">Publicar</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($feedPosts): ?>
        <div class="feed-divisor"><span>Mais recentes</span></div>
    <?php else: ?>
        <div class="social-card social-empty">
            <span class="social-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
            </span>
            <strong>Nenhuma publicação por aqui ainda</strong>
            <p>Comece a conversa com um resumo, um material ou uma dúvida.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($feedPosts as $post): ?>
        <?php renderPostSocial($post, $ctxFeed); ?>
    <?php endforeach; ?>
</div>
