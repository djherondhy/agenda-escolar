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

    if (in_array($acao, ['mover_horario', 'definir_horario', 'esvaziar_horario', 'gerar_horario'], true)) {
        $resultadoQuadro = processarQuadroHorario($pdo, $_POST, $turmaId, $erros);
        if (($_POST['ajax'] ?? '') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'   => $resultadoQuadro !== null,
                'erro' => $erros[0] ?? '',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($resultadoQuadro) {
            redirecionar('/admin/turma.php?id=' . $turmaId . '&aba=horarios&ok=' . $resultadoQuadro);
        }
    }

    if ($acao === 'adicionar') {
        $professorId = (int) ($_POST['professor_id'] ?? 0);
        $materias = normalizarMaterias((string) ($_POST['materia'] ?? ''));

        $existe = $pdo->prepare('SELECT id FROM professores WHERE id = :id LIMIT 1');
        $existe->execute(['id' => $professorId]);

        if (!$existe->fetchColumn()) {
            $erros[] = 'Selecione um representante.';
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

            redirecionar('/admin/turma.php?id=' . $turmaId . '&aba=representantes' . ($novas ? '&ok=vinculo' : '&ok=existe'));
        }
    }

    if (in_array($acao, ['adicionar_horario', 'remover_horario'], true)) {
        $resultado = processarHorarioTurma($pdo, $_POST, $turmaId, null, $erros);
        if ($resultado) {
            redirecionar('/admin/turma.php?id=' . $turmaId . '&aba=horarios&ok=' . $resultado);
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
        redirecionar('/admin/turma.php?id=' . $turmaId . '&aba=representantes&ok=removido');
    }

    if ($acao === 'adicionar_disciplina') {
        $nomeDisciplina = mb_substr(trim((string) ($_POST['nome'] ?? '')), 0, 100);
        $nucleo = (string) ($_POST['nucleo'] ?? 'basico');
        if (!in_array($nucleo, ['basico', 'politecnico', 'tecnico'], true)) {
            $nucleo = 'basico';
        }
        if ($nomeDisciplina === '') {
            $erros[] = 'Informe o nome da disciplina.';
        } else {
            $proxima = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM disciplinas_turma WHERE turma_id = :id');
            $proxima->execute(['id' => $turmaId]);
            $inserir = $pdo->prepare(
                'INSERT INTO disciplinas_turma (turma_id, nome, nucleo, ordem)
                 VALUES (:turma, :nome, :nucleo, :ordem)'
            );
            try {
                $inserir->execute([
                    'turma'  => $turmaId,
                    'nome'   => $nomeDisciplina,
                    'nucleo' => $nucleo,
                    'ordem'  => (int) $proxima->fetchColumn(),
                ]);
                $pdo->prepare('UPDATE turmas SET grade_aplicada = 1 WHERE id = :id')->execute(['id' => $turmaId]);
                redirecionar('/admin/turma.php?id=' . $turmaId . '&aba=grade&ok=disciplina');
            } catch (PDOException $e) {
                $erros[] = 'Essa disciplina já está na grade.';
            }
        }
    }

    if ($acao === 'renomear_disciplina') {
        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        $novoNome = mb_substr(trim((string) ($_POST['nome'] ?? '')), 0, 100);
        $atual = $pdo->prepare('SELECT nome FROM disciplinas_turma WHERE id = :id AND turma_id = :turma LIMIT 1');
        $atual->execute(['id' => $disciplinaId, 'turma' => $turmaId]);
        $nomeAtual = (string) $atual->fetchColumn();
        if ($nomeAtual === '' || $novoNome === '') {
            $erros[] = 'Informe o nome da disciplina.';
        } elseif (strcasecmp($nomeAtual, $novoNome) === 0) {
            redirecionar('/admin/turma.php?id=' . $turmaId . '&aba=grade&ok=disciplina');
        } else {
            try {
                renomearDisciplinaNaTurma($pdo, $turmaId, $nomeAtual, $novoNome);
                redirecionar('/admin/turma.php?id=' . $turmaId . '&aba=grade&ok=disciplina');
            } catch (PDOException $e) {
                $erros[] = 'Já existe uma disciplina com esse nome.';
            }
        }
    }

    if ($acao === 'remover_disciplina') {
        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        $atual = $pdo->prepare('SELECT nome FROM disciplinas_turma WHERE id = :id AND turma_id = :turma LIMIT 1');
        $atual->execute(['id' => $disciplinaId, 'turma' => $turmaId]);
        $nomeAtual = (string) $atual->fetchColumn();
        if ($nomeAtual !== '') {
            $pdo->prepare('DELETE FROM professor_turmas WHERE turma_id = :turma AND materia = :nome')
                ->execute(['turma' => $turmaId, 'nome' => $nomeAtual]);
            $pdo->prepare('DELETE FROM horarios_aula WHERE turma_id = :turma AND materia = :nome')
                ->execute(['turma' => $turmaId, 'nome' => $nomeAtual]);
            $pdo->prepare('DELETE FROM disciplinas_turma WHERE id = :id AND turma_id = :turma')
                ->execute(['id' => $disciplinaId, 'turma' => $turmaId]);
        }
        redirecionar('/admin/turma.php?id=' . $turmaId . '&aba=grade&ok=disciplina_removida');
    }

    if ($acao === 'restaurar_grade') {
        $curso = (string) ($_POST['curso'] ?? '');
        $serie = (int) ($_POST['serie'] ?? 0);
        $curso = isset(cursosDaGrade()[$curso]) ? $curso : null;
        $serie = in_array($serie, [1, 2, 3], true) ? $serie : null;
        if (aplicarGradePadrao($pdo, $turmaId, true, $curso, $serie)) {
            redirecionar('/admin/turma.php?id=' . $turmaId . '&aba=grade&ok=grade');
        }
        $erros[] = 'Escolha o curso e a série para aplicar a grade do IFAM.';
    }
}

$alunos = listarAlunosDaTurma($pdo, $turmaId);
$professores = agruparProfessoresDaTurma(listarVinculosDaTurma($pdo, $turmaId));
$todosProfessores = listarProfessores($pdo);
$horariosPainel = listarHorariosDaTurma($pdo, $turmaId);
$opcoesHorario = opcoesHorarioDaTurma($professores);
$actionHorario = BASE_URL . '/admin/turma.php?id=' . $turmaId;
$errosHorario = [];
$grade = listarGradeDaTurma($pdo, $turmaId);
$cursoDetectado = cursoDaTurma($turma['nome']);
$serieDetectada = serieDaTurma($turma['nome']);
$gradePorNucleo = ['basico' => [], 'politecnico' => [], 'tecnico' => []];
foreach ($grade as $disciplina) {
    $nucleo = isset($gradePorNucleo[$disciplina['nucleo']]) ? $disciplina['nucleo'] : 'basico';
    $gradePorNucleo[$nucleo][] = $disciplina;
}

$abasValidas = ['grade', 'representantes', 'horarios', 'alunos'];
$abaPorOk = [
    'vinculo'            => 'representantes',
    'existe'             => 'representantes',
    'removido'           => 'representantes',
    'horario_ok'         => 'horarios',
    'horario_removido'   => 'horarios',
];
$aba = (string) ($_GET['aba'] ?? '');
if (!in_array($aba, $abasValidas, true)) {
    $aba = $abaPorOk[$_GET['ok'] ?? ''] ?? 'grade';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erros) {
    $aba = match ($_POST['acao'] ?? '') {
        'adicionar', 'remover' => 'representantes',
        'adicionar_horario', 'remover_horario', 'mover_horario', 'definir_horario', 'esvaziar_horario', 'gerar_horario' => 'horarios',
        default => 'grade',
    };
}

$tituloPagina = $turma['nome'];
$menuAtivo = 'turmas';
$acaoTopo = '<a class="btn btn-ghost" href="' . BASE_URL . '/admin/editar_turma.php?id=' . $turmaId . '">Editar</a>';

require __DIR__ . '/../includes/header.php';
?>

<p class="back-link"><a href="<?= BASE_URL ?>/admin/turmas.php">Turmas</a></p>

<?php if (($_GET['ok'] ?? '') === 'vinculo'): ?>
    <div class="alert alert-ok" role="status">Matéria vinculada ao representante.</div>
<?php elseif (($_GET['ok'] ?? '') === 'existe'): ?>
    <div class="alert alert-ok" role="status">Esse vínculo já existia.</div>
<?php elseif (($_GET['ok'] ?? '') === 'removido'): ?>
    <div class="alert alert-ok" role="status">Vínculo removido.</div>
<?php elseif (($_GET['ok'] ?? '') === 'editada'): ?>
    <div class="alert alert-ok" role="status">Turma atualizada.</div>
<?php elseif (($_GET['erro'] ?? '') === 'alunos'): ?>
    <div class="alert alert-error">Mova os alunos para outra turma antes de excluir.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_gerado'): ?>
    <div class="alert alert-ok" role="status">Grade de horários gerada. Arraste as aulas para ajustar.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_quadro'): ?>
    <div class="alert alert-ok" role="status">Grade de horários atualizada.</div>
    <div class="alert alert-ok" role="status">Horário adicionado.</div>
<?php elseif (($_GET['ok'] ?? '') === 'horario_removido'): ?>
    <div class="alert alert-ok" role="status">Horário removido.</div>
<?php elseif (($_GET['ok'] ?? '') === 'disciplina'): ?>
    <div class="alert alert-ok" role="status">Grade atualizada.</div>
<?php elseif (($_GET['ok'] ?? '') === 'disciplina_removida'): ?>
    <div class="alert alert-ok" role="status">Disciplina removida da grade.</div>
<?php elseif (($_GET['ok'] ?? '') === 'grade'): ?>
    <div class="alert alert-ok" role="status">Grade padrão do IFAM aplicada. Ajuste o que for diferente neste campus.</div>
<?php endif; ?>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="hello">
    <h2 class="display-name"><?= e($turma['nome']) ?></h2>
    <p>
        <?= e($turma['ano'] ?: 'Ano não informado') ?>
        · <?= count($alunos) ?> aluno<?= count($alunos) === 1 ? '' : 's' ?>
        · <?= count($professores) ?> representante<?= count($professores) === 1 ? '' : 's' ?>
    </p>
</section>

<nav class="turma-abas" aria-label="Seções da turma">
    <?php
    $abasTurma = [
        'grade'          => ['Grade', count($grade)],
        'representantes' => ['Representantes', count($professores)],
        'horarios'       => ['Horários', count($horariosPainel)],
        'alunos'         => ['Alunos', count($alunos)],
    ];
    foreach ($abasTurma as $codigo => [$rotulo, $total]):
    ?>
        <a class="<?= $aba === $codigo ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>&aba=<?= e($codigo) ?>"<?= $aba === $codigo ? ' aria-current="page"' : '' ?>>
            <?= e($rotulo) ?>
            <span class="turma-aba-n"><?= (int) $total ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($aba === 'grade'): ?>
<section class="panel" id="grade">
    <div class="panel-head">
        <h2>Grade de disciplinas</h2>
    </div>
    <p class="panel-lead">A formação geral é a do ensino médio integrado do IFAM. A formação técnica segue o PPC de Informática, Administração ou Agropecuária. O padrão já vem preenchido; altere só o que for diferente nesta turma.</p>

    <div class="grade-nucleos">
        <?php foreach (['basico' => 'FG', 'politecnico' => 'NP', 'tecnico' => 'FT'] as $nucleo => $marca): ?>
            <?php if (!$gradePorNucleo[$nucleo]) { continue; } ?>
            <?php $quantidade = count($gradePorNucleo[$nucleo]); ?>
            <article class="grade-nucleo grade-nucleo-<?= e($nucleo) ?>">
                <header class="grade-nucleo-topo">
                    <span class="grade-nucleo-marca" aria-hidden="true"><?= e($marca) ?></span>
                    <div>
                        <h3><?= e(rotuloNucleo($nucleo)) ?></h3>
                        <p><?= $quantidade ?> disciplina<?= $quantidade === 1 ? '' : 's' ?></p>
                    </div>
                </header>
                <ul class="grade-lista">
                    <?php foreach ($gradePorNucleo[$nucleo] as $disciplina): ?>
                        <li class="grade-linha">
                            <form method="post" action="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>">
                                <input type="hidden" name="acao" value="renomear_disciplina">
                                <input type="hidden" name="disciplina_id" value="<?= (int) $disciplina['id'] ?>">
                                <input name="nome" type="text" maxlength="100" value="<?= e($disciplina['nome']) ?>" title="<?= e($disciplina['nome']) ?>" required aria-label="Nome da disciplina">
                                <button class="grade-ico" type="submit" aria-label="Salvar <?= e($disciplina['nome']) ?>" title="Salvar">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.6 9.2 16.8 19 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </form>
                            <form method="post" action="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>" data-confirm="Remover esta disciplina da grade?">
                                <input type="hidden" name="acao" value="remover_disciplina">
                                <input type="hidden" name="disciplina_id" value="<?= (int) $disciplina['id'] ?>">
                                <button class="grade-ico grade-ico-remover" type="submit" aria-label="Remover <?= e($disciplina['nome']) ?>" title="Remover">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7l10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                                </button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!$grade): ?>
        <p class="empty">Nenhuma disciplina nesta turma.</p>
    <?php endif; ?>

    <form class="stack-form" method="post" action="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>">
        <input type="hidden" name="acao" value="adicionar_disciplina">
        <h3 class="section-title">Incluir disciplina</h3>
        <div class="form-grid">
            <div class="field">
                <label for="nova-disciplina">Nome</label>
                <input id="nova-disciplina" name="nome" type="text" maxlength="100" required placeholder="Ex.: Programação Web">
            </div>
            <div class="field">
                <label for="nucleo-disciplina">Núcleo</label>
                <select id="nucleo-disciplina" name="nucleo">
                    <option value="basico">Formação geral</option>
                    <option value="politecnico">Núcleo politécnico</option>
                    <option value="tecnico">Formação técnica</option>
                </select>
            </div>
        </div>
        <button class="btn btn-stamp" type="submit">Incluir</button>
    </form>

    <form class="stack-form" method="post" action="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>" data-confirm="Substituir a grade atual pela padrão do IFAM?">
        <input type="hidden" name="acao" value="restaurar_grade">
        <h3 class="section-title">Grade padrão</h3>
        <?php if (!$cursoDetectado || !$serieDetectada): ?>
            <div class="form-grid">
                <div class="field">
                    <label for="curso-grade">Curso</label>
                    <select id="curso-grade" name="curso" required>
                        <option value="">Selecione</option>
                        <?php foreach (cursosDaGrade() as $codigo => $rotulo): ?>
                            <option value="<?= e($codigo) ?>"><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="serie-grade">Série</label>
                    <select id="serie-grade" name="serie" required>
                        <option value="">Selecione</option>
                        <option value="1">1º ano</option>
                        <option value="2">2º ano</option>
                        <option value="3">3º ano</option>
                    </select>
                </div>
            </div>
        <?php else: ?>
            <p class="panel-lead"><?= (int) $serieDetectada ?>º ano de <?= e(cursosDaGrade()[$cursoDetectado]) ?>.</p>
        <?php endif; ?>
        <button class="btn btn-ghost" type="submit">Aplicar grade do IFAM</button>
    </form>
