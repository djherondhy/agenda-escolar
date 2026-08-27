<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAluno();

$pdo = getPDO();
$aluno = obterAlunoPorUsuario($pdo, (int) $_SESSION['usuario_id']);

if (!$aluno) {
    encerrarSessao();
    redirecionar('/login.php');
}

$eventoId = (int) ($_GET['id'] ?? 0);
$evento = obterEventoDaTurma($pdo, $eventoId, (int) $aluno['turma_id']);

if (!$evento) {
    redirecionar('/aluno/atividades.php');
}

$concluido = eventoConcluido($pdo, (int) $aluno['id'], $eventoId);
$anexos = listarAnexos($pdo, $eventoId);

$tituloPagina = $evento['titulo'];
$menuAtivo = 'atividades';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <span class="chip chip-<?= e($evento['tipo']) ?>"><?= e(rotuloTipo($evento['tipo'])) ?></span>
    <h2><?= e($evento['titulo']) ?></h2>

    <dl class="detail-meta">
        <div>
            <dt>Data</dt>
            <dd><?= formatarData($evento['data_evento']) ?></dd>
        </div>
        <div>
            <dt>Horário</dt>
            <dd><?= $evento['hora'] ? formatarHora($evento['hora']) : 'Sem horário' ?></dd>
        </div>
        <div>
            <dt>Turma</dt>
            <dd>
                <button class="link-modal" type="button" data-modal="turma" data-id="<?= (int) $evento['turma_id'] ?>">
                    <?= e($evento['turma_nome']) ?>
                </button>
            </dd>
        </div>
        <div>
            <dt>Professor</dt>
            <dd><?= e($evento['professor_nome']) ?></dd>
        </div>
    </dl>

    <?php if ($evento['descricao']): ?>
        <div class="rich-text"><?= descricaoHtml($evento['descricao']) ?></div>
    <?php endif; ?>

    <?php if ($anexos): ?>
        <h3 class="section-title">Anexos</h3>
        <ul class="anexo-list">
            <?php foreach ($anexos as $anexo): ?>
                <li>
                    <?php if (anexoEhImagem($anexo['tipo_mime'])): ?>
                        <a href="<?= e(UPLOAD_URL . '/' . $anexo['caminho']) ?>" target="_blank" rel="noopener">
                            <img src="<?= e(UPLOAD_URL . '/' . $anexo['caminho']) ?>" alt="<?= e($anexo['nome_original']) ?>">
                        </a>
                    <?php else: ?>
                        <a href="<?= e(UPLOAD_URL . '/' . $anexo['caminho']) ?>" target="_blank" rel="noopener"><?= e($anexo['nome_original']) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/aluno/concluir_evento.php">
        <input type="hidden" name="id" value="<?= $eventoId ?>">
        <input type="hidden" name="voltar" value="<?= BASE_URL ?>/aluno/atividade.php?id=<?= $eventoId ?>">
        <button class="btn <?= $concluido ? 'btn-ghost' : 'btn-stamp' ?>" type="submit">
            <?= $concluido ? 'Desmarcar conclusão' : 'Marcar como concluída' ?>
        </button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
