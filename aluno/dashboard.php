<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAluno();

$pdo = getPDO();
$aluno = obterAlunoPorUsuario($pdo, (int) $_SESSION['usuario_id']);
$ehRepresentante = usuarioEhRepresentante();

if (!$aluno) {
    encerrarSessao();
    redirecionar('/login.php');
}

$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM eventos WHERE turma_id = :turma_id');
$stmtTotal->execute(['turma_id' => $aluno['turma_id']]);
$totalAtividades = (int) $stmtTotal->fetchColumn();

$stmtConcluidas = $pdo->prepare(
    'SELECT COUNT(*) FROM eventos WHERE turma_id = :turma_id AND concluido = 1'
);
$stmtConcluidas->execute(['turma_id' => $aluno['turma_id']]);
$totalConcluidas = (int) $stmtConcluidas->fetchColumn();

$totalPendentes = max($totalAtividades - $totalConcluidas, 0);
$percentual = $totalAtividades > 0 ? (int) round(($totalConcluidas / $totalAtividades) * 100) : 0;

$stmtLista = $pdo->prepare(
    'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora, e.concluido
     FROM eventos e
     WHERE e.turma_id = :turma_id AND e.data_evento >= CURDATE()
     ORDER BY e.data_evento ASC, e.hora ASC
     LIMIT 6'
);
$stmtLista->execute(['turma_id' => $aluno['turma_id']]);
$proximas = $stmtLista->fetchAll();

$horarios = listarHorariosDaTurma($pdo, (int) $aluno['turma_id']);
$proximaAula = proximaAulaHoje($horarios);
$pessoais = listarAtividadesPessoais($pdo, (int) $aluno['id']);
$pessoaisFuturas = array_values(array_filter(
    $pessoais,
    static fn ($item) => $item['data_evento'] >= date('Y-m-d') && !$item['concluido']
));

$agenda = [];
foreach ($proximas as $evento) {
    $agenda[] = [
        'quando'    => $evento['data_evento'] . ' ' . ($evento['hora'] ?: '23:59:59'),
        'tipo'      => 'turma',
        'item'      => $evento,
    ];
}
foreach (array_slice($pessoaisFuturas, 0, 6) as $atividade) {
    $agenda[] = [
        'quando' => $atividade['data_evento'] . ' ' . ($atividade['hora'] ?: '23:59:59'),
        'tipo'   => 'pessoal',
        'item'   => $atividade,
    ];
}
usort($agenda, static fn ($a, $b) => $a['quando'] <=> $b['quando']);
$agenda = array_slice($agenda, 0, 4);

$usuarioId = (int) $_SESSION['usuario_id'];
$stmtMeus = $pdo->prepare('SELECT COUNT(*) FROM mural_posts WHERE autor_id = :autor AND turma_id = :turma');
$stmtMeus->execute(['autor' => $usuarioId, 'turma' => $aluno['turma_id']]);
$minhasPublicacoes = (int) $stmtMeus->fetchColumn();

$salas = listarSalas($pdo, $usuarioId);
$minhasSalas = count(array_filter($salas, static fn ($sala) => (bool) $sala['inscrito']));
$salasSugeridas = array_slice($salas, 0, 3);
$feedPosts = listarPostsComunitarios($pdo, 'mural', (int) $aluno['turma_id']);

$tituloPagina = 'Mural';
$menuAtivo = 'mural';
$acaoTopo = '<button class="btn btn-stamp" type="button" data-modal="pessoal">Nova atividade</button>';

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

