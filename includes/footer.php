            </main>
        </div>
    </div>

    <dialog class="app-dialog" id="modal-detalhe" aria-labelledby="modal-titulo">
        <div class="modal-card" id="modal-conteudo"></div>
    </dialog>

    <dialog class="app-dialog" id="modal-confirm" aria-labelledby="confirm-titulo">
        <form method="dialog" class="modal-card">
            <h2 id="confirm-titulo">Confirmar</h2>
            <p id="confirm-texto">Esta ação não pode ser desfeita.</p>
            <div class="modal-actions">
                <button class="btn btn-ghost" value="cancel">Cancelar</button>
                <button class="btn btn-danger" value="ok">Excluir</button>
            </div>
        </form>
    </dialog>

    <?php if (($tipoUsuario ?? '') === 'aluno'): ?>
        <?php
        $alunoModal = obterAlunoPorUsuario(getPDO(), (int) ($usuario['id'] ?? 0));
        $disciplinasModal = $alunoModal ? listarDisciplinasDaTurma(getPDO(), (int) $alunoModal['turma_id']) : [];
        ?>
        <dialog class="app-dialog" id="modal-pessoal" aria-labelledby="pessoal-titulo">
            <div class="modal-card">
                <form method="post" action="<?= BASE_URL ?>/aluno/salvar_pessoal.php" id="form-pessoal" enctype="multipart/form-data">
                    <button class="modal-close" type="button" data-modal-close>Fechar</button>
                    <h2 id="pessoal-titulo">Nova atividade pessoal</h2>
                    <p class="panel-lead">Para revisão, provas extras ou tarefas que só você precisa lembrar.</p>
                    <input type="hidden" name="id" id="pessoal-id" value="">
                    <input type="hidden" name="voltar" value="<?= e($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/aluno/atividades.php')) ?>">
                    <div class="field">
                        <label for="pessoal-titulo-campo">Título</label>
                        <input id="pessoal-titulo-campo" name="titulo" type="text" maxlength="150" required>
                    </div>
                    <div class="form-grid">
                        <div class="field">
                            <label for="pessoal-data">Data</label>
                            <input id="pessoal-data" name="data_evento" type="date" required>
                        </div>
                        <div class="field">
                            <label for="pessoal-hora">Horário</label>
                            <input id="pessoal-hora" name="hora" type="time">
                        </div>
                        <div class="field span-2">
                            <label for="pessoal-materia">Disciplina (opcional)</label>
                            <select id="pessoal-materia" name="materia">
                                <option value="">Sem disciplina</option>
                                <?php foreach ($disciplinasModal as $disciplina): ?>
                                    <option value="<?= e($disciplina['materia']) ?>"><?= e(rotuloMateria($disciplina['materia'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field span-2">
                            <label for="pessoal-descricao">Observação</label>
                            <textarea id="pessoal-descricao" name="descricao" rows="3" maxlength="1000"></textarea>
                        </div>
                        <div class="field span-2">
                            <label for="pessoal-anexos">Imagens, PDF, Word e slides</label>
                            <input id="pessoal-anexos" name="anexos[]" type="file" multiple accept="<?= aceitaAnexos() ?>">
                            <small class="field-help">Até 5 MB cada.</small>
                            <div id="pessoal-arquivos" class="anexo-lista" hidden></div>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="btn btn-stamp" type="submit">Salvar</button>
                    </div>
                </form>
                <div class="modal-actions" id="pessoal-extra" hidden></div>
            </div>
        </dialog>
    <?php endif; ?>

    <script>window.APP_BASE = <?= json_encode(BASE_URL) ?>;</script>
    <?php if (!empty($usarEditor)): ?>
        <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
    <?php endif; ?>
    <script src="<?= asset('assets/js/script.js') ?>"></script>
    <script src="<?= asset('assets/js/viva.js') ?>"></script>
    <script src="<?= asset('assets/js/tema.js') ?>"></script>
    <?php
    if (in_array(($tipoUsuario ?? ''), ['aluno', 'professor'], true) && !empty($usuario['id'])) {
        try {
            require_once __DIR__ . '/push.php';
            $chavePushRodape = garantirChavesVapid()['public'];
        } catch (Throwable $e) {
            $chavePushRodape = '';
        }
        if ($chavePushRodape !== '') {
            $alunoPushId = isset($alunoModal['id']) ? (int) $alunoModal['id'] : 0;
            echo '<script>window.APP_PUSH = ' . json_encode([
                'vapid' => $chavePushRodape,
                'usuarioId' => (int) $usuario['id'],
                'alunoId' => $alunoPushId,
            ], JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
            echo '    <script src="' . asset('assets/js/push.js') . '"></script>' . "\n";
        }
    }
    ?>
</body>
</html>
