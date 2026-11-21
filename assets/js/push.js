/* Avisos (Web Push) para alunos: navegador no computador e celular (Android e iPhone). */
(function () {
    'use strict';

    const config = window.APP_PUSH;
    if (!config || !config.vapid) {
        return;
    }

    const base = window.APP_BASE || '';
    const suportado = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const instalado = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    const els = {
        status: document.getElementById('push-status'),
        ativar: document.getElementById('push-ativar'),
        teste: document.getElementById('push-teste'),
        desativar: document.getElementById('push-desativar'),
        dica: document.getElementById('push-dica'),
    };

    const bytes = (texto) => {
        const pad = '='.repeat((4 - (texto.length % 4)) % 4);
        const bruto = atob((texto + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(bruto, (c) => c.charCodeAt(0));
    };

    const mostrar = (el, visivel) => {
        if (el) {
            el.hidden = !visivel;
        }
    };

    const dizer = (texto, erro = false) => {
        if (els.status) {
            els.status.textContent = texto;
            els.status.classList.toggle('is-erro', erro);
        }
    };

    const enviar = (url, corpo) => fetch(`${base}/aluno/${url}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(corpo || {}),
    });

    const registrar = () => navigator.serviceWorker.register(`${base}/sw.js`, { scope: `${base}/` })
        .then(() => navigator.serviceWorker.ready);

    const assinar = async (registro) => {
        let inscricao = await registro.pushManager.getSubscription();
        if (!inscricao) {
            inscricao = await registro.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: bytes(config.vapid),
            });
        }
        const resposta = await enviar('push_salvar.php', inscricao.toJSON());
        if (!resposta.ok) {
            throw new Error('salvar');
        }
        return inscricao;
    };

    const atualizarTela = async () => {
        if (!els.status) {
            return;
        }
        mostrar(els.ativar, false);
        mostrar(els.teste, false);
        mostrar(els.desativar, false);
        mostrar(els.dica, false);

        if (!suportado) {
            if (ios && !instalado) {
                dizer('No iPhone/iPad os avisos só funcionam com o app instalado na tela inicial.', true);
                mostrar(els.dica, true);
            } else {
                dizer('Este navegador não recebe avisos. Use Chrome, Edge, Firefox ou Safari atualizado.', true);
            }
            return;
        }

        if (Notification.permission === 'denied') {
            dizer('Os avisos estão bloqueados para este site. Libere nas configurações do navegador (cadeado ao lado do endereço) e recarregue.', true);
            return;
        }

        const registro = await navigator.serviceWorker.getRegistration(`${base}/`);
        const inscricao = registro ? await registro.pushManager.getSubscription() : null;

        if (Notification.permission === 'granted' && inscricao) {
            dizer('Avisos ativados neste aparelho.');
            mostrar(els.teste, true);
            mostrar(els.desativar, true);
            return;
        }

        dizer('Avisos desativados neste aparelho.');
        mostrar(els.ativar, true);
    };

    els.ativar?.addEventListener('click', async () => {
        els.ativar.disabled = true;
        try {
            const permissao = await Notification.requestPermission();
            if (permissao !== 'granted') {
                dizer('Permissão não concedida. Toque em "Permitir" quando o navegador perguntar.', true);
                return;
            }
            const registro = await registrar();
            await assinar(registro);
            await atualizarTela();
        } catch (erro) {
            dizer('Não foi possível ativar. Confira se o site está em HTTPS e tente de novo.', true);
        } finally {
            els.ativar.disabled = false;
        }
    });

    els.teste?.addEventListener('click', async () => {
        els.teste.disabled = true;
        dizer('Enviando aviso de teste…');
        try {
            const resposta = await enviar('push_teste.php');
            const dados = await resposta.json();
            dizer(dados.mensagem || 'Teste enviado.', !dados.ok);
        } catch (erro) {
            dizer('Não foi possível enviar o teste agora.', true);
        } finally {
            els.teste.disabled = false;
        }
    });

    els.desativar?.addEventListener('click', async () => {
        try {
            const registro = await navigator.serviceWorker.getRegistration(`${base}/`);
            const inscricao = registro ? await registro.pushManager.getSubscription() : null;
            if (inscricao) {
                await enviar('push_salvar.php', { acao: 'remover', endpoint: inscricao.endpoint });
                await inscricao.unsubscribe();
            }
        } catch (erro) {
            /* segue para atualizar a tela */
        }
        await atualizarTela();
    });

    if (suportado) {
        // Em todas as páginas: mantém o service worker pronto e a inscrição em dia no servidor.
        registrar().then(async (registro) => {
            if (Notification.permission === 'granted' && !sessionStorage.getItem('push-sync-' + config.alunoId)) {
                sessionStorage.setItem('push-sync-' + config.alunoId, '1');
                await assinar(registro).catch(() => {});
            }
        }).catch(() => {}).finally(atualizarTela);
    } else {
        atualizarTela();
    }
})();
