<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirProfessor();

$pdo = getPDO();
$professor = obterProfessorPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$professor) {
    encerrarSessao();
    redirecionar('/login.php');
}

$turmas = listarTurmasDoProfessor($pdo, (int) $professor['id']);
$turmaId = (int) ($_GET['turma'] ?? 0);
$ids = array_map(static fn (array $turma): int => (int) $turma['id'], $turmas);

if (!$ids) {
    $turmaId = 0;
} elseif (!in_array($turmaId, $ids, true)) {
    $turmaId = $ids[0];
}

$turmaAtual = null;
$contagemPosts = [];
foreach ($turmas as $turma) {
    if ((int) $turma['id'] === $turmaId) {
        $turmaAtual = $turma;
    }
}
if ($ids) {
    $linhas = $pdo->query('SELECT turma_id, COUNT(*) AS total FROM mural_posts WHERE turma_id IN (' . implode(',', $ids) . ') GROUP BY turma_id')->fetchAll();
    foreach ($linhas as $linha) {
        $contagemPosts[(int) $linha['turma_id']] = (int) $linha['total'];
    }
}

$tituloPagina = 'Mural';
$menuAtivo = 'mural';

require __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-ok" role="status">Mural atualizado.</div>
<?php endif; ?>
<?php if (!empty($_GET['aviso'])): ?>
    <div class="alert alert-error">A publicação foi salva, mas um arquivo não foi aceito. Use imagem, PDF, Word ou slides de até 5 MB.</div>
<?php endif; ?>
<?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-error">Não foi possível publicar agora. Escreva um texto e tente de novo.</div>
<?php endif; ?>

<?php if (!$turmas): ?>
    <div class="social-card social-empty">
        <strong>Nenhuma turma vinculada ainda</strong>
        <p>O mural aparece quando uma turma estiver com você.</p>
    </div>
<?php else: ?>
    <div class="social-layout layout-representante">
        <aside class="social-left">
            <section class="social-card perfil-card">
                <div class="perfil-capa capa-<?= ($turmaId % 5) + 1 ?>"></div>
                <div class="perfil-corpo">
                    <?= avatarSocial((int) $_SESSION['usuario_id'], $professor['nome'], 'xl') ?>
                    <h2><?= e($professor['nome']) ?></h2>
                    <span class="perfil-turma">Representante de turma</span>
                </div>
            </section>

            <nav class="social-card turmas-card" aria-label="Turmas do mural">
                <h2>Suas turmas</h2>
                <?php foreach ($turmas as $turma): ?>
                    <a class="<?= (int) $turma['id'] === $turmaId ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/professor/mural.php?turma=<?= (int) $turma['id'] ?>">
                        <span class="sala-icone <?= capaSala((int) $turma['id']) ?>"><?= e(iniciaisNome($turma['nome'])) ?></span>
                        <span class="lateral-texto">
                            <strong><?= e($turma['nome']) ?></strong>
                            <small><?= $contagemPosts[(int) $turma['id']] ?? 0 ?> publicaç<?= ($contagemPosts[(int) $turma['id']] ?? 0) === 1 ? 'ão' : 'ões' ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div class="social-feed">
            <div class="feed-titulo">
                <h2>Mural · <?= e($turmaAtual['nome'] ?? '') ?></h2>
                <p>Você publica para a turma e pode apagar qualquer publicação ou comentário deste mural.</p>
            </div>
            <?php
            $feedPosts = listarPostsComunitarios($pdo, 'mural', $turmaId);
            $feedPodePublicar = true;
            $feedUsuarioId = (int) $_SESSION['usuario_id'];
            $feedModeraPosts = true;
            $feedModeraComentarios = true;
            $feedContexto = 'mural';
            $feedAlvo = $turmaId;
            $feedVoltar = BASE_URL . '/professor/mural.php?turma=' . $turmaId;
            require __DIR__ . '/../includes/feed_social.php';
            ?>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
