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

$turmaId = (int) $aluno['turma_id'];
$disciplinas = listarDisciplinasDaTurma($pdo, $turmaId);
$horarios = listarHorariosDaTurma($pdo, $turmaId);
$hoje = (int) (new DateTime('today'))->format('N');

$tituloPagina = 'Disciplinas';
$menuAtivo = 'disciplinas';

require __DIR__ . '/../includes/header.php';
?>

<section class="hello">
    <h2 class="display-name">Disciplinas</h2>
    <p>Atividades, professores e horários de cada matéria da <?= e($aluno['turma_nome']) ?>.</p>
</section>

<?php if (!$disciplinas): ?>
    <section class="panel">
        <div class="empty-state">
            <p>Nenhuma disciplina vinculada a esta turma ainda.</p>
        </div>
    </section>
<?php else: ?>
    <div class="discipline-grid">
        <?php foreach ($disciplinas as $disciplina): ?>
            <?php
            $eventos = listarEventosDaDisciplina($pdo, $turmaId, (int) $aluno['id'], $disciplina['materia']);
            $pendentes = 0;
            foreach ($eventos as $evento) {
                if (!$evento['concluido']) {
                    $pendentes++;
                }
            }
            $pessoais = listarAtividadesPessoais($pdo, (int) $aluno['id'], $disciplina['materia']);
            $aulasSemana = 0;
            $proximaAula = null;
            foreach ($horarios as $aula) {
                if (strcasecmp((string) $aula['materia'], (string) $disciplina['materia']) !== 0) {
                    continue;
                }
                $aulasSemana++;
                if ((int) $aula['dia_semana'] === $hoje && $proximaAula === null) {
                    $proximaAula = $aula;
                }
            }
            $nomes = array_unique(array_column($disciplina['professores'], 'nome'));
            ?>
            <a class="discipline-card" href="<?= BASE_URL ?>/aluno/disciplina.php?materia=<?= urlencode($disciplina['materia']) ?>">
                <h2><?= e(rotuloMateria($disciplina['materia'])) ?></h2>
                <p class="agenda-meta"><?= e(implode(', ', $nomes) ?: 'Quem ministra não informado') ?></p>
                <div class="discipline-meta">
                    <span><?= $pendentes ?> pendente<?= $pendentes === 1 ? '' : 's' ?> da turma</span>
                    <span><?= count($pessoais) ?> pessoal<?= count($pessoais) === 1 ? '' : 'is' ?></span>
                    <span><?= $aulasSemana ?> aula<?= $aulasSemana === 1 ? '' : 's' ?> na semana</span>
                </div>
                <?php if ($proximaAula): ?>
                    <p class="agenda-meta">Hoje · <?= formatarHora($proximaAula['hora_inicio']) ?></p>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
