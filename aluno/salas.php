<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirEscola();

$pdo = getPDO();
$usuarioId = (int) $_SESSION['usuario_id'];
$salas = listarSalas($pdo, $usuarioId);
foreach ($salas as &$sala) {
    $sala['membros'] = listarMembrosSala($pdo, (int) $sala['id'], 4);
}
unset($sala);
$minhas = count(array_filter($salas, static fn ($sala) => (bool) $sala['inscrito']));

$tituloPagina = 'Salas de estudo';
$menuAtivo = 'salas';

require __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-error">Dê um nome para a sala antes de criar.</div>
<?php endif; ?>

<section class="social-card salas-hero">
    <div class="salas-hero-texto">
        <span class="salas-hero-kicker">Comunidade</span>
        <h2>Estude junto com alunos de todas as turmas</h2>
        <p>Entre em uma sala para ver os materiais, publicar resumos e tirar dúvidas na conversa.</p>
    </div>
    <div class="salas-hero-numeros">
        <div><strong><?= count($salas) ?></strong><span>salas abertas</span></div>
        <div><strong><?= $minhas ?></strong><span>você participa</span></div>
    </div>
</section>

<details class="social-card nova-sala" id="nova-sala" <?= !$salas || !empty($_GET['erro']) ? 'open' : '' ?>>
    <summary>
        <span class="nova-sala-mais" aria-hidden="true">+</span>
        <span>
            <strong>Criar uma sala de estudo</strong>
            <small>Você entra automaticamente e pode convidar a turma toda.</small>
        </span>
    </summary>
    <form class="nova-sala-form" method="post" action="<?= BASE_URL ?>/aluno/social.php">
        <input type="hidden" name="acao" value="criar_sala">
        <div class="form-grid">
            <div class="field">
                <label for="sala-titulo">Nome da sala</label>
                <input id="sala-titulo" name="titulo" type="text" maxlength="150" required placeholder="Revisão de funções">
            </div>
            <div class="field">
                <label for="sala-tema">Tema</label>
                <input id="sala-tema" name="tema" type="text" maxlength="150" placeholder="Matemática">
            </div>
            <div class="field span-2">
                <label for="sala-descricao">Sobre o que é a sala</label>
                <textarea id="sala-descricao" name="descricao" rows="3" maxlength="1000" placeholder="Quem entra encontra resumos, dúvidas e material de estudo."></textarea>
            </div>
        </div>
        <button class="btn btn-stamp" type="submit">Criar e entrar</button>
    </form>
</details>

<?php if ($salas): ?>
    <div class="salas-filtros" data-salas-filtros>
        <div class="salas-abas" role="tablist" aria-label="Filtrar salas">
            <button type="button" class="is-active" data-filtro-sala="todas" role="tab" aria-selected="true">Todas</button>
            <button type="button" data-filtro-sala="minhas" role="tab" aria-selected="false">Minhas salas</button>
            <button type="button" data-filtro-sala="novas" role="tab" aria-selected="false">Para entrar</button>
        </div>
        <label class="salas-busca">
            <span class="sr-only">Buscar sala</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input type="search" placeholder="Buscar por nome ou tema" data-busca-sala>
        </label>
    </div>
<?php endif; ?>

<section class="sala-grid" data-sala-grid>
    <?php foreach ($salas as $sala): ?>
        <article class="social-card sala-card" data-sala data-inscrito="<?= $sala['inscrito'] ? '1' : '0' ?>" data-busca="<?= e(mb_strtolower($sala['titulo'] . ' ' . $sala['tema'] . ' ' . $sala['descricao'])) ?>">
            <?php if ($sala['inscrito']): ?>
                <span class="sala-selo">Você participa</span>
            <?php endif; ?>
            <div class="sala-corpo">
                <span class="sala-icone sala-icone-lg <?= capaSala((int) $sala['id']) ?>"><?= e(iniciaisNome($sala['titulo'])) ?></span>
                <p class="sala-tema"><?= e($sala['tema'] !== '' ? $sala['tema'] : 'Estudo livre') ?></p>
                <h2><a href="<?= BASE_URL ?>/aluno/sala.php?id=<?= (int) $sala['id'] ?>"><?= e($sala['titulo']) ?></a></h2>
                <p class="sala-descricao"><?= e($sala['descricao'] ?: 'Sala aberta para publicar resumos e comentar.') ?></p>
                <div class="sala-membros">
                    <span class="avatar-pilha">
                        <?php foreach ($sala['membros'] as $membro): ?>
                            <?= avatarSocial((int) $membro['id'], $membro['nome'], 'sm') ?>
                        <?php endforeach; ?>
                    </span>
                    <small><?= (int) $sala['inscritos'] ?> membro<?= (int) $sala['inscritos'] === 1 ? '' : 's' ?> · criada por <?= e(primeiroNome($sala['criador_nome'])) ?></small>
                </div>
            </div>
            <div class="sala-rodape">
                <?php if ($sala['inscrito']): ?>
                    <a class="btn btn-stamp" href="<?= BASE_URL ?>/aluno/sala.php?id=<?= (int) $sala['id'] ?>">Abrir sala</a>
                <?php else: ?>
                    <form method="post" action="<?= BASE_URL ?>/aluno/social.php">
                        <input type="hidden" name="acao" value="inscrever">
                        <input type="hidden" name="alvo" value="<?= (int) $sala['id'] ?>">
                        <button class="btn btn-stamp" type="submit">+ Participar</button>
                    </form>
                    <a class="btn btn-ghost" href="<?= BASE_URL ?>/aluno/sala.php?id=<?= (int) $sala['id'] ?>">Ver detalhes</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<div class="social-card social-empty" data-salas-vazio hidden>
    <strong>Nenhuma sala neste filtro</strong>
    <p>Troque o filtro ou crie uma sala nova sobre o conteúdo que você quer estudar.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
