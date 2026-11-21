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
    <p class="panel-lead">Quando um representante cadastra uma atividade, ela aparece aqui e, se você ativar, também como aviso no navegador e no celular.</p>
    <?php if ($chavePush !== ''): ?>
        <div class="push-caixa">
            <p class="push-status" id="push-status" role="status">Verificando…</p>
            <div class="form-actions">
                <button class="btn btn-stamp" type="button" id="push-ativar" hidden>Ativar avisos neste aparelho</button>
                <button class="btn btn-ghost" type="button" id="push-teste" hidden>Enviar aviso de teste</button>
                <button class="btn btn-ghost" type="button" id="push-desativar" hidden>Desativar</button>
            </div>
            <div class="push-dica" id="push-dica" hidden>
                <strong>iPhone / iPad (iOS 16.4 ou mais novo)</strong>
                <ol>
                    <li>Abra este site no <strong>Safari</strong>.</li>
                    <li>Toque em <strong>Compartilhar</strong> e depois em <strong>Adicionar à Tela de Início</strong>.</li>
                    <li>Abra a Agenda pelo <strong>ícone na tela inicial</strong>, entre na conta e volte aqui para ativar os avisos.</li>
                </ol>
            </div>
            <details class="push-ajuda">
                <summary>Não recebi os avisos</summary>
                <ul>
                    <li>No <strong>Android</strong> (Chrome): permita as notificações quando o navegador perguntar. Se bloqueou, toque no cadeado ao lado do endereço &rarr; Permissões &rarr; Notificações.</li>
                    <li>No <strong>iPhone</strong>: só funciona com o app adicionado à Tela de Início (passo a passo acima).</li>
                    <li>No <strong>computador</strong>: deixe o navegador aberto (pode estar minimizado) e confira se o Windows/macOS não está em "Não perturbe".</li>
                    <li>Cada aparelho e cada navegador precisa ser ativado uma vez.</li>
                </ul>
            </details>
        </div>
    <?php else: ?>
        <p class="panel-lead">O sino dentro do sistema continua funcionando. O aviso no aparelho não pôde ser preparado neste servidor.</p>
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
