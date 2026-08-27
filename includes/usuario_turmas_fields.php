<?php
$turmas = $turmas ?? [];
$dados = $dados ?? ['turma_id' => '', 'turma_ids' => [], 'turma_materias' => []];
$tipoAtual = $dados['tipo'] ?? 'aluno';
$turmaIds = array_map('intval', $dados['turma_ids'] ?? []);
$turmaMaterias = $dados['turma_materias'] ?? [];
?>

<div class="field span-2 js-tipo-aluno" <?= $tipoAtual !== 'aluno' ? 'hidden' : '' ?>>
    <label for="turma_id">Turma</label>
    <small class="field-help">O aluno vê apenas as atividades desta turma.</small>
    <?php if (!$turmas): ?>
        <p class="empty">Nenhuma turma cadastrada. <a href="<?= BASE_URL ?>/admin/turmas.php">Cadastrar turmas</a></p>
    <?php else: ?>
        <select id="turma_id" name="turma_id" <?= $tipoAtual === 'aluno' ? 'required' : 'disabled' ?>>
            <option value="">Selecione a turma</option>
            <?php foreach ($turmas as $turma): ?>
                <option value="<?= (int) $turma['id'] ?>" <?= (string) $dados['turma_id'] === (string) $turma['id'] ? 'selected' : '' ?>>
                    <?= e($turma['nome']) ?><?= !empty($turma['ano']) ? ' · ' . e($turma['ano']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
</div>

<div class="field span-2 js-tipo-professor" <?= $tipoAtual !== 'professor' ? 'hidden' : '' ?>>
    <div class="turma-picker-head">
        <span class="field-legend" id="turmas-professor-label">Turmas e matérias</span>
        <?php if ($turmas): ?>
            <div class="turma-picker-actions">
                <button type="button" class="link-modal" data-turmas-all>Selecionar todas</button>
                <button type="button" class="link-modal" data-turmas-none>Limpar</button>
            </div>
        <?php endif; ?>
    </div>
    <small class="field-help">Marque as turmas e informe as matérias. Um professor pode dar várias matérias na mesma turma.</small>
    <?php if (!$turmas): ?>
        <p class="empty">Nenhuma turma cadastrada. <a href="<?= BASE_URL ?>/admin/turmas.php">Cadastrar turmas</a></p>
    <?php else: ?>
        <div class="turma-picker" role="group" aria-labelledby="turmas-professor-label">
            <?php foreach ($turmas as $turma): ?>
                <?php
                $turmaId = (int) $turma['id'];
                $marcada = in_array($turmaId, $turmaIds, true);
                $materias = $turmaMaterias[$turmaId] ?? '';
                ?>
                <label class="turma-pick-item">
                    <input
                        class="js-turma-check"
                        type="checkbox"
                        name="turma_ids[]"
                        value="<?= $turmaId ?>"
                        <?= $marcada ? 'checked' : '' ?>
                        <?= $tipoAtual !== 'professor' ? 'disabled' : '' ?>
                    >
                    <span>
                        <?= e($turma['nome']) ?>
                        <?php if (!empty($turma['ano'])): ?>
                            <small><?= e($turma['ano']) ?></small>
                        <?php endif; ?>
                        <input
                            class="js-turma-materias"
                            type="text"
                            name="turma_materias[<?= $turmaId ?>]"
                            value="<?= e((string) $materias) ?>"
                            maxlength="150"
                            placeholder="Matérias, separadas por vírgula"
                            <?= $tipoAtual !== 'professor' || !$marcada ? 'disabled' : '' ?>
                        >
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
