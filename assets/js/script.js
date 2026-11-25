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

    const montarBarraApp = () => {
        const barra = document.querySelector('.app-barra');
        const fonte = document.getElementById('barra-fonte');
        if (!barra || !fonte || !window.matchMedia('(max-width: 960px)').matches) {
            return;
        }
        const itens = [...fonte.querySelectorAll('[data-barra-item]')];
        const centro = itens.find((item) => item.hasAttribute('data-barra-centro')) || null;
        const laterais = itens.filter((item) => item !== centro);
        const esquerda = barra.querySelector('[data-barra-esq]');
        const direita = barra.querySelector('[data-barra-dir]');
        const meio = barra.querySelector('[data-barra-meio]');
        const mais = barra.querySelector('[data-mais]');
        const lista = barra.querySelector('[data-mais-lista]');
        if (!esquerda || !direita || !meio || !mais || !lista) {
            return;
        }

        meio.replaceChildren(...(centro ? [centro.cloneNode(true)] : []));
        const livre = Math.max(68, barra.clientWidth - (centro ? 84 : 0) - 8);
        const cabem = Math.max(1, Math.floor(livre / 68));
        const visiveis = laterais.length <= cabem ? laterais : laterais.slice(0, Math.max(0, cabem - 1));
        const extras = laterais.slice(visiveis.length);
        const metade = Math.ceil(visiveis.length / 2);
        esquerda.replaceChildren(...visiveis.slice(0, metade).map((item) => item.cloneNode(true)));
        direita.replaceChildren(...visiveis.slice(metade).map((item) => item.cloneNode(true)));
        lista.replaceChildren(...extras.map((item) => item.cloneNode(true)));
        mais.hidden = extras.length === 0;
        if (extras.length === 0) {
            mais.removeAttribute('open');
        }
        const temAtivo = extras.some((item) => item.classList.contains('is-active'));
        mais.querySelector('summary')?.classList.toggle('is-active', temAtivo);
        direita.append(mais);
    };

    montarBarraApp();
    window.addEventListener('resize', montarBarraApp);

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

        formulario.querySelectorAll('.js-representante').forEach((caixa) => {
            caixa.disabled = !ehAluno;
        });

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
        if (dados.pode_concluir) {
            acoes.push(`<form method="post" action="${window.APP_BASE}/professor/concluir_evento.php">
                <input type="hidden" name="id" value="${dados.id}">
                <input type="hidden" name="voltar" value="${htmlEsc(window.location.pathname + window.location.search)}">
                ${htmlBotaoConcluir(dados.concluido)}
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
            <p class="agenda-meta">Representante: ${htmlEsc(dados.professor)}</p>
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
            ${dados.professores?.length ? `<p class="agenda-meta">Representantes: ${htmlEsc(dados.professores.join('; '))}</p>` : ''}
            ${proximas ? `<div class="modal-list">${proximas}</div>` : '<p class="empty">Nenhuma atividade nesta turma.</p>'}
        `;
    };

    const iconeCheck = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.6 9.2 16.8 19 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    const htmlBotaoConcluir = (concluido) => {
        const rotulo = concluido ? 'Desmarcar conclusão' : 'Marcar como concluída';
        return `<button class="btn btn-concluir${concluido ? ' is-done' : ''}" type="submit" aria-label="${rotulo}" title="${rotulo}">${iconeCheck}</button>`;
    };

    const botaoConcluir = (item) => {
        let acao = '';
        if (item.origem === 'pessoal') {
            acao = '/aluno/concluir_pessoal.php';
        } else if (item.pode_concluir) {
            acao = '/professor/concluir_evento.php';
        } else {
            return '';
        }
        return `<form method="post" action="${window.APP_BASE}${acao}">
            <input type="hidden" name="id" value="${item.id}">
            <input type="hidden" name="voltar" value="${htmlEsc(window.location.pathname + window.location.search)}">
            ${htmlBotaoConcluir(Number(item.concluido))}
        </form>`;
    };

    const montarDia = (titulo, itens, dataIso, novaUrl) => {
        const lista = itens.map((item) => {
            const origem = item.origem === 'pessoal' ? 'pessoal' : 'evento';
            const concluir = botaoConcluir(item);
            return `<div class="agenda-item agenda-item-day ${Number(item.concluido) ? 'is-done' : 'is-pending'}">
                <span class="agenda-mark mark-${htmlEsc(item.tipo === 'pessoal' ? 'outro' : item.tipo)}"></span>
                <div>
                    <button class="link-modal" type="button" data-modal="${origem}" data-id="${item.id}">${htmlEsc(item.titulo)}</button>
                    <p class="agenda-meta">${item.hora ? htmlEsc(item.hora) + ' · ' : ''}${htmlEsc(item.turma_nome || '')}</p>
                </div>
                ${concluir}
            </div>`;
        }).join('');

        let adicionar = '';
        if (novaUrl) {
            adicionar = `<a class="btn btn-stamp" href="${htmlEsc(novaUrl)}">Adicionar atividade</a>`;
        } else if (dataIso) {
            adicionar = `<button class="btn btn-stamp" type="button" data-modal="pessoal" data-data="${htmlEsc(dataIso)}">Adicionar atividade</button>`;
        }

        return `
            <button class="modal-close" type="button" data-modal-close>Fechar</button>
            <h2 id="modal-titulo">${htmlEsc(titulo)}</h2>
            <p class="agenda-meta">${itens.length ? itens.length + ' atividade' + (itens.length === 1 ? '' : 's') + ' neste dia' : 'Nenhuma atividade neste dia'}</p>
            <div class="modal-list">${lista || '<p class="empty">Nada marcado neste dia.</p>'}</div>
            <div class="modal-actions">${adicionar}</div>
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
            const dataIso = gatilho.getAttribute('data-data') || '';
            if (!itens.length && dataIso && document.getElementById('modal-pessoal')) {
                abrirPessoal(null, '', dataIso);
                return;
            }
            abrirModal(montarDia(
                gatilho.getAttribute('data-dia') || 'Dia',
                itens,
                dataIso,
                gatilho.getAttribute('data-nova') || ''
            ));
            return;
        }

        if (tipo === 'pessoal') {
            evento.preventDefault();
            evento.stopPropagation();
            if (gatilho.closest('#modal-detalhe')) {
                dialog?.close();
            }
            abrirPessoal(
                gatilho.getAttribute('data-id'),
                gatilho.getAttribute('data-materia') || '',
                gatilho.getAttribute('data-data') || ''
            );
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
        const arquivos = formPessoal.querySelector('#pessoal-arquivos');
        if (arquivos) {
            arquivos.hidden = true;
            arquivos.innerHTML = '';
        }
        if (extraPessoal) {
            extraPessoal.hidden = true;
            extraPessoal.innerHTML = '';
        }
    };

    const abrirPessoal = async (id, materia, data) => {
        if (!modalPessoal || !formPessoal) {
            return;
        }

        limparPessoal(materia || '');
        if (data) {
            formPessoal.querySelector('#pessoal-data').value = data;
        }

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
                    const arquivos = formPessoal.querySelector('#pessoal-arquivos');
                    if (arquivos) {
                        const lista = Array.isArray(dados.anexos) ? dados.anexos : [];
                        arquivos.hidden = lista.length === 0;
                        arquivos.innerHTML = lista.map((anexo) => `<a href="${htmlEsc(anexo.url)}" target="_blank" rel="noopener">${htmlEsc(anexo.nome)}</a>`).join('');
                    }
                    if (extraPessoal) {
                        extraPessoal.hidden = false;
                        extraPessoal.innerHTML = `
                            <form method="post" action="${window.APP_BASE}/aluno/concluir_pessoal.php">
                                <input type="hidden" name="id" value="${dados.id}">
                                <input type="hidden" name="voltar" value="${htmlEsc(window.location.pathname + window.location.search)}">
                                ${htmlBotaoConcluir(dados.concluido)}
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

    const listaAtividades = document.getElementById('lista-atividades');
    if (listaAtividades) {
        const estado = {
            origem: listaAtividades.dataset.origem || 'todas',
            tipo: listaAtividades.dataset.tipo || 'todas',
            status: listaAtividades.dataset.status || 'todas',
        };
        const vazio = document.getElementById('atividades-vazio');

        const itens = [...listaAtividades.querySelectorAll('[data-atividade]')];
        const combina = (item, origem, tipo, status) => {
            const origemOk = origem === 'todas' || item.dataset.origem === origem;
            const tipoOk = item.dataset.origem !== 'turma' || tipo === 'todas' || item.dataset.tipo === tipo;
            const statusOk = status === 'todas' || item.dataset.status === status;
            return origemOk && tipoOk && statusOk;
        };

        const aplicarFiltros = () => {
            let visiveis = 0;
            itens.forEach((item) => {
                const mostra = combina(item, estado.origem, estado.tipo, estado.status);
                item.hidden = !mostra;
                if (mostra) {
                    visiveis += 1;
                }
            });

            const contar = (origem, tipo, status) => itens.filter((item) => combina(item, origem, tipo, status)).length;

            listaAtividades.querySelectorAll('[data-filtro-select]').forEach((select) => {
                const grupo = select.dataset.filtroSelect;
                select.value = estado[grupo] || 'todas';
                select.classList.toggle('is-set', select.value !== 'todas');
                select.querySelectorAll('option').forEach((opcao) => {
                    const total = contar(
                        grupo === 'origem' ? opcao.value : estado.origem,
                        grupo === 'tipo' ? opcao.value : estado.tipo,
                        estado.status,
                    );
                    opcao.textContent = `${opcao.dataset.rotulo} · ${total}`;
                });
            });

            listaAtividades.querySelectorAll('.filtro-chave input').forEach((chave) => {
                const valor = chave.dataset.filtro;
                const total = contar(estado.origem, estado.tipo, valor);
                const contagem = chave.parentElement?.querySelector('[data-contagem]');
                if (contagem) {
                    contagem.textContent = String(total);
                }
                chave.checked = estado.status === 'todas' || estado.status === valor;
            });

            const nomes = {
                turma: 'da turma',
                pessoais: 'pessoais',
                prova: 'provas',
                trabalho: 'trabalhos',
                atividade: 'atividades',
                reuniao: 'reuniões',
                pendentes: 'pendentes',
                concluidas: 'concluídas',
            };
            const recorte = [
                estado.origem !== 'todas' ? nomes[estado.origem] : '',
                estado.origem !== 'pessoais' && estado.tipo !== 'todas' ? nomes[estado.tipo] : '',
                estado.status !== 'todas' ? nomes[estado.status] : '',
            ].filter(Boolean);
            const resumo = listaAtividades.querySelector('[data-atividades-resumo]');
            if (resumo) {
                const quantidade = visiveis === 1 ? '1 atividade' : `${visiveis} atividades`;
                if (estado.status === 'nenhuma') {
                    resumo.textContent = recorte.length
                        ? `Nenhuma situação marcada · ${recorte.join(' · ')}`
                        : 'Nenhuma situação marcada';
                } else {
                    resumo.textContent = recorte.length
                        ? `Mostrando ${quantidade}: ${recorte.join(' · ')}`
                        : `Mostrando ${quantidade}`;
                }
            }
            const limpar = listaAtividades.querySelector('[data-limpar-filtros]');
            if (limpar) {
                limpar.hidden = estado.origem === 'todas' && estado.tipo === 'todas' && estado.status === 'todas';
            }

            const tipos = listaAtividades.querySelector('[data-filtro-grupo="tipo"]');
            if (tipos) {
                tipos.hidden = estado.origem === 'pessoais';
            }
            if (vazio) {
                vazio.hidden = visiveis > 0;
            }

            const params = new URLSearchParams({
                origem: estado.origem,
                tipo: estado.tipo,
                status: estado.status,
            });
            history.replaceState(null, '', `${window.location.pathname}?${params}`);
        };

        listaAtividades.addEventListener('change', (evento) => {
            const select = evento.target.closest('[data-filtro-select]');
            if (select && listaAtividades.contains(select)) {
                estado[select.dataset.filtroSelect] = select.value;
                aplicarFiltros();
                return;
            }
            const chave = evento.target.closest('.filtro-chave input');
            if (!chave || !listaAtividades.contains(chave)) {
                return;
            }
            const pendentes = listaAtividades.querySelector('[data-filtro="pendentes"]')?.checked;
            const concluidas = listaAtividades.querySelector('[data-filtro="concluidas"]')?.checked;
            if (pendentes && concluidas) {
                estado.status = 'todas';
            } else if (pendentes) {
                estado.status = 'pendentes';
            } else if (concluidas) {
                estado.status = 'concluidas';
            } else {
                estado.status = 'nenhuma';
            }
            aplicarFiltros();
        });

        listaAtividades.querySelector('[data-limpar-filtros]')?.addEventListener('click', () => {
            estado.origem = 'todas';
            estado.tipo = 'todas';
            estado.status = 'todas';
            aplicarFiltros();
        });

        aplicarFiltros();
    }

    const avisoSocial = (texto, erro = false) => {
        if (!texto) {
            return;
        }
        document.querySelector('.social-toast')?.remove();
        const aviso = document.createElement('div');
        aviso.className = `social-toast${erro ? ' is-erro' : ''}`;
        aviso.setAttribute('role', 'status');
        aviso.textContent = texto;
        document.body.appendChild(aviso);
        setTimeout(() => aviso.remove(), 3400);
    };

    const textoContagem = (total) => (
        total ? `${total} comentário${total === 1 ? '' : 's'}` : 'Seja o primeiro a comentar'
    );

    const ajustarPublicacoes = (delta) => {
        const numero = document.querySelector('.perfil-numeros div:first-child dd');
        if (numero) {
            numero.textContent = String(Math.max(0, (parseInt(numero.textContent, 10) || 0) + delta));
        }
        const linha = document.querySelector('.turmas-card a.is-active small');
        if (linha) {
            const total = Math.max(0, (parseInt(linha.textContent, 10) || 0) + delta);
            linha.textContent = `${total} publicaç${total === 1 ? 'ão' : 'ões'}`;
        }
    };

    const aplicarSocial = (form, acao, dados) => {
        if (dados.aviso) {
            avisoSocial(dados.aviso, true);
        }

        if (acao === 'publicar') {
            const stream = form.closest('.social-stream');
            stream?.querySelector('.social-empty')?.remove();
            let divisor = stream?.querySelector('.feed-divisor');
            if (stream && !divisor) {
                divisor = document.createElement('div');
                divisor.className = 'feed-divisor';
                divisor.innerHTML = '<span>Mais recentes</span>';
                form.insertAdjacentElement('afterend', divisor);
            }
            divisor?.insertAdjacentHTML('afterend', dados.html || '');
            const campo = form.querySelector('textarea');
            if (campo) {
                campo.value = '';
                campo.style.height = '';
            }
            form.querySelectorAll('input[type="file"]').forEach((input) => {
                input.value = '';
            });
            const lista = form.querySelector('[data-composer-files]');
            if (lista) {
                lista.hidden = true;
                lista.innerHTML = '';
            }
            ajustarPublicacoes(1);
            return;
        }

        if (acao === 'comentar') {
            const post = form.closest('.social-post');
            form.insertAdjacentHTML('beforebegin', dados.html || '');
            const campo = form.querySelector('input[name="texto"]');
            if (campo) campo.value = '';
            const contagem = post?.querySelector('.post-contagem');
            if (contagem) contagem.textContent = textoContagem(post.querySelectorAll('.comentario').length);
            return;
        }

        if (acao === 'apagar_post') {
            const post = form.closest('.social-post');
            const stream = post?.closest('.social-stream');
            if (post && stream && Number(post.dataset.autor) === Number(stream.dataset.eu)) {
                ajustarPublicacoes(-1);
            }
            post?.remove();
            if (stream && !stream.querySelector('.social-post')) {
                stream.querySelector('.feed-divisor')?.remove();
                stream.insertAdjacentHTML('beforeend', '<div class="social-card social-empty"><strong>Nenhuma publicação por aqui ainda</strong><p>Comece a conversa com um resumo, um material ou uma dúvida.</p></div>');
            }
            return;
        }

        if (acao === 'apagar_comentario') {
            const post = form.closest('.social-post');
            form.closest('.comentario')?.remove();
            const contagem = post?.querySelector('.post-contagem');
            if (contagem) contagem.textContent = textoContagem(post.querySelectorAll('.comentario').length);
            const ocultos = post?.querySelectorAll('[data-comentario-oculto]').length || 0;
            const mais = post?.querySelector('[data-mais-comentarios]');
            if (mais) {
                if (!ocultos) {
                    mais.remove();
                } else {
                    mais.textContent = `Ver ${ocultos} comentário${ocultos === 1 ? '' : 's'} anterior${ocultos === 1 ? '' : 'es'}`;
                }
            }
            return;
        }

        if (acao === 'inscrever') {
            const card = form.closest('[data-sala]');
            if (card && dados.html) {
                card.outerHTML = dados.html;
            }
            if (dados.feed && document.querySelector('.sala-bloqueio')) {
                const feed = document.querySelector('.social-feed');
                if (feed) feed.innerHTML = dados.feed;
            }
            if (dados.status) {
                const grupo = document.querySelector('.grupo-acao');
                if (grupo) grupo.innerHTML = dados.status;
            }
            avisoSocial('Agora você participa desta sala.');
            return;
        }

        if (acao === 'criar_sala') {
            const grade = document.querySelector('[data-sala-grid]');
            if (grade && dados.html) {
                grade.insertAdjacentHTML('afterbegin', dados.html);
            }
            form.reset();
            form.closest('details')?.removeAttribute('open');
            document.querySelectorAll('.salas-hero-numeros strong').forEach((item) => {
                item.textContent = String((parseInt(item.textContent, 10) || 0) + 1);
            });
            const vazio = document.querySelector('[data-salas-vazio]');
            if (vazio) vazio.hidden = true;
            avisoSocial('Sala criada. Você já participa.');
        }
    };

    document.addEventListener('submit', async (evento) => {
        const form = evento.target;
        if (!(form instanceof HTMLFormElement) || !String(form.action).includes('/aluno/social.php')) {
            return;
        }
        const acao = form.querySelector('[name="acao"]')?.value || '';
        const noLugar = ['publicar', 'comentar', 'apagar_post', 'apagar_comentario', 'inscrever', 'criar_sala'];
        if (!noLugar.includes(acao)) {
            return;
        }
        if (form.hasAttribute('data-confirm') && form.dataset.confirmed !== '1') {
            return;
        }

        evento.preventDefault();
        evento.stopImmediatePropagation();
        delete form.dataset.confirmed;

        const botao = form.querySelector('[type="submit"]');
        if (botao) botao.disabled = true;
        try {
            const resposta = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-CE-Ajax': '1' },
            });
            const dados = await resposta.json();
            if (!dados.ok) {
                avisoSocial(dados.erro || 'Não foi possível concluir esta ação.', true);
                return;
            }
            aplicarSocial(form, acao, dados);
        } catch (erro) {
            avisoSocial('A conexão falhou. Tente de novo.', true);
        } finally {
            if (botao?.isConnected) botao.disabled = false;
        }
    }, true);

    document.querySelectorAll('[data-autogrow]').forEach((campo) => {
        const ajustar = () => {
            campo.style.height = 'auto';
            campo.style.height = `${campo.scrollHeight + 2}px`;
        };
        campo.addEventListener('input', ajustar);
        campo.addEventListener('focus', () => {
            if (campo.rows < 3) {
                campo.rows = 3;
            }
            ajustar();
        });
    });

    document.querySelectorAll('[data-composer]').forEach((composer) => {
        const lista = composer.querySelector('[data-composer-files]');
        const inputs = composer.querySelectorAll('[data-composer-input]');
        const atualizar = () => {
            const nomes = [];
            inputs.forEach((input) => {
                Array.from(input.files || []).forEach((arquivo) => nomes.push(arquivo.name));
            });
            if (!lista) {
                return;
            }
            lista.hidden = nomes.length === 0;
            lista.innerHTML = nomes.map((nome) => `<span>${htmlEsc(nome)}</span>`).join('');
        };
        inputs.forEach((input) => input.addEventListener('change', atualizar));
    });

    document.addEventListener('click', async (evento) => {
        const comentar = evento.target.closest('[data-comentar]');
        if (comentar) {
            comentar.closest('.social-post')?.querySelector('.comentario-form input[type="text"]')?.focus();
            return;
        }

        const mais = evento.target.closest('[data-mais-comentarios]');
        if (mais) {
            mais.closest('.post-comentarios')?.querySelectorAll('[data-comentario-oculto]').forEach((item) => {
                item.hidden = false;
            });
            mais.remove();
            return;
        }

        const copiar = evento.target.closest('[data-copiar-link]');
        if (copiar) {
            const post = copiar.closest('.social-post');
            const url = `${window.location.origin}${window.location.pathname}${window.location.search}#${post?.id || ''}`;
            const rotulo = copiar.querySelector('span');
            try {
                await navigator.clipboard.writeText(url);
                if (rotulo) rotulo.textContent = 'Link copiado';
            } catch (erro) {
                if (rotulo) rotulo.textContent = 'Copie da barra de endereço';
            }
            copiar.classList.add('is-feito');
            setTimeout(() => {
                if (rotulo) rotulo.textContent = 'Copiar link';
                copiar.classList.remove('is-feito');
            }, 2200);
        }
    });

    document.addEventListener('click', (evento) => {
        document.querySelectorAll('.post-menu[open]').forEach((menu) => {
            if (!menu.contains(evento.target)) {
                menu.open = false;
            }
        });
    });

    const filtrosSala = document.querySelector('[data-salas-filtros]');
    if (filtrosSala) {
        const cartoes = document.querySelectorAll('[data-sala]');
        const vazio = document.querySelector('[data-salas-vazio]');
        const busca = filtrosSala.querySelector('[data-busca-sala]');
        let filtro = 'todas';
        const aplicar = () => {
            const termo = (busca?.value || '').trim().toLowerCase();
            let visiveis = 0;
            cartoes.forEach((cartao) => {
                const inscrito = cartao.dataset.inscrito === '1';
                const filtroOk = filtro === 'todas' || (filtro === 'minhas' && inscrito) || (filtro === 'novas' && !inscrito);
                const buscaOk = termo === '' || (cartao.dataset.busca || '').includes(termo);
                cartao.hidden = !(filtroOk && buscaOk);
                if (!cartao.hidden) visiveis += 1;
            });
            if (vazio) vazio.hidden = visiveis > 0;
        };
        filtrosSala.querySelectorAll('[data-filtro-sala]').forEach((botao) => {
            botao.addEventListener('click', () => {
                filtro = botao.dataset.filtroSala;
                filtrosSala.querySelectorAll('[data-filtro-sala]').forEach((outro) => {
                    const ativo = outro === botao;
                    outro.classList.toggle('is-active', ativo);
                    outro.setAttribute('aria-selected', String(ativo));
                });
                aplicar();
            });
        });
        busca?.addEventListener('input', aplicar);
    }

    const buscaMembro = document.querySelector('[data-busca-membro]');
    if (buscaMembro) {
        const linhas = document.querySelectorAll('[data-candidato]');
        const vazio = document.querySelector('[data-candidatos-vazio]');
        const filtrar = () => {
            const termo = buscaMembro.value.trim().toLowerCase();
            let visiveis = 0;
            linhas.forEach((linha) => {
                const ok = termo === '' || (linha.dataset.busca || '').includes(termo);
                linha.hidden = !ok;
                if (ok) visiveis += 1;
            });
            if (vazio) vazio.hidden = visiveis > 0;
        };
        buscaMembro.addEventListener('input', filtrar);
    }
});

function base64UrlParaBytes(texto) {
    const pad = '='.repeat((4 - (texto.length % 4)) % 4);
    const normal = (texto + pad).replace(/-/g, '+').replace(/_/g, '/');
    const bruto = atob(normal);
    return Uint8Array.from(bruto, (c) => c.charCodeAt(0));
}
