<?php

function camposSocial(string $acao, array $ctx): string
{
    return '<input type="hidden" name="acao" value="' . e($acao) . '">'
        . '<input type="hidden" name="contexto" value="' . e($ctx['contexto']) . '">'
        . '<input type="hidden" name="alvo" value="' . (int) $ctx['alvo'] . '">'
        . '<input type="hidden" name="voltar" value="' . e($ctx['voltar']) . '">';
}

function renderComentarioSocial(array $comentario, array $ctx, bool $oculto = false): void
{
    $podeApagar = (int) $comentario['autor_id'] === (int) $ctx['usuarioId'] || !empty($ctx['moderaComentarios']);
    ?>
    <div class="comentario" <?= $oculto ? 'hidden data-comentario-oculto' : '' ?>>
        <?= avatarSocial((int) $comentario['autor_id'], $comentario['autor_nome'], 'sm') ?>
        <div class="comentario-corpo">
            <div class="comentario-balao">
                <div class="comentario-topo">
                    <strong><?= e($comentario['autor_nome']) ?></strong>
                    <time datetime="<?= e($comentario['data_criacao']) ?>"><?= e(tempoRelativo($comentario['data_criacao'])) ?></time>
                </div>
                <small><?= e(descricaoAutor($comentario['autor_tipo'] ?? null, $comentario['autor_turma'] ?? null)) ?></small>
                <p><?= nl2br(e($comentario['texto'])) ?></p>
            </div>
            <?php if ($podeApagar): ?>
                <form method="post" action="<?= BASE_URL ?>/aluno/social.php" data-confirm="Apagar este comentário?">
                    <?= camposSocial('apagar_comentario', $ctx) ?>
                    <input type="hidden" name="comentario_id" value="<?= (int) $comentario['id'] ?>">
                    <button class="comentario-apagar" type="submit">Apagar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function renderPostSocial(array $post, array $ctx): void
{
    $imagens = array_values(array_filter($post['anexos'] ?? [], static fn ($a) => anexoEhImagem($a['tipo_mime'])));
    $arquivos = array_values(array_filter($post['anexos'] ?? [], static fn ($a) => !anexoEhImagem($a['tipo_mime'])));
    $comentarios = $post['comentarios'] ?? [];
    $totalComentarios = count($comentarios);
    $ocultos = max(0, $totalComentarios - 2);
    $podeApagarPost = (int) $post['autor_id'] === (int) $ctx['usuarioId'] || !empty($ctx['moderaPosts']);
    $chave = e($ctx['contexto']) . '-' . (int) $ctx['alvo'];
    ?>
    <article class="social-card social-post" id="post-<?= e($ctx['contexto']) ?>-<?= (int) $post['id'] ?>" data-autor="<?= (int) $post['autor_id'] ?>">
        <header class="post-head">
            <?= avatarSocial((int) $post['autor_id'], $post['autor_nome'], 'lg') ?>
            <div class="post-autor">
                <strong><?= e($post['autor_nome']) ?></strong>
                <span><?= e(descricaoAutor($post['autor_tipo'] ?? null, $post['autor_turma'] ?? null)) ?></span>
                <time datetime="<?= e($post['data_criacao']) ?>" title="<?= e(date('d/m/Y H:i', strtotime($post['data_criacao']))) ?>"><?= e(tempoRelativo($post['data_criacao'])) ?></time>
            </div>
            <?php if ($podeApagarPost): ?>
                <details class="post-menu">
                    <summary aria-label="Opções da publicação">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                    </summary>
                    <form method="post" action="<?= BASE_URL ?>/aluno/social.php" data-confirm="Apagar esta publicação?">
                        <?= camposSocial('apagar_post', $ctx) ?>
                        <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                        <button type="submit">Apagar publicação</button>
                    </form>
                </details>
            <?php endif; ?>
        </header>

        <p class="post-texto"><?= nl2br(e($post['texto'])) ?></p>

        <?php if ($imagens): ?>
            <div class="post-galeria galeria-<?= min(count($imagens), 3) ?>">
                <?php foreach ($imagens as $imagem): ?>
                    <a href="<?= e(UPLOAD_URL . '/' . $imagem['caminho']) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(UPLOAD_URL . '/' . $imagem['caminho']) ?>" alt="<?= e($imagem['nome_original']) ?>" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($arquivos as $arquivo): ?>
            <?php [$classeArquivo, $rotuloArquivo] = tipoArquivoSocial($arquivo['nome_original']); ?>
            <a class="post-arquivo" href="<?= e(UPLOAD_URL . '/' . $arquivo['caminho']) ?>" target="_blank" rel="noopener">
                <span class="arquivo-badge arquivo-<?= e($classeArquivo) ?>"><?= e($rotuloArquivo) ?></span>
                <span class="arquivo-info">
                    <strong><?= e($arquivo['nome_original']) ?></strong>
                    <small><?= e($rotuloArquivo) ?> · <?= e(tamanhoLegivel((int) $arquivo['tamanho'])) ?></small>
                </span>
                <span class="arquivo-abrir">Abrir</span>
            </a>
        <?php endforeach; ?>

        <div class="post-contagem">
            <?= $totalComentarios ? $totalComentarios . ' comentário' . ($totalComentarios === 1 ? '' : 's') : 'Seja o primeiro a comentar' ?>
        </div>

        <div class="post-acoes">
            <?php if (!empty($ctx['podePublicar'])): ?>
                <button type="button" data-comentar>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    Comentar
                </button>
            <?php endif; ?>
            <button type="button" data-copiar-link>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span>Copiar link</span>
            </button>
        </div>

        <div class="post-comentarios">
            <?php if ($ocultos): ?>
                <button class="comentarios-mais" type="button" data-mais-comentarios>Ver <?= $ocultos ?> comentário<?= $ocultos === 1 ? '' : 's' ?> anterior<?= $ocultos === 1 ? '' : 'es' ?></button>
            <?php endif; ?>

            <?php foreach ($comentarios as $indice => $comentario): ?>
                <?php renderComentarioSocial($comentario, $ctx, $indice < $ocultos); ?>
            <?php endforeach; ?>

            <?php if (!empty($ctx['podePublicar'])): ?>
                <form class="comentario-form" method="post" action="<?= BASE_URL ?>/aluno/social.php">
                    <?= camposSocial('comentar', $ctx) ?>
                    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                    <?= avatarSocial((int) $ctx['usuarioId'], (string) $ctx['usuarioNome'], 'sm') ?>
                    <label class="sr-only" for="comentario-<?= $chave ?>-<?= (int) $post['id'] ?>">Escrever comentário</label>
                    <input id="comentario-<?= $chave ?>-<?= (int) $post['id'] ?>" name="texto" type="text" maxlength="1000" required placeholder="Adicionar um comentário">
                    <button type="submit" aria-label="Enviar comentário">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12 20 4l-6 16-3-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

function renderCartaoSala(array $sala): void
{
    $id = (int) $sala['id'];
    $inscrito = !empty($sala['inscrito']);
    $membros = $sala['membros'] ?? [];
    $inscritos = (int) ($sala['inscritos'] ?? count($membros));
    ?>
    <article class="social-card sala-card" data-sala data-inscrito="<?= $inscrito ? '1' : '0' ?>" data-busca="<?= e(mb_strtolower(($sala['titulo'] ?? '') . ' ' . ($sala['tema'] ?? '') . ' ' . ($sala['descricao'] ?? ''))) ?>">
        <?php if ($inscrito): ?>
            <span class="sala-selo">Você participa</span>
        <?php endif; ?>
        <div class="sala-corpo">
            <span class="sala-icone sala-icone-lg <?= capaSala($id) ?>"><?= e(iniciaisNome($sala['titulo'])) ?></span>
            <p class="sala-tema"><?= e(($sala['tema'] ?? '') !== '' ? $sala['tema'] : 'Estudo livre') ?></p>
            <h2><a href="<?= BASE_URL ?>/aluno/sala.php?id=<?= $id ?>"><?= e($sala['titulo']) ?></a></h2>
            <p class="sala-descricao"><?= e($sala['descricao'] ?: 'Sala aberta para publicar resumos e comentar.') ?></p>
            <div class="sala-membros">
                <span class="avatar-pilha">
                    <?php foreach ($membros as $membro): ?>
                        <?= avatarSocial((int) $membro['id'], $membro['nome'], 'sm') ?>
                    <?php endforeach; ?>
                </span>
                <small><?= $inscritos ?> membro<?= $inscritos === 1 ? '' : 's' ?> · criada por <?= e(primeiroNome($sala['criador_nome'] ?? '')) ?></small>
            </div>
        </div>
        <div class="sala-rodape">
            <?php if ($inscrito): ?>
                <a class="btn btn-stamp" href="<?= BASE_URL ?>/aluno/sala.php?id=<?= $id ?>">Abrir sala</a>
            <?php else: ?>
                <form method="post" action="<?= BASE_URL ?>/aluno/social.php">
                    <input type="hidden" name="acao" value="inscrever">
                    <input type="hidden" name="alvo" value="<?= $id ?>">
                    <button class="btn btn-stamp" type="submit">+ Participar</button>
                </form>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>/aluno/sala.php?id=<?= $id ?>">Ver detalhes</a>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

function capturarRender(callable $render): string
{
    ob_start();
    $render();

    return (string) ob_get_clean();
}
