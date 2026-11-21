/* Service worker da Agenda Acadêmica: recebe avisos (Web Push) e abre a atividade ao tocar. */

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (evento) => {
    evento.waitUntil(self.clients.claim());
});

self.addEventListener('push', (evento) => {
    const escopo = self.registration.scope;
    let dados = {
        title: 'Agenda Acadêmica',
        body: 'Nova atividade da turma',
        url: new URL('aluno/notificacoes.php', escopo).href,
    };

    try {
        if (evento.data) {
            dados = Object.assign(dados, evento.data.json());
        }
    } catch (erro) {
        try {
            dados.body = evento.data.text() || dados.body;
        } catch (erro2) {
            // mantém o texto padrão
        }
    }

    const opcoes = {
        body: dados.body,
        icon: dados.icon || new URL('assets/img/icon-192.png', escopo).href,
        badge: dados.badge || new URL('assets/img/icon-192.png', escopo).href,
        data: { url: dados.url },
        lang: 'pt-BR',
    };
    if (dados.tag) {
        opcoes.tag = dados.tag;
    }

    evento.waitUntil(self.registration.showNotification(dados.title, opcoes));
});

self.addEventListener('notificationclick', (evento) => {
    evento.notification.close();
    const destino = (evento.notification.data && evento.notification.data.url)
        || new URL('aluno/notificacoes.php', self.registration.scope).href;

    evento.waitUntil((async () => {
        const janelas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const janela of janelas) {
            if (janela.url.startsWith(self.registration.scope) && 'focus' in janela) {
                await janela.focus();
                if ('navigate' in janela) {
                    return janela.navigate(destino);
                }
                return undefined;
            }
        }
        return self.clients.openWindow(destino);
    })());
});

// O navegador trocou a inscrição (chaves expiradas): renova e avisa o servidor.
self.addEventListener('pushsubscriptionchange', (evento) => {
    evento.waitUntil((async () => {
        const antiga = evento.oldSubscription;
        const chave = antiga && antiga.options && antiga.options.applicationServerKey;
        if (!chave) {
            return;
        }
        const nova = await self.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: chave,
        });
        await fetch(new URL('aluno/push_salvar.php', self.registration.scope).href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(nova.toJSON()),
        });
    })());
});