<div class="social-layout">
    <aside class="social-left">
        <section class="social-card perfil-card">
            <div class="perfil-capa capa-<?= ((int) $aluno['turma_id'] % 5) + 1 ?>"></div>
            <div class="perfil-corpo">
                <?= avatarSocial($usuarioId, $aluno['nome'], 'xl') ?>
                <h2><?= e($aluno['nome']) ?></h2>
                <?php if ($ehRepresentante): ?>
                    <span class="perfil-turma">Representante de turma</span>
                <?php endif; ?>
                <button class="perfil-turma" type="button" data-modal="turma" data-id="<?= (int) $aluno['turma_id'] ?>"><?= e($aluno['turma_nome']) ?></button>
            </div>
            <dl class="perfil-numeros">
                <div><dt>Publicações</dt><dd><?= $minhasPublicacoes ?></dd></div>
                <div><dt>Salas</dt><dd><?= $minhasSalas ?></dd></div>
                <div><dt>Pendentes</dt><dd><?= $totalPendentes ?></dd></div>
            </dl>
            <div class="perfil-progresso">
                <div class="perfil-progresso-texto">
                    <span>Atividades da turma</span>
                    <strong><?= $percentual ?>%</strong>
                </div>
                <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percentual ?>" aria-label="Progresso das atividades">
                    <span class="progress-fill" style="width: <?= $percentual ?>%"></span>
                </div>
            </div>
        </section>

        <nav class="social-card atalhos-card" aria-label="Atalhos">
            <a href="<?= BASE_URL ?>/aluno/horarios.php"><span class="atalho-ponto ponto-coral"></span>Horários da semana</a>
            <a href="<?= BASE_URL ?>/aluno/disciplinas.php"><span class="atalho-ponto ponto-amber"></span>Disciplinas</a>
            <a href="<?= BASE_URL ?>/aluno/atividades.php?origem=pessoais"><span class="atalho-ponto ponto-teal"></span>Atividades pessoais</a>
            <a href="<?= BASE_URL ?>/aluno/salas.php"><span class="atalho-ponto ponto-violet"></span>Salas de estudo</a>
        </nav>
    </aside>

    <div class="social-feed">
        <?php
        $feedPodePublicar = true;
        $feedUsuarioId = $usuarioId;
        $feedModeraPosts = $ehRepresentante;
        $feedModeraComentarios = $ehRepresentante;
        $feedContexto = 'mural';
        $feedAlvo = (int) $aluno['turma_id'];
        $feedVoltar = BASE_URL . '/aluno/dashboard.php';
        require __DIR__ . '/../includes/feed_social.php';
        ?>
    </div>

    <aside class="social-right">
        <section class="social-card lateral-card lateral-agenda">
            <div class="lateral-head">
                <h2>O que vem pela frente</h2>
                <a href="<?= BASE_URL ?>/aluno/atividades.php">Ver tudo</a>
            </div>

            <?php if ($proximaAula): ?>
                <a class="lateral-destaque" href="<?= BASE_URL ?>/aluno/horarios.php">
                    <small>Próxima aula hoje</small>
                    <strong><?= e(rotuloMateria($proximaAula['materia'])) ?></strong>
                    <span><?= formatarHora($proximaAula['hora_inicio']) ?><?= $proximaAula['hora_fim'] ? ' – ' . formatarHora($proximaAula['hora_fim']) : '' ?> · <?= e($proximaAula['professor_nome']) ?></span>
                </a>
            <?php endif; ?>

            <?php if (!$agenda): ?>
                <p class="lateral-vazio">Nada marcado daqui pra frente.</p>
                <button class="btn btn-ghost lateral-botao" type="button" data-modal="pessoal">Nova atividade pessoal</button>
            <?php endif; ?>

            <?php foreach ($agenda as $entrada): ?>
                <?php $item = $entrada['item']; ?>
                <?php $pessoal = $entrada['tipo'] === 'pessoal'; ?>
                <button class="lateral-item <?= $item['concluido'] ? 'is-done' : 'is-pending' ?>" type="button" data-modal="<?= $pessoal ? 'pessoal' : 'evento' ?>" data-id="<?= (int) $item['id'] ?>">
                    <span class="lateral-data">
                        <strong><?= e(date('d', strtotime($item['data_evento']))) ?></strong>
                        <small><?= e(mb_strtoupper(mb_substr(['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'][(int) date('n', strtotime($item['data_evento'])) - 1], 0, 3))) ?></small>
                    </span>
                    <span class="lateral-texto">
                        <strong><?= e($item['titulo']) ?></strong>
                        <small>
                            <?= $pessoal ? 'Pessoal' : e(rotuloTipo($item['tipo'])) ?>
                            · <?= e(rotuloPrazo($item['data_evento'])) ?>
                            <?= $item['hora'] ? ' · ' . formatarHora($item['hora']) : '' ?>
                        </small>
                    </span>
                </button>
            <?php endforeach; ?>
        </section>

        <section class="social-card lateral-card lateral-salas">
            <div class="lateral-head">
                <h2>Salas de estudo</h2>
                <a href="<?= BASE_URL ?>/aluno/salas.php">Ver todas</a>
            </div>
            <?php if (!$salasSugeridas): ?>
                <p class="lateral-vazio">Nenhuma sala criada ainda.</p>
            <?php endif; ?>
            <?php foreach ($salasSugeridas as $sala): ?>
                <a class="lateral-sala" href="<?= BASE_URL ?>/aluno/sala.php?id=<?= (int) $sala['id'] ?>">
                    <?= htmlIconeSala($sala) ?>
                    <span class="lateral-texto">
                        <strong><?= e($sala['titulo']) ?></strong>
                        <small><?= (int) $sala['inscritos'] ?> membro<?= (int) $sala['inscritos'] === 1 ? '' : 's' ?><?= $sala['inscrito'] ? ' · você participa' : '' ?></small>
                    </span>
                </a>
            <?php endforeach; ?>
            <a class="btn btn-ghost lateral-botao" href="<?= BASE_URL ?>/aluno/salas.php#nova-sala">Criar uma sala</a>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
