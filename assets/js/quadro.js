(function () {
    const quadro = document.querySelector('[data-quadro][data-editar="1"]');
    if (!quadro) {
        return;
    }

    const action = quadro.getAttribute('data-action') || '';
    let arraste = null;

    function limpar() {
        quadro.querySelectorAll('.is-over').forEach((celula) => celula.classList.remove('is-over'));
        quadro.querySelectorAll('.is-dragging').forEach((item) => item.classList.remove('is-dragging'));
        if (arraste && arraste.ghost) {
            arraste.ghost.remove();
        }
        arraste = null;
    }

    function celulaEm(x, y) {
        const alvo = document.elementFromPoint(x, y);
        return alvo ? alvo.closest('.quadro-celula') : null;
    }

    async function enviar(dados) {
        const corpo = new URLSearchParams(dados);
        corpo.set('ajax', '1');
        const resposta = await fetch(action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: corpo.toString(),
        });
        const json = await resposta.json();
        if (!json.ok) {
            throw new Error(json.erro || 'Não foi possível atualizar a grade.');
        }
        window.location.reload();
    }

    quadro.addEventListener('pointerdown', (evento) => {
        if (evento.button !== 0) {
            return;
        }
        const origem = evento.target.closest('.quadro-aula, .quadro-chip');
        if (!origem || evento.target.closest('[data-esvaziar]')) {
            return;
        }
        arraste = {
            origem,
            x: evento.clientX,
            y: evento.clientY,
            ativo: false,
            ghost: null,
        };
        origem.setPointerCapture?.(evento.pointerId);
    });

    quadro.addEventListener('pointermove', (evento) => {
        if (!arraste) {
            return;
        }
        const dx = evento.clientX - arraste.x;
        const dy = evento.clientY - arraste.y;
        if (!arraste.ativo && Math.hypot(dx, dy) < 6) {
            return;
        }
        if (!arraste.ativo) {
            arraste.ativo = true;
            arraste.ghost = arraste.origem.cloneNode(true);
            arraste.ghost.classList.add('quadro-ghost');
            arraste.ghost.querySelectorAll('button').forEach((botao) => botao.remove());
            document.body.appendChild(arraste.ghost);
            arraste.origem.classList.add('is-dragging');
        }
        arraste.ghost.style.left = evento.clientX + 'px';
        arraste.ghost.style.top = evento.clientY + 'px';
        quadro.querySelectorAll('.is-over').forEach((celula) => celula.classList.remove('is-over'));
        const celula = celulaEm(evento.clientX, evento.clientY);
        if (celula) {
            celula.classList.add('is-over');
        }
        evento.preventDefault();
    });

    quadro.addEventListener('pointerup', async (evento) => {
        if (!arraste) {
            return;
        }
        const atual = arraste;
        const celula = atual.ativo ? celulaEm(evento.clientX, evento.clientY) : null;
        const mesma = celula && celula.contains(atual.origem);
        limpar();
        if (!atual.ativo || !celula || mesma) {
            return;
        }

        const dados = {
            dia_semana: celula.getAttribute('data-dia') || '',
            hora_inicio: celula.getAttribute('data-inicio') || '',
        };
        if (atual.origem.hasAttribute('data-id')) {
            dados.acao = 'mover_horario';
            dados.horario_id = atual.origem.getAttribute('data-id') || '';
        } else {
            dados.acao = 'definir_horario';
            dados.materia = atual.origem.getAttribute('data-materia') || '';
        }

        try {
            await enviar(dados);
        } catch (erro) {
            window.alert(erro.message);
        }
    });

    quadro.addEventListener('pointercancel', limpar);

    quadro.addEventListener('click', async (evento) => {
        const botao = evento.target.closest('[data-esvaziar]');
        if (!botao) {
            return;
        }
        const aula = botao.closest('.quadro-aula');
        if (!aula) {
            return;
        }
        try {
            await enviar({
                acao: 'esvaziar_horario',
                horario_id: aula.getAttribute('data-id') || '',
            });
        } catch (erro) {
            window.alert(erro.message);
        }
    });
})();
