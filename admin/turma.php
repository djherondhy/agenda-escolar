<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

$pdo = getPDO();
$turmaId = (int) ($_GET['id'] ?? $_POST['turma_id'] ?? 0);
$turma = obterTurma($pdo, $turmaId);

if (!$turma) {
    redirecionar('/admin/turmas.php');
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'adicionar') {
        $professorId = (int) ($_POST['professor_id'] ?? 0);
        $materias = normalizarMaterias((string) ($_POST['materia'] ?? ''));

        $existe = $pdo->prepare('SELECT id FROM professores WHERE id = :id LIMIT 1');
        $existe->execute(['id' => $professorId]);

        if (!$existe->fetchColumn()) {
            $erros[] = 'Selecione um professor.';
        } elseif (!$materias) {
            $erros[] = 'Informe pelo menos uma matéria.';
        } else {
            $inserir = $pdo->prepare(
                'INSERT IGNORE INTO professor_turmas (professor_id, turma_id, materia)
                 VALUES (:professor_id, :turma_id, :materia)'
            );
            $novas = 0;

            foreach ($materias as $materia) {
                $inserir->execute([
                    'professor_id' => $professorId,
                    'turma_id'     => $turmaId,
                    'materia'      => $materia,
                ]);
                $novas += $inserir->rowCount();
            }

            redirecionar('/admin/turma.php?id=' . $turmaId . ($novas ? '&ok=vinculo' : '&ok=existe'));
        }
    }

    if (in_array($acao, ['adicionar_horario', 'remover_horario'], true)) {
        $resultado = processarHorarioTurma($pdo, $_POST, $turmaId, null, $erros);
        if ($resultado) {
            redirecionar('/admin/turma.php?id=' . $turmaId . '&ok=' . $resultado);
        }
    }

    if ($acao === 'remover') {
        $vinculoId = (int) ($_POST['vinculo_id'] ?? 0);
        $delete = $pdo->prepare(
            'DELETE FROM professor_turmas WHERE id = :id AND turma_id = :turma_id'
        );
        $delete->execute([
            'id'       => $vinculoId,
            'turma_id' => $turmaId,
        ]);
        redirecionar('/admin/turma.php?id=' . $turmaId . '&ok=removido');
    }
}

$alunos = listarAlunosDaTurma($pdo, $turmaId);
$professores = agruparProfessoresDaTurma(listarVinculosDaTurma($pdo, $turmaId));
$todosProfessores = listarProfessores($pdo);
$horariosPainel = listarHorariosDaTurma($pdo, $turmaId);
$opcoesHorario = opcoesHorarioDaTurma($professores);
$actionHorario = BASE_URL . '/admin/turma.php?id=' . $turmaId;
$errosHorario = [];

$tituloPagina = $turma['nome'];
$menuAtivo = 'turmas';
$acaoTopo = '<a class="btn btn-ghost" href="' . BASE_URL . '/admin/editar_turma.php?id=' . $turmaId . '">Editar</a>';

require __DIR__ . '/../includes/header.php';
?>

<p class="back-link"><a href="<?= BASE_URL ?>/admin/turmas.php">Turmas</a></p>

<?php if (($_GET['ok'] ?? '') === 'vinculo'): ?>
    <div class="alert alert-ok" role="status">Matéria vinculada ao professor.</div>
<?php elseif (($_GET['ok'] ?? '') === 'existe'): ?>
    <div class="alert alert-ok" role="status">Esse vínculo já existia.</div>
<?php elseif (($_GET['ok'] ?? '') === 'removido'): ?>
    <div class="alert alert-ok" role="status">Vínculo removido.</div>
<?php elseif (($_GET['ok'] ?? '') === 'editada'): ?>
    <div class="alert alert-ok" role="status">Turma atualizada.</div>
