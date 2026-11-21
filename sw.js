self.addEventListener('push', (evento) => {
    let dados = { title: 'Agenda Acadêmica', body: 'Nova atividade da turma', url: './aluno/notificacoes.php' };
    try {
        dados = Object.assign(dados, evento.data ? evento.data.json() : {});
    } catch (erro) {
        // mantém o texto padrão
    }

    evento.waitUntil(self.registration.showNotification(dados.title, {
        body: dados.body,
        data: { url: dados.url },
    }));
});

self.addEventListener('notificationclick', (evento) => {
    evento.notification.close();
    const url = evento.notification.data && evento.notification.data.url;
    if (url) {
        evento.waitUntil(clients.openWindow(url));
    }
});