</section>
<?php endif; ?>

<?php if ($aba === 'representantes'): ?>
<section class="panel">
    <div class="panel-head">
        <h2>Representantes</h2>
    </div>
    <p class="panel-lead">O mesmo representante pode cuidar de mais de uma matéria nesta turma.</p>

    <?php if (!$professores): ?>
        <p class="empty">Nenhum representante vinculado ainda.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Representante</th>
                        <th>E-mail</th>
                        <th>Matérias</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($professores as $professor): ?>
                        <tr>
                            <td data-label="Representante"><?= e($professor['nome']) ?></td>
                            <td data-label="E-mail"><?= e($professor['email']) ?></td>
                            <td data-label="Matérias">
                                <div class="materia-list">
                                    <?php foreach ($professor['materias'] as $item): ?>
                                        <span class="materia-item">
                                            <span class="chip chip-professor"><?= e(rotuloMateria($item['materia'])) ?></span>
                                            <form method="post" action="<?= BASE_URL ?>/admin/turma.php?id=<?= $turmaId ?>" data-confirm="Remover esta matéria deste representante?">
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
                    <label for="professor_id">Representante</label>
                    <select id="professor_id" name="professor_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($todosProfessores as $professor): ?>
                            <option value="<?= (int) $professor['id'] ?>"><?= e($professor['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="materia">Matéria</label>
                    <?php if ($grade): ?>
                        <select id="materia" name="materia" required>
                            <option value="">Selecione</option>
                            <?php foreach ($grade as $disciplina): ?>
                                <option value="<?= e($disciplina['nome']) ?>"><?= e($disciplina['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input id="materia" name="materia" type="text" maxlength="100" required placeholder="Ex.: Matemática">
                        <small class="field-help">Para várias de uma vez, separe por vírgula.</small>
                    <?php endif; ?>
                </div>
            </div>
            <button class="btn btn-stamp" type="submit">Adicionar</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($aba === 'horarios'): ?>
<section class="panel">
    <div class="panel-head">
        <h2>Horários</h2>
    </div>
    <p class="panel-lead">Segunda a sexta, no turno da manhã. A grade nasce das disciplinas da turma. Arraste uma aula para trocar de lugar, ou solte uma disciplina em um horário.</p>
    <?php
    $podeEditarQuadro = true;
    $actionQuadro = BASE_URL . '/admin/turma.php?id=' . $turmaId . '&aba=horarios';
    require __DIR__ . '/../includes/quadro_grade.php';
    ?>
</section>
<?php endif; ?>

<?php if ($aba === 'alunos'): ?>
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

<section class="panel turma-perigo">
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
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
