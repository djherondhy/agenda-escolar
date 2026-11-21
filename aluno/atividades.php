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

$tiposValidos = ['prova', 'trabalho', 'atividade', 'reuniao'];
$statusValidos = ['pendentes', 'concluidas', 'nenhuma'];
$origensValidas = ['turma', 'pessoais'];

$filtroTipo = $_GET['tipo'] ?? 'todas';
$filtroStatus = $_GET['status'] ?? 'todas';
$filtroOrigem = $_GET['origem'] ?? 'todas';

if (!in_array($filtroTipo, $tiposValidos, true)) {
    $filtroTipo = 'todas';
}

if (!in_array($filtroStatus, $statusValidos, true)) {
    $filtroStatus = 'todas';
}

if (!in_array($filtroOrigem, $origensValidas, true)) {
    $filtroOrigem = 'todas';
}

$stmt = $pdo->prepare(
    'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora, e.materia, e.concluido
     FROM eventos e
     WHERE e.turma_id = :turma_id
     ORDER BY e.data_evento ASC, e.hora ASC'
);
$stmt->execute(['turma_id' => $aluno['turma_id']]);
$eventos = $stmt->fetchAll();
$pessoais = listarAtividadesPessoais($pdo, (int) $aluno['id']);
$anexosPessoais = listarAnexosPessoais($pdo, (int) $aluno['id']);

$tituloPagina = 'Atividades';
$menuAtivo = 'atividades';
$acaoTopo = '<button class="btn btn-stamp" type="button" data-modal="pessoal">Nova atividade</button>';

require __DIR__ . '/../includes/header.php';

$mostra = static function (string $origem, string $tipo, bool $concluido) use ($filtroOrigem, $filtroTipo, $filtroStatus): bool {
    if ($filtroOrigem !== 'todas' && $filtroOrigem !== $origem) {
        return false;
    }
    if ($origem === 'turma' && $filtroTipo !== 'todas' && $filtroTipo !== $tipo) {
        return false;
    }
    if ($filtroStatus === 'nenhuma') {
        return false;
    }
    if ($filtroStatus === 'pendentes' && $concluido) {
        return false;
    }
    if ($filtroStatus === 'concluidas' && !$concluido) {
        return false;
    }

    return true;
};
?>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-ok" role="status">Atividade pessoal salva.</div>
<?php endif; ?>

<?php if (!empty($_GET['excluido'])): ?>
    <div class="alert alert-ok" role="status">Atividade pessoal excluída.</div>
<?php endif; ?>

<?php if (!empty($_GET['aviso'])): ?>
    <div class="alert alert-error">A atividade foi salva, mas um arquivo não foi aceito. Use imagem, PDF, Word ou slides de até 5 MB.</div>
<?php endif; ?>

<?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-error">Confira os dados da atividade pessoal e tente de novo.</div>
<?php endif; ?>

<?php
$mesesCurtos = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$quandoDe = static function (?string $data) use ($mesesCurtos): array {
    $dia = DateTime::createFromFormat('Y-m-d', substr((string) $data, 0, 10));
    if (!$dia) {
        return ['dia' => '—', 'mes' => ''];
    }

    return [
        'dia' => $dia->format('d'),
        'mes' => $mesesCurtos[(int) $dia->format('n') - 1],
    ];
};
?>
<div
    id="lista-atividades"
    class="atividades"
    data-origem="<?= e($filtroOrigem) ?>"
    data-tipo="<?= e($filtroTipo) ?>"
    data-status="<?= e($filtroStatus) ?>"
>
<section class="atividades-filtros" aria-label="Filtros das atividades">
    <div class="filtro-linha">
        <label class="filtro-campo" data-filtro-grupo="origem">
            <span class="filtro-legenda">Origem</span>
            <select class="filtro-select" data-filtro-select="origem">
                <option value="todas" data-rotulo="Todas" <?= $filtroOrigem === 'todas' ? 'selected' : '' ?>>Todas</option>
                <option value="turma" data-rotulo="Da turma" <?= $filtroOrigem === 'turma' ? 'selected' : '' ?>>Da turma</option>
                <option value="pessoais" data-rotulo="Pessoais" <?= $filtroOrigem === 'pessoais' ? 'selected' : '' ?>>Pessoais</option>
            </select>
        </label>
        <label class="filtro-campo" data-filtro-grupo="tipo" <?= $filtroOrigem === 'pessoais' ? 'hidden' : '' ?>>
            <span class="filtro-legenda">Tipo</span>
            <select class="filtro-select" data-filtro-select="tipo">
                <option value="todas" data-rotulo="Todos" <?= $filtroTipo === 'todas' ? 'selected' : '' ?>>Todos</option>
                <option value="prova" data-rotulo="Provas" <?= $filtroTipo === 'prova' ? 'selected' : '' ?>>Provas</option>
                <option value="trabalho" data-rotulo="Trabalhos" <?= $filtroTipo === 'trabalho' ? 'selected' : '' ?>>Trabalhos</option>
                <option value="atividade" data-rotulo="Atividades" <?= $filtroTipo === 'atividade' ? 'selected' : '' ?>>Atividades</option>
                <option value="reuniao" data-rotulo="Reuniões" <?= $filtroTipo === 'reuniao' ? 'selected' : '' ?>>Reuniões</option>
            </select>
        </label>
    </div>
    <div class="filtro-situacao" data-filtro-grupo="status">
        <span class="filtro-legenda" id="filtro-mostrar">Mostrar</span>
        <div class="filtro-chaves" role="group" aria-labelledby="filtro-mostrar">
            <label class="filtro-chave tom-pendente">
                <input type="checkbox" data-filtro="pendentes" <?= $filtroStatus === 'todas' || $filtroStatus === 'pendentes' ? 'checked' : '' ?>>
                <span>Pendentes <small data-contagem>0</small></span>
            </label>
            <label class="filtro-chave tom-concluida">
                <input type="checkbox" data-filtro="concluidas" <?= $filtroStatus === 'todas' || $filtroStatus === 'concluidas' ? 'checked' : '' ?>>
                <span>Concluídas <small data-contagem>0</small></span>
            </label>
        </div>
    </div>
