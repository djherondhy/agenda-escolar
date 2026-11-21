<script>
(function () {
    try {
        var salvo = localStorage.getItem('ce-tema');
        var escuro = salvo ? salvo === 'escuro' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (escuro) {
            document.documentElement.setAttribute('data-tema', 'escuro');
        }
    } catch (e) {}
})();
</script>
