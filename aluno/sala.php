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
$administra = usuarioAdministraSala($pdo, $salaId, $usuarioId);
$membros = listarMembrosSala($pdo, $salaId, 12);
$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM salas_inscricoes WHERE sala_id = :sala');
$stmtTotal->execute(['sala' => $salaId]);
$totalMembros = (int) $stmtTotal->fetchColumn();
$stmtPosts = $pdo->prepare('SELECT COUNT(*) FROM salas_posts WHERE sala_id = :sala');
$stmtPosts->execute(['sala' => $salaId]);
$totalPosts = (int) $stmtPosts->fetchColumn();

$aba = $_GET['aba'] ?? 'conversa';
if (!$administra || !in_array($aba, ['conversa', 'config'], true)) {
    $aba = 'conversa';
}
$candidatos = $administra && $aba === 'config' ? listarCandidatosSala($pdo, $salaId) : [];
$membrosConfig = $administra && $aba === 'config' ? listarMembrosSala($pdo, $salaId, 500) : $membros;

$tituloPagina = $sala['titulo'];
$menuAtivo = 'salas';

require __DIR__ . '/../includes/header.php';

$avisosSala = [
    'inscrito'          => 'Agora você participa desta sala.',
    'admin'             => 'Você administra esta sala e pode incluir outros alunos como administradores.',
    'editada'           => 'Sala atualizada.',
    'membro'            => 'Aluno adicionado à sala.',
    'admin_novo'        => 'Aluno adicionado como administrador.',
    'admin_promovido'   => 'Este aluno agora também administra a sala.',
    'admin_removido'    => 'Este aluno não administra mais a sala.',
    'membro_removido'   => 'Aluno removido da sala.',
];
$errosSala = [
    'titulo'  => 'Dê um nome para a sala.',
    'membro'  => 'Escolha um aluno que ainda não está na sala.',
    'criador' => 'Peça a outro administrador para ajustar o seu acesso.',
    'ultimo'  => 'A sala precisa de pelo menos um administrador.',
    'acesso'  => 'Você não pode alterar esta sala.',
];
?>

<?php if (isset($avisosSala[$_GET['ok'] ?? ''])): ?>
    <div class="alert alert-ok" role="status"><?= e($avisosSala[$_GET['ok']]) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['aviso'])): ?>
    <div class="alert alert-error">A publicação foi salva, mas um arquivo não foi aceito. Use imagem, PDF, Word ou slides de até 5 MB.</div>
<?php endif; ?>
<?php if (isset($errosSala[$_GET['erro'] ?? ''])): ?>
    <div class="alert alert-error"><?= e($errosSala[$_GET['erro']]) ?></div>
<?php elseif (!empty($_GET['erro'])): ?>
    <div class="alert alert-error">Não foi possível concluir esta ação.</div>
<?php endif; ?>

<section class="social-card grupo-head">
    <div class="grupo-capa <?= capaSala($salaId) ?>"></div>
    <a class="grupo-voltar" href="<?= BASE_URL ?>/aluno/salas.php">← Todas as salas</a>
    <div class="grupo-info">
        <?= htmlIconeSala($sala, 'xl') ?>
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
            <?php if ($administra): ?>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>/aluno/sala.php?id=<?= $salaId ?>&aba=config">Configurações</a>
            <?php endif; ?>
            <?php if ($inscrito): ?>
                <span class="grupo-status">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.6 9.2 16.8 19 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?= $administra ? 'Você administra esta sala' : 'Você participa' ?>
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

<?php if ($administra): ?>
<nav class="turma-abas" aria-label="Seções da sala">
    <a class="<?= $aba === 'conversa' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/aluno/sala.php?id=<?= $salaId ?>"<?= $aba === 'conversa' ? ' aria-current="page"' : '' ?>>Conversa</a>
    <a class="<?= $aba === 'config' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/aluno/sala.php?id=<?= $salaId ?>&aba=config"<?= $aba === 'config' ? ' aria-current="page"' : '' ?>>
        Configurações
        <span class="turma-aba-n"><?= $totalMembros ?></span>
    </a>
</nav>
<?php endif; ?>

