<?php
$horariosPainel = $horariosPainel ?? [];
$opcoesHorario = $opcoesHorario ?? [];
$actionHorario = $actionHorario ?? '';
$horarioEmbutido = $horarioEmbutido ?? false;
?>

<?php if (!$horarioEmbutido): ?>
<section class="panel">
    <h2>Horários de aula</h2>
    <p class="panel-lead">A grade semanal que os alunos veem em Horários e em cada disciplina.</p>
<?php endif; ?>

    <?php foreach ($errosHorario as $erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <?php if (!$horariosPainel): ?>
        <p class="empty">Nenhum horário cadastrado nesta turma.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Horário</th>
                        <th>Disciplina</th>
                        <th>Quem ministra</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($horariosPainel as $aula): ?>
                        <tr>
                            <td data-label="Dia"><?= e(rotuloDiaSemana((int) $aula['dia_semana'])) ?></td>
                            <td data-label="Horário">
                                <?= formatarHora($aula['hora_inicio']) ?>
                                <?= $aula['hora_fim'] ? ' – ' . formatarHora($aula['hora_fim']) : '' ?>
                            </td>
                            <td data-label="Disciplina"><?= e(rotuloMateria($aula['materia'])) ?></td>
                            <td data-label="Quem ministra"><?= e($aula['professor_nome']) ?></td>
                            <td data-label="Ações">
                                <form method="post" action="<?= e($actionHorario) ?>" data-confirm="Remover este horário?">
                                    <input type="hidden" name="acao" value="remover_horario">
                                    <input type="hidden" name="turma_id" value="<?= (int) $aula['turma_id'] ?>">
                                    <input type="hidden" name="horario_id" value="<?= (int) $aula['id'] ?>">
                                    <button class="btn btn-danger" type="submit">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($opcoesHorario): ?>
        <form class="stack-form" method="post" action="<?= e($actionHorario) ?>">
            <input type="hidden" name="acao" value="adicionar_horario">
            <input type="hidden" name="turma_id" value="<?= (int) ($turmaId ?? 0) ?>">
            <h3 class="section-title">Adicionar aula</h3>
            <div class="form-grid">
                <div class="field">
                    <label for="vinculo_horario">Disciplina</label>
                    <select id="vinculo_horario" name="vinculo" required>
                        <option value="">Selecione</option>
                        <?php foreach ($opcoesHorario as $opcao): ?>
                            <option value="<?= (int) $opcao['professor_id'] ?>|<?= e($opcao['materia']) ?>">
                                <?= e($opcao['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="dia_semana">Dia</label>
                    <select id="dia_semana" name="dia_semana" required>
                        <option value="">Selecione</option>
                        <?php foreach (diasDaSemana() as $numero => $nome): ?>
                            <option value="<?= $numero ?>"><?= e($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="hora_inicio">Início</label>
                    <input id="hora_inicio" name="hora_inicio" type="time" required>
                </div>
                <div class="field">
                    <label for="hora_fim">Término</label>
                    <input id="hora_fim" name="hora_fim" type="time">
                </div>
            </div>
            <button class="btn btn-stamp" type="submit">Adicionar horário</button>
        </form>
    <?php endif; ?>
<?php if (!$horarioEmbutido): ?>
</section>
<?php endif; ?>
