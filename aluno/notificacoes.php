<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/push.php';

exigirAluno();

$pdo = getPDO();
$aluno = obterAlunoPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$aluno) {
    encerrarSessao();
    redirecionar('/login.php');
}

$notificacoes = listarNotificacoesAluno($pdo, (int) $aluno['id']);
try {
    $chavePush = garantirChavesVapid()['public'];
} catch (Throwable $e) {
    $chavePush = '';
}

$tituloPagina = 'Notificações';
$menuAtivo = 'dashboard';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <h2>Avisos da turma</h2>
    <p class="panel-lead">Quando um representante cadastra uma atividade, ela aparece aqui e, se você ativou, também no celular.</p>
    <?php if ($chavePush !== ''): ?>
        <button class="btn btn-stamp" type="button" id="ativar-push" data-vapid="<?= e($chavePush) ?>">Ativar avisos neste celular</button>
        <p class="panel-lead" id="push-status">O aviso na tela bloqueada do celular só chega se este site estiver aberto em HTTPS.</p>
    <?php else: ?>
        <p class="panel-lead" id="push-status">O sino dentro do sistema continua funcionando. O aviso no celular não pôde ser preparado neste servidor.</p>
    <?php endif; ?>

    <?php if (!$notificacoes): ?>
        <div class="empty-state">
            <p>Nenhuma notificação por enquanto.</p>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notificacoes as $aviso): ?>
                <a class="notif-item <?= $aviso['lida'] ? 'is-read' : '' ?>" href="<?= BASE_URL ?>/aluno/notificacao.php?id=<?= (int) $aviso['id'] ?>">
                    <span class="chip chip-<?= e($aviso['tipo']) ?>"><?= e(rotuloTipo($aviso['tipo'])) ?></span>
                    <strong><?= e($aviso['titulo']) ?></strong>
                    <span><?= e($aviso['professor_nome']) ?> · <?= formatarData($aviso['data_evento']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