</section>

<p class="atividades-resumo">
    <span data-atividades-resumo>Mostrando as atividades</span>
    <button class="filtro-limpar" type="button" data-limpar-filtros hidden>Limpar</button>
</p>

<div class="empty-state" id="atividades-vazio" hidden>
    <p>Nenhuma atividade encontrada com esses filtros.</p>
    <button class="btn btn-stamp empty-action" type="button" data-modal="pessoal">Nova atividade</button>
</div>

<?php
$voltarLista = $_SERVER['REQUEST_URI'] ?? (BASE_URL . '/aluno/atividades.php');
?>
<div class="atividade-lista">
<?php foreach ($eventos as $evento): ?>
    <?php $quando = $quandoDe($evento['data_evento']); ?>
    <article class="atividade-item <?= $evento['concluido'] ? 'is-done' : 'is-pending' ?>" data-atividade data-origem="turma" data-tipo="<?= e($evento['tipo']) ?>" data-status="<?= $evento['concluido'] ? 'concluidas' : 'pendentes' ?>" <?= $mostra('turma', $evento['tipo'], (bool) $evento['concluido']) ? '' : 'hidden' ?>>
        <button class="atividade-abrir" type="button" data-modal="evento" data-id="<?= (int) $evento['id'] ?>">
            <span class="atividade-quando" aria-hidden="true">
                <strong><?= e($quando['dia']) ?></strong>
                <small><?= e($quando['mes']) ?></small>
            </span>
            <span class="atividade-texto">
                <span class="atividade-titulo"><?= e($evento['titulo']) ?></span>
                <span class="atividade-detalhe">
                    <?= formatarData($evento['data_evento']) ?>
                    <?= $evento['hora'] ? ' · ' . formatarHora($evento['hora']) : '' ?>
                    <?= !empty($evento['materia']) ? ' · ' . e($evento['materia']) : '' ?>
                </span>
            </span>
        </button>
        <div class="atividade-lado">
            <span class="chip chip-<?= e($evento['tipo']) ?>"><?= e(rotuloTipo($evento['tipo'])) ?></span>
            <span class="atividade-estado"><?= $evento['concluido'] ? 'Concluída' : 'Pendente' ?></span>
        </div>
    </article>
<?php endforeach; ?>

<?php foreach ($pessoais as $atividade): ?>
    <?php
    $quando = $quandoDe($atividade['data_evento']);
    $qtdAnexos = count($anexosPessoais[(int) $atividade['id']] ?? []);
    ?>
    <article class="atividade-item <?= $atividade['concluido'] ? 'is-done' : 'is-pending' ?>" data-atividade data-origem="pessoais" data-tipo="pessoal" data-status="<?= $atividade['concluido'] ? 'concluidas' : 'pendentes' ?>" <?= $mostra('pessoais', 'pessoal', (bool) $atividade['concluido']) ? '' : 'hidden' ?>>
        <button class="atividade-abrir" type="button" data-modal="pessoal" data-id="<?= (int) $atividade['id'] ?>">
            <span class="atividade-quando" aria-hidden="true">
                <strong><?= e($quando['dia']) ?></strong>
                <small><?= e($quando['mes']) ?></small>
            </span>
            <span class="atividade-texto">
                <span class="atividade-titulo"><?= e($atividade['titulo']) ?></span>
                <span class="atividade-detalhe">
                    <?= formatarData($atividade['data_evento']) ?>
                    <?= $atividade['hora'] ? ' · ' . formatarHora($atividade['hora']) : '' ?>
                    <?= $atividade['materia'] !== '' ? ' · ' . e($atividade['materia']) : '' ?>
                    <?= $qtdAnexos ? ' · ' . $qtdAnexos . ' arquivo' . ($qtdAnexos === 1 ? '' : 's') : '' ?>
                </span>
            </span>
        </button>
        <div class="atividade-lado">
            <span class="chip chip-outro">Pessoal</span>
            <span class="atividade-estado"><?= $atividade['concluido'] ? 'Concluída' : 'Pendente' ?></span>
            <form method="post" action="<?= BASE_URL ?>/aluno/concluir_pessoal.php">
                <input type="hidden" name="id" value="<?= (int) $atividade['id'] ?>">
                <input type="hidden" name="voltar" value="<?= e($voltarLista) ?>">
                <button class="btn btn-concluir<?= $atividade['concluido'] ? ' is-done' : '' ?>" type="submit" aria-label="<?= $atividade['concluido'] ? 'Desmarcar conclusão' : 'Marcar como concluída' ?>" title="<?= $atividade['concluido'] ? 'Desmarcar conclusão' : 'Marcar como concluída' ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.6 9.2 16.8 19 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </form>
        </div>
    </article>
<?php endforeach; ?>
</div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
