document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('[data-nav-toggle]');
    const backdrop = document.querySelector('[data-nav-close]');

    const fecharMenu = () => {
        sidebar?.classList.remove('is-open');
        if (backdrop) {
            backdrop.hidden = true;
        }
        toggle?.setAttribute('aria-expanded', 'false');
    };

    toggle?.addEventListener('click', () => {
        const aberto = sidebar.classList.toggle('is-open');
        if (backdrop) {
            backdrop.hidden = !aberto;
        }
        toggle.setAttribute('aria-expanded', String(aberto));
    });

    backdrop?.addEventListener('click', fecharMenu);

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            fecharMenu();
        }
    });

    const confirmDialog = document.getElementById('modal-confirm');
    const confirmTexto = document.getElementById('confirm-texto');
    let formPendente = null;

    document.addEventListener('submit', (evento) => {
        const elemento = evento.target.closest('[data-confirm]');
        if (!elemento || evento.target !== elemento) {
            return;
        }

        if (elemento.dataset.confirmed === '1') {
            delete elemento.dataset.confirmed;
            return;
        }

        evento.preventDefault();
        formPendente = elemento;

        if (confirmTexto) {
            confirmTexto.textContent = elemento.getAttribute('data-confirm') || 'Confirmar esta ação?';
        }

        if (confirmDialog?.showModal) {
            confirmDialog.returnValue = '';
            confirmDialog.showModal();
            return;
        }

        if (window.confirm(elemento.getAttribute('data-confirm') || 'Confirmar esta ação?')) {
            elemento.dataset.confirmed = '1';
            elemento.requestSubmit();
        }
    });

    confirmDialog?.addEventListener('click', (evento) => {
        if (evento.target === confirmDialog) {
            confirmDialog.close('cancel');
        }
    });

    confirmDialog?.addEventListener('close', () => {
        if (confirmDialog.returnValue === 'ok' && formPendente) {
            formPendente.dataset.confirmed = '1';
            formPendente.requestSubmit();
        }
        formPendente = null;
    });

    document.querySelectorAll('[data-toggle-password]').forEach((botao) => {
        botao.addEventListener('click', () => {
            const campo = document.getElementById(botao.getAttribute('data-toggle-password'));
            if (!campo) {
                return;
            }

            const mostrar = campo.type === 'password';
            campo.type = mostrar ? 'text' : 'password';
            botao.textContent = mostrar ? 'Ocultar' : 'Mostrar';
        });
    });

    document.querySelectorAll('[data-fill-login]').forEach((botao) => {
        botao.addEventListener('click', () => {
            const email = document.getElementById('email');
            const senha = document.getElementById('senha');

            if (email) {
                email.value = botao.getAttribute('data-fill-login') || '';
            }

            if (senha) {
                senha.value = botao.getAttribute('data-fill-senha') || '';
                senha.focus();
            }
        });
    });

    const atualizarCamposTipo = (formulario) => {
        const tipo = formulario.querySelector('[data-tipo-select]')?.value;
        const blocoAluno = formulario.querySelector('.js-tipo-aluno');
        const blocoProfessor = formulario.querySelector('.js-tipo-professor');
        const selectAluno = formulario.querySelector('#turma_id');

        const ehAluno = tipo === 'aluno';
        const ehProfessor = tipo === 'professor';

        if (blocoAluno) {
            blocoAluno.hidden = !ehAluno;
        }

        if (blocoProfessor) {
            blocoProfessor.hidden = !ehProfessor;
        }

        if (selectAluno) {
            selectAluno.disabled = !ehAluno;
            selectAluno.required = ehAluno;
        }

        formulario.querySelectorAll('.js-turma-check').forEach((caixa) => {
            caixa.disabled = !ehProfessor;
        });

        formulario.querySelectorAll('.js-turma-materias').forEach((campo) => {
            const caixa = campo.closest('label')?.querySelector('.js-turma-check');
            campo.disabled = !ehProfessor || !caixa?.checked;
        });
    };

    const sincronizarMaterias = (formulario) => {
        formulario.querySelectorAll('.js-turma-materias').forEach((campo) => {
            const caixa = campo.closest('label')?.querySelector('.js-turma-check');
            campo.disabled = !caixa || caixa.disabled || !caixa.checked;
        });
    };

    document.querySelectorAll('[data-tipo-form]').forEach((formulario) => {
        atualizarCamposTipo(formulario);

        formulario.querySelector('[data-tipo-select]')?.addEventListener('change', () => {
            atualizarCamposTipo(formulario);
        });

        formulario.querySelectorAll('.js-turma-check').forEach((caixa) => {
            caixa.addEventListener('change', () => sincronizarMaterias(formulario));
        });

        formulario.querySelector('[data-turmas-all]')?.addEventListener('click', () => {
            formulario.querySelectorAll('.js-turma-check:not(:disabled)').forEach((caixa) => {
                caixa.checked = true;
            });
            sincronizarMaterias(formulario);
        });

        formulario.querySelector('[data-turmas-none]')?.addEventListener('click', () => {
            formulario.querySelectorAll('.js-turma-check').forEach((caixa) => {
                caixa.checked = false;
            });
            sincronizarMaterias(formulario);
        });
    });

    const turmaSelect = document.getElementById('turma_id');
    const materiaSelect = document.getElementById('materia');
    if (turmaSelect && materiaSelect && turmaSelect.hasAttribute('data-materias-turmas')) {
        const mapa = JSON.parse(turmaSelect.getAttribute('data-materias-turmas') || '{}');
        const atualizarMaterias = () => {
            const atual = materiaSelect.getAttribute('data-materia-atual') || materiaSelect.value;
            const lista = mapa[turmaSelect.value] || [];
            materiaSelect.innerHTML = '<option value="">Geral / sem disciplina</option>';
            lista.forEach((nome) => {
                const option = document.createElement('option');
                option.value = nome;
                option.textContent = nome;
                if (nome === atual) {
                    option.selected = true;
                }
                materiaSelect.appendChild(option);
            });
        };
        turmaSelect.addEventListener('change', atualizarMaterias);
        atualizarMaterias();
    }

    const editorEl = document.getElementById('editor-descricao');
    const descricaoInput = document.getElementById('descricao');
    if (editorEl && window.Quill && descricaoInput) {
        const quill = new window.Quill(editorEl, {
            theme: 'snow',
            placeholder: 'Explique o que a turma precisa entregar...',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['link', 'image'],
                        ['clean'],
                    ],
                    handlers: {
                        image() {
                            const input = document.createElement('input');
                            input.type = 'file';
                            input.accept = 'image/*';
                            input.click();
                            input.addEventListener('change', async () => {
                                const arquivo = input.files?.[0];
                                if (!arquivo) {
                                    return;
                                }
                                const dados = new FormData();
                                dados.append('imagem', arquivo);
                                const resposta = await fetch(`${window.APP_BASE}/ajax/upload_imagem.php`, {
                                    method: 'POST',
                                    body: dados,
                                });
                                const json = await resposta.json();
                                if (json.ok) {
                                    const range = quill.getSelection(true);
                                    quill.insertEmbed(range.index, 'image', json.url, 'user');
                                }
                            });
                        },
                    },
                },
            },
        });

        const formulario = editorEl.closest('form');
        formulario?.addEventListener('submit', () => {
            descricaoInput.value = quill.root.innerHTML;
        });
    }

    const dialog = document.getElementById('modal-detalhe');
    const modalBox = document.getElementById('modal-conteudo');

    const fecharModal = () => dialog?.close();

    const htmlEsc = (texto) => String(texto ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');

    const abrirModal = (html) => {
        if (!dialog || !modalBox) {
            return;
        }
        modalBox.innerHTML = html;
        dialog.showModal();
    };

    const montarEvento = (dados) => {
        const anexos = (dados.anexos || []).map((anexo) => (
            anexo.imagem
                ? `<a class="anexo-thumb" href="${htmlEsc(anexo.url)}" target="_blank" rel="noopener"><img src="${htmlEsc(anexo.url)}" alt="${htmlEsc(anexo.nome)}"></a>`
                : `<a class="anexo-file" href="${htmlEsc(anexo.url)}" target="_blank" rel="noopener">${htmlEsc(anexo.nome)}</a>`
        )).join('');

        const acoes = [];
        if (dados.eh_aluno) {
            acoes.push(`<form method="post" action="${window.APP_BASE}/aluno/concluir_evento.php">
                <input type="hidden" name="id" value="${dados.id}">
                <button class="btn ${dados.concluido ? 'btn-ghost' : 'btn-stamp'}" type="submit">${dados.concluido ? 'Desmarcar conclusão' : 'Marcar como concluída'}</button>
            </form>`);
        }
        if (dados.pode_editar) {
            acoes.push(`<a class="btn btn-ghost" href="${window.APP_BASE}/professor/editar_evento.php?id=${dados.id}">Editar</a>`);
        }

        return `
            <button class="modal-close" type="button" data-modal-close>Fechar</button>
            <span class="chip chip-${htmlEsc(dados.tipo)}">${htmlEsc(dados.tipo_rotulo)}</span>
            <h2 id="modal-titulo">${htmlEsc(dados.titulo)}</h2>
            <p class="agenda-meta">${htmlEsc(dados.prazo)} ${dados.hora ? ' · ' + htmlEsc(dados.hora) : ''} · <button class="link-modal" type="button" data-modal="turma" data-id="${dados.turma_id}">${htmlEsc(dados.turma_nome)}</button></p>
            <p class="agenda-meta">Professor: ${htmlEsc(dados.professor)}</p>
            <div class="rich-text">${dados.descricao || ''}</div>
            ${anexos ? `<div class="anexo-list">${anexos}</div>` : ''}
            <div class="modal-actions">${acoes.join('')}</div>
        `;
    };

    const montarTurma = (dados) => {
        const proximas = (dados.proximas || []).map((item) => (
            `<button class="agenda-item" type="button" data-modal="evento" data-id="${item.id}">
                <span class="agenda-mark mark-${htmlEsc(item.tipo)}"></span>
                <div>
                    <div class="agenda-title">${htmlEsc(item.titulo)}</div>
                    <p class="agenda-meta">${htmlEsc(item.prazo)}${item.hora ? ' · ' + htmlEsc(item.hora) : ''}</p>
                </div>
            </button>`
        )).join('');

        return `
            <button class="modal-close" type="button" data-modal-close>Fechar</button>
            <span class="chip chip-outro">Turma</span>
            <h2 id="modal-titulo">${htmlEsc(dados.nome)}</h2>
            <p class="agenda-meta">${htmlEsc(dados.ano || '')} · ${dados.total_alunos} aluno${dados.total_alunos === 1 ? '' : 's'} · ${dados.total_eventos} atividade${dados.total_eventos === 1 ? '' : 's'}</p>
            ${dados.professores?.length ? `<p class="agenda-meta">Professores: ${htmlEsc(dados.professores.join('; '))}</p>` : ''}
            ${proximas ? `<div class="modal-list">${proximas}</div>` : '<p class="empty">Nenhuma atividade nesta turma.</p>'}
        `;
    };

    const montarDia = (titulo, itens) => {
        const lista = itens.map((item) => {
            const origem = item.origem === 'pessoal' ? 'pessoal' : 'evento';
            return `<div class="agenda-item">
                <span class="agenda-mark mark-${htmlEsc(item.tipo === 'pessoal' ? 'outro' : item.tipo)}"></span>
                <div>
                    <button class="link-modal" type="button" data-modal="${origem}" data-id="${item.id}">${htmlEsc(item.titulo)}</button>
                    <p class="agenda-meta">${item.hora ? htmlEsc(item.hora) + ' · ' : ''}${htmlEsc(item.turma_nome || '')}</p>
                </div>
            </div>`;
        }).join('');

        return `
            <button class="modal-close" type="button" data-modal-close>Fechar</button>
            <h2 id="modal-titulo">${htmlEsc(titulo)}</h2>
            <p class="agenda-meta">${itens.length} atividade${itens.length === 1 ? '' : 's'} neste dia</p>
            <div class="modal-list">${lista || '<p class="empty">Nada marcado neste dia.</p>'}</div>
        `;
    };

    const carregarEvento = async (id) => {
        abrirModal('<p class="modal-loading">Carregando atividade…</p>');
        try {
            const resposta = await fetch(`${window.APP_BASE}/ajax/evento.php?id=${id}`);
            const dados = await resposta.json();
            if (dados.ok) {
                abrirModal(montarEvento(dados));
                return;
            }
        } catch (erro) {
            // feedback abaixo
        }
        abrirModal('<p class="empty">Não foi possível abrir esta atividade.</p><button class="btn btn-ghost" type="button" data-modal-close>Fechar</button>');
    };

    const carregarTurma = async (id) => {
        abrirModal('<p class="modal-loading">Carregando turma…</p>');
        try {
            const resposta = await fetch(`${window.APP_BASE}/ajax/turma.php?id=${id}`);
            const dados = await resposta.json();
            if (dados.ok) {
                abrirModal(montarTurma(dados));
                return;
            }
        } catch (erro) {
            // feedback abaixo
        }
        abrirModal('<p class="empty">Não foi possível abrir esta turma.</p><button class="btn btn-ghost" type="button" data-modal-close>Fechar</button>');
    };

    document.addEventListener('click', (evento) => {
        const fechar = evento.target.closest('[data-modal-close]');
        if (fechar) {
            fechar.closest('dialog')?.close();
            return;
        }

        const gatilho = evento.target.closest('[data-modal]');
        if (!gatilho) {
            return;
        }

        const tipo = gatilho.getAttribute('data-modal');
        if (tipo === 'evento') {
            evento.preventDefault();
            evento.stopPropagation();
            carregarEvento(gatilho.getAttribute('data-id'));
            return;
        }

        if (tipo === 'turma') {
            evento.preventDefault();
            evento.stopPropagation();
            carregarTurma(gatilho.getAttribute('data-id'));
            return;
        }

        if (tipo === 'dia') {
            evento.preventDefault();
            const itens = JSON.parse(gatilho.getAttribute('data-itens') || '[]');
            abrirModal(montarDia(gatilho.getAttribute('data-dia') || 'Dia', itens));
            return;
        }

        if (tipo === 'pessoal') {
            evento.preventDefault();
            evento.stopPropagation();
            abrirPessoal(gatilho.getAttribute('data-id'), gatilho.getAttribute('data-materia') || '');
        }
    });

    const modalPessoal = document.getElementById('modal-pessoal');
    const formPessoal = document.getElementById('form-pessoal');
    const extraPessoal = document.getElementById('pessoal-extra');

    const limparPessoal = (materia = '') => {
        if (!formPessoal) {
            return;
        }
        formPessoal.reset();
        formPessoal.querySelector('#pessoal-id').value = '';
        formPessoal.querySelector('#pessoal-titulo').textContent = 'Nova atividade pessoal';
        formPessoal.querySelector('#pessoal-materia').value = materia;
        if (extraPessoal) {
            extraPessoal.hidden = true;
            extraPessoal.innerHTML = '';
        }
    };

    const abrirPessoal = async (id, materia) => {
        if (!modalPessoal || !formPessoal) {
            return;
        }

        limparPessoal(materia || '');

        if (id) {
            formPessoal.querySelector('#pessoal-titulo').textContent = 'Atividade pessoal';
            try {
                const resposta = await fetch(`${window.APP_BASE}/ajax/pessoal.php?id=${id}`);
                const dados = await resposta.json();
                if (dados.ok) {
                    formPessoal.querySelector('#pessoal-id').value = dados.id;
                    formPessoal.querySelector('#pessoal-titulo-campo').value = dados.titulo;
                    formPessoal.querySelector('#pessoal-data').value = dados.data_evento;
                    formPessoal.querySelector('#pessoal-hora').value = dados.hora || '';
                    formPessoal.querySelector('#pessoal-materia').value = dados.materia || '';
                    formPessoal.querySelector('#pessoal-descricao').value = dados.descricao || '';
                    if (extraPessoal) {
                        extraPessoal.hidden = false;
                        extraPessoal.innerHTML = `
                            <form method="post" action="${window.APP_BASE}/aluno/concluir_pessoal.php">
                                <input type="hidden" name="id" value="${dados.id}">
                                <input type="hidden" name="voltar" value="${htmlEsc(window.location.pathname + window.location.search)}">
                                <button class="btn btn-ghost" type="submit">${dados.concluido ? 'Desmarcar' : 'Concluir'}</button>
                            </form>
                            <form method="post" action="${window.APP_BASE}/aluno/excluir_pessoal.php" data-confirm="Excluir esta atividade pessoal?">
                                <input type="hidden" name="id" value="${dados.id}">
                                <button class="btn btn-danger" type="submit">Excluir</button>
                            </form>
                        `;
                    }
                }
            } catch (erro) {
                // mantém o formulário vazio
            }
        }

        modalPessoal.showModal();
    };

    modalPessoal?.addEventListener('click', (evento) => {
        if (evento.target === modalPessoal) {
            modalPessoal.close();
        }
    });

    dialog?.addEventListener('click', (evento) => {
        if (evento.target === dialog) {
            fecharModal();
        }
    });
});
