(function () {
    const reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduzido || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        return;
    }

    document.querySelectorAll('.panel, .stat, .dash-hero, .lp-profile, .auth-card').forEach((cartao) => {
        cartao.addEventListener('pointermove', (evento) => {
            const caixa = cartao.getBoundingClientRect();
            const x = (evento.clientX - caixa.left) / caixa.width - 0.5;
            const y = (evento.clientY - caixa.top) / caixa.height - 0.5;
            cartao.style.transform = `perspective(700px) rotateX(${(-y * 4).toFixed(2)}deg) rotateY(${(x * 5).toFixed(2)}deg) translateY(-3px)`;
        });
        cartao.addEventListener('pointerleave', () => {
            cartao.style.transform = '';
        });
    });
})();
