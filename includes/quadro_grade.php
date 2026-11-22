<?php

$gradeQuadro = $gradeQuadro ?? ($grade ?? []);
$horariosQuadro = $horariosQuadro ?? ($horariosPainel ?? []);
$actionQuadro = $actionQuadro ?? ($actionHorario ?? '');
$podeEditarQuadro = $podeEditarQuadro ?? false;

$nucleoPorNome = [];
foreach ($gradeQuadro as $disciplina) {
    $nucleoPorNome[mb_strtolower(trim((string) $disciplina['nome']))] = (string) ($disciplina['nucleo'] ?? '');
}

$ocupados = [];
$foraDoQuadro = [];
foreach ($horariosQuadro as $aula) {
    $dia = (int) $aula['dia_semana'];
    $inicio = substr((string) $aula['hora_inicio'], 0, 5);
    $chave = $dia . '|' . $inicio;
    if (slotDoQuadro($dia, $inicio) && !isset($ocupados[$chave])) {
        $ocupados[$chave] = $aula;
        continue;
    }
    $foraDoQuadro[] = $aula;
}
?>

<?php if (!$gradeQuadro): ?>
    <p class="empty">Cadastre a grade de disciplinas para montar os horários desta turma.</p>
<?php else: ?>
    <div class="quadro" data-quadro data-action="<?= e($actionQuadro) ?>" data-editar="<?= $podeEditarQuadro ? '1' : '0' ?>">
        <div class="quadro-rolagem">
            <table class="quadro-tabela">
                <thead>
                    <tr>
                        <th scope="col">Tempo</th>
                        <?php foreach (diasDoQuadro() as $nomeDia): ?>
                            <th scope="col"><?= e($nomeDia) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (periodosDoDia() as $periodo): ?>
                        <?php if ((int) $periodo['ordem'] === 4): ?>
                            <tr class="quadro-intervalo">
                                <th scope="row">Intervalo</th>
                                <td colspan="5">10:00 – 10:20</td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th scope="row">
                                <span><?= (int) $periodo['ordem'] ?>º</span>
                                <small><?= e($periodo['inicio']) ?></small>
                            </th>
                            <?php foreach (array_keys(diasDoQuadro()) as $dia): ?>
                                <?php
                                $chave = $dia . '|' . $periodo['inicio'];
                                $aula = $ocupados[$chave] ?? null;
                                $nucleo = $aula ? ($nucleoPorNome[mb_strtolower(trim((string) $aula['materia']))] ?? '') : '';
                                ?>
                                <td>
                                    <div class="quadro-celula<?= $aula ? '' : ' is-vazia' ?>" data-dia="<?= $dia ?>" data-inicio="<?= e($periodo['inicio']) ?>">
                                        <?php if ($aula): ?>
                                            <article
                                                class="quadro-aula"
                                                data-nucleo="<?= e($nucleo) ?>"
                                                <?= $podeEditarQuadro ? 'data-id="' . (int) $aula['id'] . '"' : '' ?>
                                            >
                                                <?php if ($podeEditarQuadro): ?>
                                                    <strong><?= e($aula['materia']) ?></strong>
                                                <?php else: ?>
                                                    <a href="<?= BASE_URL ?>/aluno/disciplina.php?materia=<?= urlencode((string) $aula['materia']) ?>"><?= e($aula['materia']) ?></a>
                                                <?php endif; ?>
                                                <?php if ($aula['professor_nome'] !== ''): ?>
                                                    <span><?= e($aula['professor_nome']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($podeEditarQuadro): ?>
                                                    <button class="quadro-x" type="button" data-esvaziar aria-label="Esvaziar <?= e($aula['materia']) ?>">×</button>
                                                <?php endif; ?>
                                            </article>
                                        <?php elseif ($podeEditarQuadro): ?>
                                            <span class="quadro-vazio">Solte aqui</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php if ($podeEditarQuadro): ?>
        <div class="quadro-paleta" aria-label="Disciplinas da turma">
            <p>Arraste uma disciplina para um horário, ou arraste uma aula para trocar de lugar.</p>
            <div class="quadro-chips">
                <?php foreach ($gradeQuadro as $disciplina): ?>
                    <button class="quadro-chip" type="button" data-materia="<?= e($disciplina['nome']) ?>" data-nucleo="<?= e($disciplina['nucleo']) ?>">
                        <?= e($disciplina['nome']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <details class="quadro-manual">
            <summary>Ajustar um horário sem arrastar</summary>
            <form class="stack-form" method="post" action="<?= e($actionQuadro) ?>">
                <input type="hidden" name="acao" value="definir_horario">
                <div class="form-grid">
                    <div class="field">
                        <label for="quadro-dia">Dia</label>
                        <select id="quadro-dia" name="dia_semana" required>
                            <?php foreach (diasDoQuadro() as $numero => $nome): ?>
                                <option value="<?= $numero ?>"><?= e($nome) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="quadro-tempo">Tempo</label>
                        <select id="quadro-tempo" name="hora_inicio" required>
                            <?php foreach (periodosDoDia() as $periodo): ?>
                                <option value="<?= e($periodo['inicio']) ?>"><?= (int) $periodo['ordem'] ?>º · <?= e($periodo['inicio']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field span-2">
                        <label for="quadro-materia">Disciplina</label>
                        <select id="quadro-materia" name="materia">
                            <option value="">Esvaziar este horário</option>
                            <?php foreach ($gradeQuadro as $disciplina): ?>
                                <option value="<?= e($disciplina['nome']) ?>"><?= e($disciplina['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button class="btn btn-stamp" type="submit">Aplicar</button>
            </form>
        </details>

        <form method="post" action="<?= e($actionQuadro) ?>" data-confirm="Substituir a grade de horários pela distribuição automática?">
            <input type="hidden" name="acao" value="gerar_horario">
            <button class="btn btn-ghost" type="submit">Gerar grade automática de novo</button>
        </form>
    <?php endif; ?>
    </div>
    <?php if ($podeEditarQuadro): ?>
        <script src="<?= asset('assets/js/quadro.js') ?>"></script>
    <?php endif; ?>
<?php endif; ?>

<?php if ($foraDoQuadro): ?>
    <h3 class="section-title">Horários fora da grade</h3>
    <ul class="quadro-fora">
        <?php foreach ($foraDoQuadro as $aula): ?>
            <li>
                <span><?= e(rotuloDiaSemana((int) $aula['dia_semana'])) ?> · <?= e(formatarHora($aula['hora_inicio'])) ?> · <?= e($aula['materia']) ?></span>
                <?php if ($podeEditarQuadro): ?>
                    <form method="post" action="<?= e($actionQuadro) ?>" data-confirm="Remover este horário?">
                        <input type="hidden" name="acao" value="remover_horario">
                        <input type="hidden" name="horario_id" value="<?= (int) $aula['id'] ?>">
                        <button class="btn btn-ghost btn-compact" type="submit">Remover</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