<?php if ($aba === 'config'): ?>
<section class="panel">
    <div class="panel-head"><h2>Dados da sala</h2></div>
    <form class="stack-form" method="post" action="<?= BASE_URL ?>/aluno/social.php">
        <input type="hidden" name="acao" value="editar_sala">
        <input type="hidden" name="alvo" value="<?= $salaId ?>">
        <div class="form-grid">
            <div class="field">
                <label for="sala-titulo">Nome da sala</label>
                <input id="sala-titulo" name="titulo" type="text" maxlength="150" required value="<?= e($sala['titulo']) ?>">
            </div>
            <div class="field">
                <label for="sala-tema">Tema</label>
                <input id="sala-tema" name="tema" type="text" maxlength="150" value="<?= e($sala['tema']) ?>">
            </div>
            <div class="field span-2">
                <label for="sala-descricao">Sobre o que é a sala</label>
                <textarea id="sala-descricao" name="descricao" rows="3" maxlength="1000"><?= e($sala['descricao']) ?></textarea>
            </div>
            <div class="field span-2">
                <?php campoIconesSala((string) ($sala['icone'] ?? '')); ?>
            </div>
        </div>
        <button class="btn btn-stamp" type="submit">Salvar</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Alunos</h2>
        <span class="lateral-contagem"><?= $totalMembros ?></span>
    </div>
    <p class="panel-lead">Quem você adiciona passa a ver a conversa e pode publicar. Um administrador pode tornar outro aluno administrador da sala.</p>
    <?php foreach ($membrosConfig as $membro): ?>
        <?php
        $ehAdmin = (int) ($membro['admin'] ?? 0) === 1;
        $ehVoce = (int) $membro['id'] === $usuarioId;
        ?>
        <div class="sala-membro-linha">
            <div class="lateral-membro">
                <?= avatarSocial((int) $membro['id'], $membro['nome']) ?>
                <span class="lateral-texto">
                    <strong><?= e($membro['nome']) ?><?= $ehAdmin ? ' · administrador' : '' ?></strong>
                    <small><?= e(descricaoAutor($membro['tipo'], $membro['turma'])) ?></small>
                </span>
            </div>
            <div class="sala-membro-acoes">
                <?php if (!$ehAdmin): ?>
                    <form method="post" action="<?= BASE_URL ?>/aluno/social.php">
                        <input type="hidden" name="acao" value="tornar_admin">
                        <input type="hidden" name="alvo" value="<?= $salaId ?>">
                        <input type="hidden" name="membro_id" value="<?= (int) $membro['id'] ?>">
                        <button class="btn btn-ghost" type="submit">Tornar administrador</button>
                    </form>
                <?php elseif (!$ehVoce): ?>
                    <form method="post" action="<?= BASE_URL ?>/aluno/social.php" data-confirm="Remover a administração de <?= e($membro['nome']) ?>?">
                        <input type="hidden" name="acao" value="remover_admin">
                        <input type="hidden" name="alvo" value="<?= $salaId ?>">
                        <input type="hidden" name="membro_id" value="<?= (int) $membro['id'] ?>">
                        <button class="btn btn-ghost" type="submit">Remover administração</button>
                    </form>
                <?php endif; ?>
                <?php if (!$ehVoce): ?>
                    <form method="post" action="<?= BASE_URL ?>/aluno/social.php" data-confirm="Remover <?= e($membro['nome']) ?> desta sala?">
                        <input type="hidden" name="acao" value="remover_membro">
                        <input type="hidden" name="alvo" value="<?= $salaId ?>">
                        <input type="hidden" name="membro_id" value="<?= (int) $membro['id'] ?>">
                        <button class="btn btn-ghost" type="submit">Remover</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-head"><h2>Adicionar aluno</h2></div>
    <?php if ($candidatos): ?>
        <label class="salas-busca" for="busca-membro">
            <span class="sr-only">Buscar aluno</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input id="busca-membro" type="search" placeholder="Buscar por nome ou turma" data-busca-membro>
        </label>
        <div class="sala-candidatos" data-candidatos>
            <?php foreach ($candidatos as $candidato): ?>
                <div class="sala-membro-linha" data-candidato data-busca="<?= e(mb_strtolower($candidato['nome'] . ' ' . ($candidato['turma'] ?? ''))) ?>">
                    <div class="lateral-membro">
                        <?= avatarSocial((int) $candidato['id'], $candidato['nome']) ?>
                        <span class="lateral-texto">
                            <strong><?= e($candidato['nome']) ?></strong>
                            <small><?= e(descricaoAutor($candidato['tipo'], $candidato['turma'])) ?></small>
                        </span>
                    </div>
                    <form class="sala-membro-acoes" method="post" action="<?= BASE_URL ?>/aluno/social.php">
                        <input type="hidden" name="acao" value="adicionar_membro">
                        <input type="hidden" name="alvo" value="<?= $salaId ?>">
                        <input type="hidden" name="membro_id" value="<?= (int) $candidato['id'] ?>">
                        <button class="btn btn-stamp" type="submit">Adicionar</button>
                        <button class="btn btn-ghost" type="submit" name="como_admin" value="1">Como administrador</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <p class="empty" data-candidatos-vazio hidden>Nenhum aluno com esse nome.</p>
        </div>
    <?php else: ?>
        <p class="empty">Todos os alunos da escola já estão nesta sala.</p>
    <?php endif; ?>
</section>

<section class="panel turma-perigo">
    <div class="panel-head"><h2>Excluir sala</h2></div>
    <p class="panel-lead">A conversa, os materiais e a lista de alunos desta sala são apagados.</p>
    <form method="post" action="<?= BASE_URL ?>/aluno/social.php" data-confirm="Excluir esta sala e toda a conversa?">
        <input type="hidden" name="acao" value="excluir_sala">
        <input type="hidden" name="alvo" value="<?= $salaId ?>">
        <button class="btn btn-ghost" type="submit">Excluir sala</button>
    </form>
</section>
<?php else: ?>
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
                    <p>As publicações, os materiais e os comentários abrem depois da inscrição. O primeiro aluno que entrar administra a sala e pode incluir outros administradores.</p>
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
            $feedModeraPosts = $administra;
            $feedModeraComentarios = $administra;
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
                <li><span>Administração</span><strong><?= e(($sala['criador_nome'] ?? '') !== '' ? $sala['criador_nome'] : 'Primeiro aluno que entrar') ?></strong></li>
                <li><span>Desde</span><strong><?= e(date('d/m/Y', strtotime($sala['data_criacao']))) ?></strong></li>
            </ul>
        </section>

        <section class="social-card lateral-card">
            <div class="lateral-head"><h2>Membros</h2><span class="lateral-contagem"><?= $totalMembros ?></span></div>
            <?php foreach ($membros as $membro): ?>
                <div class="lateral-membro">
                    <?= avatarSocial((int) $membro['id'], $membro['nome']) ?>
                    <span class="lateral-texto">
                        <strong><?= e($membro['nome']) ?><?= (int) ($membro['admin'] ?? 0) === 1 ? ' · administrador' : '' ?></strong>
                        <small><?= e(descricaoAutor($membro['tipo'], $membro['turma'])) ?></small>
                    </span>
                </div>
            <?php endforeach; ?>
        </section>
    </aside>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
