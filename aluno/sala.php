<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirEscola();

$pdo = getPDO();
$usuarioId = (int) $_SESSION['usuario_id'];
$sala = obterSala($pdo, (int) ($_GET['id'] ?? 0));

if (!$sala) {
    redirecionar('/aluno/salas.php');
}

$salaId = (int) $sala['id'];
$inscrito = usuarioInscritoNaSala($pdo, $salaId, $usuarioId);
$criador = (int) $sala['criador_id'] === $usuarioId;
$membros = listarMembrosSala($pdo, $salaId, 12);
$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM salas_inscricoes WHERE sala_id = :sala');
$stmtTotal->execute(['sala' => $salaId]);
$totalMembros = (int) $stmtTotal->fetchColumn();
$stmtPosts = $pdo->prepare('SELECT COUNT(*) FROM salas_posts WHERE sala_id = :sala');
$stmtPosts->execute(['sala' => $salaId]);
$totalPosts = (int) $stmtPosts->fetchColumn();

$tituloPagina = $sala['titulo'];
$menuAtivo = 'salas';

require __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-ok" role="status"><?= ($_GET['ok'] ?? '') === 'inscrito' ? 'Agora você participa desta sala.' : 'Sala atualizada.' ?></div>
<?php endif; ?>
<?php if (!empty($_GET['aviso'])): ?>
    <div class="alert alert-error">A publicação foi salva, mas um arquivo não foi aceito. Use imagem, PDF, Word ou slides de até 5 MB.</div>
<?php endif; ?>
<?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-error">Não foi possível concluir esta ação.</div>
<?php endif; ?>

<section class="social-card grupo-head">
    <div class="grupo-capa <?= capaSala($salaId) ?>">
        <a class="grupo-voltar" href="<?= BASE_URL ?>/aluno/salas.php">← Todas as salas</a>
    </div>
    <div class="grupo-info">
        <span class="sala-icone sala-icone-xl <?= capaSala($salaId) ?>"><?= e(iniciaisNome($sala['titulo'])) ?></span>
        <div class="grupo-texto">
            <p class="sala-tema"><?= e($sala['tema'] !== '' ? $sala['tema'] : 'Estudo livre') ?></p>
            <h2><?= e($sala['titulo']) ?></h2>
            <p class="grupo-meta">Sala aberta · <?= $totalMembros ?> membro<?= $totalMembros === 1 ? '' : 's' ?> · <?= $totalPosts ?> publicaç<?= $totalPosts === 1 ? 'ão' : 'ões' ?></p>
            <span class="avatar-pilha">
                <?php foreach (array_slice($membros, 0, 6) as $membro): ?>
                    <?= avatarSocial((int) $membro['id'], $membro['nome'], 'sm') ?>
                <?php endforeach; ?>
            </span>
        </div>
        <div class="grupo-acao">
            <?php if ($inscrito): ?>
                <span class="grupo-status">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.6 9.2 16.8 19 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?= $criador ? 'Você criou esta sala' : 'Você participa' ?>
                </span>
            <?php else: ?>
                <form method="post" action="<?= BASE_URL ?>/aluno/social.php">
                    <input type="hidden" name="acao" value="inscrever">
                    <input type="hidden" name="alvo" value="<?= $salaId ?>">
                    <button class="btn btn-stamp" type="submit">+ Participar da sala</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="social-layout layout-grupo">
    <div class="social-feed">
        <?php if (!$inscrito): ?>
            <div class="social-card sala-bloqueio">
                <div class="bloqueio-fantasma" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
                <div class="bloqueio-mensagem">
                    <span class="social-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    </span>
                    <strong>Participe para ver a conversa</strong>
                    <p>As publicações, os materiais e os comentários abrem depois da inscrição. Alunos de qualquer turma podem entrar.</p>
                    <form method="post" action="<?= BASE_URL ?>/aluno/social.php">
                        <input type="hidden" name="acao" value="inscrever">
                        <input type="hidden" name="alvo" value="<?= $salaId ?>">
                        <button class="btn btn-stamp" type="submit">+ Participar da sala</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <?php
            $feedPosts = listarPostsComunitarios($pdo, 'sala', $salaId);
            $feedPodePublicar = true;
            $feedUsuarioId = $usuarioId;
            $feedModeraPosts = $criador;
            $feedModeraComentarios = $criador;
            $feedContexto = 'sala';
            $feedAlvo = $salaId;
            $feedVoltar = BASE_URL . '/aluno/sala.php?id=' . $salaId;
            require __DIR__ . '/../includes/feed_social.php';
            ?>
        <?php endif; ?>
    </div>

    <aside class="social-right">
        <section class="social-card lateral-card">
            <div class="lateral-head"><h2>Sobre</h2></div>
            <p class="lateral-sobre"><?= nl2br(e($sala['descricao'] ?: 'Sala aberta para publicar resumos e comentar.')) ?></p>
            <ul class="lateral-fatos">
                <li><span>Tema</span><strong><?= e($sala['tema'] !== '' ? $sala['tema'] : 'Estudo livre') ?></strong></li>
                <li><span>Criada por</span><strong><?= e($sala['criador_nome']) ?></strong></li>
                <li><span>Desde</span><strong><?= e(date('d/m/Y', strtotime($sala['data_criacao']))) ?></strong></li>
            </ul>
        </section>

        <section class="social-card lateral-card">
            <div class="lateral-head"><h2>Membros</h2><span class="lateral-contagem"><?= $totalMembros ?></span></div>
            <?php foreach ($membros as $membro): ?>
                <div class="lateral-membro">
                    <?= avatarSocial((int) $membro['id'], $membro['nome']) ?>
                    <span class="lateral-texto">
                        <strong><?= e($membro['nome']) ?><?= (int) $membro['id'] === (int) $sala['criador_id'] ? ' · criador' : '' ?></strong>
                        <small><?= e(descricaoAutor($membro['tipo'], $membro['turma'])) ?></small>
                    </span>
                </div>
            <?php endforeach; ?>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
