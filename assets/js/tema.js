(function () {
    const botao = document.querySelector('[data-tema-botao]');
    if (!botao) {
        return;
    }

    const aplicar = (escuro) => {
        if (escuro) {
            document.documentElement.setAttribute('data-tema', 'escuro');
        } else {
            document.documentElement.removeAttribute('data-tema');
        }
        botao.setAttribute('aria-pressed', escuro ? 'true' : 'false');
        botao.setAttribute('aria-label', escuro ? 'Ativar modo claro' : 'Ativar modo escuro');
    };

    aplicar(document.documentElement.getAttribute('data-tema') === 'escuro');

    botao.addEventListener('click', () => {
        const escuro = document.documentElement.getAttribute('data-tema') !== 'escuro';
        try {
            localStorage.setItem('ce-tema', escuro ? 'escuro' : 'claro');
        } catch (e) {}
        aplicar(escuro);
    });
})();
