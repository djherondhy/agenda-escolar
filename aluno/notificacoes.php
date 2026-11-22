<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/push.php';

exigirEscola();

$pdo = getPDO();
$usuarioId = (int) $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if ($acao === 'lida') {
        marcarNotificacaoLida($pdo, (int) ($_POST['id'] ?? 0), $usuarioId);
        redirecionar('/aluno/notificacoes.php?ok=lida');
    }
    if ($acao === 'lidas') {
        marcarNotificacoesLidas($pdo, $usuarioId);
        redirecionar('/aluno/notificacoes.php?ok=lidas');
    }
    if ($acao === 'excluir') {
        excluirNotificacao($pdo, (int) ($_POST['id'] ?? 0), $usuarioId);
        redirecionar('/aluno/notificacoes.php?ok=excluida');
    }
    if ($acao === 'limpar') {
        limparNotificacoes($pdo, $usuarioId);
        redirecionar('/aluno/notificacoes.php?ok=limpa');
    }
    redirecionar('/aluno/notificacoes.php');
}

$notificacoes = listarNotificacoesUsuario($pdo, $usuarioId);
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
    <h2>Avisos</h2>
    <p class="panel-lead">Atividades da turma, publicações, comentários e o lembrete do dia anterior aparecem aqui. Com os avisos ativados, o mesmo recado chega no navegador e no celular.</p>

    <?php
    $avisosOk = [
        'lida'     => 'Notificação marcada como lida.',
        'lidas'    => 'Todas as notificações foram marcadas como lidas.',
        'excluida' => 'Notificação excluída.',
        'limpa'    => 'Notificações limpas.',
    ];
    ?>
    <?php if (isset($avisosOk[$_GET['ok'] ?? ''])): ?>
        <div class="alert alert-ok" role="status"><?= e($avisosOk[$_GET['ok']]) ?></div>
    <?php endif; ?>

    <?php if (!$notificacoes): ?>
        <div class="empty-state">
            <p>Nenhuma notificação por enquanto.</p>
        </div>
    <?php else: ?>
        <?php $temNaoLida = (bool) array_filter($notificacoes, static fn (array $aviso): bool => !(int) $aviso['lida']); ?>
        <div class="notif-barra">
            <?php if ($temNaoLida): ?>
                <form method="post" action="<?= BASE_URL ?>/aluno/notificacoes.php">
                    <input type="hidden" name="acao" value="lidas">
                    <button class="btn btn-ghost" type="submit">Marcar todas como lidas</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/aluno/notificacoes.php" data-confirm="Apagar todas as notificações?">
                <input type="hidden" name="acao" value="limpar">
                <button class="btn btn-ghost" type="submit">Limpar</button>
            </form>
        </div>
        <div class="notif-list">
            <?php foreach ($notificacoes as $aviso): ?>
                <?php
                $tipoChip = (string) ($aviso['tipo'] ?: 'atividade');
                if ($tipoChip === 'atividade' && !empty($aviso['evento_tipo'])) {
                    $tipoChip = (string) $aviso['evento_tipo'];
                }
                $tituloAviso = $aviso['titulo'] !== '' ? $aviso['titulo'] : (string) ($aviso['evento_titulo'] ?? 'Aviso');
                if ($aviso['mensagem'] !== '') {
                    $detalheAviso = (string) $aviso['mensagem'];
                } elseif (!empty($aviso['data_evento'])) {
                    $detalheAviso = trim((string) ($aviso['professor_nome'] ?? '') . ' · ' . formatarData($aviso['data_evento']), ' ·');
                } else {
                    $detalheAviso = formatarData(substr((string) $aviso['data_criacao'], 0, 10));
                }
                $lida = (bool) (int) $aviso['lida'];
                ?>
                <article class="notif-item <?= $lida ? 'is-read' : '' ?>">
                    <a class="notif-corpo" href="<?= BASE_URL ?>/aluno/notificacao.php?id=<?= (int) $aviso['id'] ?>">
                        <span class="chip chip-<?= e($tipoChip) ?>"><?= e(rotuloAviso($tipoChip)) ?></span>
                        <strong><?= e($tituloAviso) ?></strong>
                        <span class="notif-detalhe"><?= e($detalheAviso) ?></span>
                    </a>
                    <div class="notif-botoes">
                        <?php if (!$lida): ?>
                            <form method="post" action="<?= BASE_URL ?>/aluno/notificacoes.php">
                                <input type="hidden" name="acao" value="lida">
                                <input type="hidden" name="id" value="<?= (int) $aviso['id'] ?>">
                                <button class="btn btn-ghost" type="submit">Marcar como lida</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= BASE_URL ?>/aluno/notificacoes.php" data-confirm="Excluir esta notificação?">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= (int) $aviso['id'] ?>">
                            <button class="btn btn-ghost" type="submit">Excluir</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

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
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
