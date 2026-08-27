<?php
$anexos = $anexos ?? [];
$botaoTexto = $botaoTexto ?? 'Salvar atividade';
$materiasPorTurma = $materiasPorTurma ?? [];
if (!$materiasPorTurma && !empty($turmas)) {
    foreach ($turmas as $turma) {
        $lista = array_values(array_filter(array_map('trim', explode(',', (string) ($turma['materias'] ?? '')))));
        $materiasPorTurma[(int) $turma['id']] = $lista;
    }
}
?>
<form class="event-form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?php if (!empty($eventoId)): ?>
        <input type="hidden" name="id" value="<?= (int) $eventoId ?>">
    <?php endif; ?>

    <div class="form-grid">
        <div class="field span-2">
            <label for="titulo">Título</label>
            <input id="titulo" name="titulo" type="text" maxlength="150" value="<?= e($dados['titulo']) ?>" required placeholder="Ex.: Prova de Banco de Dados">
        </div>

        <div class="field span-2">
            <label for="editor-descricao">Descrição</label>
            <input type="hidden" name="descricao" id="descricao" value="<?= e($dados['descricao']) ?>">
            <div id="editor-descricao" class="rich-editor"><?= descricaoHtml($dados['descricao']) ?></div>
            <small class="field-help">Use título, negrito, itálico e listas. Imagens também podem ir na descrição ou nos anexos.</small>
        </div>

        <div class="field">
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo" required>
                <option value="">Selecione</option>
                <option value="prova" <?= $dados['tipo'] === 'prova' ? 'selected' : '' ?>>Prova</option>
                <option value="trabalho" <?= $dados['tipo'] === 'trabalho' ? 'selected' : '' ?>>Trabalho</option>
                <option value="tarefa" <?= $dados['tipo'] === 'tarefa' ? 'selected' : '' ?>>Tarefa</option>
                <option value="outro" <?= $dados['tipo'] === 'outro' ? 'selected' : '' ?>>Outro</option>
            </select>
        </div>

        <div class="field">
            <label for="turma_id">Turma</label>
            <select id="turma_id" name="turma_id" required data-materias-turmas="<?= e(json_encode($materiasPorTurma ?? new stdClass(), JSON_UNESCAPED_UNICODE)) ?>">
                <option value="">Selecione</option>
                <?php foreach ($turmas as $turma): ?>
                    <option value="<?= (int) $turma['id'] ?>" <?= (string) $dados['turma_id'] === (string) $turma['id'] ? 'selected' : '' ?>>
                        <?= e($turma['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="materia">Disciplina</label>
            <select id="materia" name="materia" data-materia-atual="<?= e($dados['materia'] ?? '') ?>">
                <option value="">Geral / sem disciplina</option>
            </select>
            <small class="field-help">Ajuda o aluno a ver a atividade na disciplina certa.</small>
        </div>

        <div class="field">
            <label for="data_evento">Data</label>
            <input id="data_evento" name="data_evento" type="date" value="<?= e($dados['data_evento']) ?>" required>
        </div>

        <div class="field">
            <label for="hora">Horário</label>
            <input id="hora" name="hora" type="time" value="<?= e($dados['hora']) ?>">
        </div>

        <div class="field span-2">
            <label for="anexos">Imagens e anexos</label>
            <input id="anexos" name="anexos[]" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
            <small class="field-help">PDF, imagens, Word, Excel, PowerPoint ou ZIP. Até 5 MB cada.</small>
        </div>

        <?php if ($anexos): ?>
            <div class="field span-2">
                <span class="field-legend">Arquivos já enviados</span>
                <ul class="anexo-edit-list">
                    <?php foreach ($anexos as $anexo): ?>
                        <li>
                            <label>
                                <input type="checkbox" name="remover_anexos[]" value="<?= (int) $anexo['id'] ?>">
                                Remover
                            </label>
                            <a href="<?= e(UPLOAD_URL . '/' . $anexo['caminho']) ?>" target="_blank" rel="noopener"><?= e($anexo['nome_original']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button class="btn btn-stamp" type="submit"><?= e($botaoTexto) ?></button>
        <a class="btn btn-ghost" href="<?= BASE_URL ?>/professor/eventos.php">Cancelar</a>
    </div>
</form>