<?php elseif (($_GET['erro'] ?? '') === 'alunos'): ?>
    <div class="alert alert-error">Mova os alunos para outra turma antes de excluir.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_ok'): ?>
    <div class="alert alert-ok" role="status">Horário adicionado.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_removido'): ?>
    <div class="alert alert-ok" role="status">Horário removido.</div>
<?php endif; ?>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="hello">
    <h2 class="display-name"><?= e($turma['nome']) ?></h2>
    <p>
        <?= e($turma['ano'] ?: 'Ano não informado') ?>
        · <?= count($alunos) ?> aluno<?= count($alunos) === 1 ? '' : 's' ?>
        · <?= count($professores) ?> professor<?= count($professores) === 1 ? '' : 'es' ?>
    </p>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Professores</h2>
    </div>
    <p class="panel-lead">O mesmo professor pode dar mais de uma matéria nesta turma.</p>

    <?php if (!$professores): ?>
        <p class="empty">Nenhum professor vinculado ainda.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Professor</th>
                        <th>E-mail</th>
                        <th>Matérias</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($professores as $professor): ?>
                        <tr>
                            <td data-label="Professor"><?= e($professor['nome']) ?></td>
                            <td data-label="E-mail"><?= e($professor['email']) ?></td>
                            <td data-label="Matérias">
                                <div class="materia-list">
                                    <?php foreach ($professor['materias'] as $item): ?>
                                        <span class="materia-item">
                                            <span class="chip chip-professor"><?= e(rotuloMateria($item['materia'])) ?></span>
                                            <form method="post" action="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>" data-confirm="Remover esta matéria deste professor?">
                                                <input type="hidden" name="acao" value="remover">
                                                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                                                <input type="hidden" name="vinculo_id" value="<?= (int) $item['id'] ?>">
                                                <button class="btn btn-ghost btn-compact" type="submit">Remover</button>
                                            </form>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($todosProfessores): ?>
        <form class="stack-form" method="post" action="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>">
            <input type="hidden" name="acao" value="adicionar">
            <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
            <h3 class="section-title">Adicionar matéria</h3>
            <div class="form-grid">
                <div class="field">
                    <label for="professor_id">Professor</label>
                    <select id="professor_id" name="professor_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($todosProfessores as $professor): ?>
                            <option value="<?= (int) $professor['id'] ?>"><?= e($professor['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="materia">Matéria</label>
                    <input id="materia" name="materia" type="text" maxlength="100" required placeholder="Ex.: Matemática">
                    <small class="field-help">Para várias de uma vez, separe por vírgula.</small>
                </div>
            </div>
            <button class="btn btn-stamp" type="submit">Adicionar</button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/horario_painel.php'; ?>

<section class="panel">
    <div class="panel-head">
        <h2>Alunos</h2>
    </div>

    <?php if (!$alunos): ?>
        <div class="empty-state">
            <p>Nenhum aluno nesta turma.</p>
            <a class="btn btn-ghost empty-action" href="<?= BASE_URL ?>/admin/adicionar_usuario.php?tipo=aluno">Cadastrar aluno</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunos as $aluno): ?>
                        <tr>
                            <td data-label="Nome">
                                <a href="<?= BASE_URL ?>/admin/editar_usuario.php?id=<?= (int) $aluno['usuario_id'] ?>"><?= e($aluno['nome']) ?></a>
                            </td>
                            <td data-label="E-mail"><?= e($aluno['email']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Excluir turma</h2>
    <?php if ($alunos): ?>
        <p class="empty">Mova os <?= count($alunos) ?> aluno<?= count($alunos) === 1 ? '' : 's' ?> para outra turma antes de excluir.</p>
    <?php else: ?>
        <p class="panel-lead">As atividades desta turma também serão excluídas.</p>
        <form method="post" action="<?= BASE_URL ?>/admin/excluir_turma.php" data-confirm="Excluir esta turma e as atividades dela?">
            <input type="hidden" name="id" value="<?= $turmaId ?>">
            <button class="btn btn-danger" type="submit">Excluir turma</button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
