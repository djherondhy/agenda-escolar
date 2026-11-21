<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/qr.php';

instalarBancoSeNecessario();

$sessoesAbertas = sessoesAtivas();

if (count($sessoesAbertas) === 1) {
    redirecionar(destinoPorTipo($sessoesAbertas[0]['tipo']));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require __DIR__ . '/includes/tema_inicio.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Acadêmica · Organização da rotina</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700&family=Nunito:wght@700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/lapis.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/viva.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/tema.css') ?>">
</head>
<body class="lp-body">
    <header class="lp-nav">
        <a class="brand" href="<?= BASE_URL ?>/">
            <span class="brand-mark"><img src="<?= BASE_URL ?>/assets/img/lapis.svg" alt=""></span>
            <span class="brand-text">
                <strong>Agenda</strong>
                <small>Acadêmica</small>
            </span>
        </a>
        <div class="lp-nav-acoes">
            <?php require __DIR__ . '/includes/tema_botao.php'; ?>
            <a class="btn btn-stamp lp-nav-btn" href="<?= BASE_URL ?>/login.php">Entrar</a>
        </div>
    </header>

    <main>
        <section class="lp-hero">
            <div class="lp-hero-copy">
                <p class="lp-kicker">Sistema de organização da rotina escolar</p>
                <h1>Saiba o que vem pela frente: prova, trabalho e tarefa no mesmo lugar.</h1>
                <p class="lp-lead">
                    A Agenda Acadêmica ajuda a turma a acompanhar prazos acadêmicos.
                    O representante de turma registra as atividades. O aluno vê o calendário da própria turma,
                    filtra o que importa e marca o que já concluiu.
                </p>
                <div class="lp-actions">
                    <?php if ($sessoesAbertas): ?>
                        <?php foreach ($sessoesAbertas as $sessao): ?>
                            <a class="btn btn-stamp" href="<?= BASE_URL . destinoPorTipo($sessao['tipo']) ?>">
                                Continuar como <?= e(rotuloPerfil($sessao['tipo'])) ?>
                            </a>
                        <?php endforeach; ?>
                        <a class="btn btn-ghost" href="<?= BASE_URL ?>/login.php">Entrar com outro perfil</a>
                    <?php else: ?>
                        <a class="btn btn-stamp" href="<?= BASE_URL ?>/login.php">Entrar no sistema</a>
                        <a class="btn btn-ghost" href="#como-funciona">Como funciona</a>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="lp-agenda" aria-label="Exemplo de semana no calendário">
                <img class="lp-art" src="<?= BASE_URL ?>/assets/img/hero.svg" alt="">
                <div class="lp-agenda-head">
                    <span>Agosto 2026</span>
                    <span>2º Ano Informática</span>
                </div>
                <ol class="lp-agenda-list">
                    <li>
                        <time datetime="2026-08-28">28</time>
                        <div>
                            <strong>Prova de Matemática</strong>
                            <span class="chip chip-prova">Prova · 08:00</span>
                        </div>
                    </li>
                    <li>
                        <time datetime="2026-08-30">30</time>
                        <div>
                            <strong>Reunião de orientação</strong>
                            <span class="chip chip-outro">Outro · 19:00</span>
                        </div>
                    </li>
                    <li>
                        <time datetime="2026-09-02">02</time>
                        <div>
                            <strong>Trabalho de História</strong>
                            <span class="chip chip-trabalho">Trabalho</span>
                        </div>
                    </li>
                    <li>
                        <time datetime="2026-09-05">05</time>
                        <div>
                            <strong>Atividade de PHP</strong>
                            <span class="chip chip-tarefa">Tarefa · 10:00</span>
                        </div>
                    </li>
                </ol>
            </aside>
        </section>

        <section class="lp-section lp-qr" id="no-celular">
            <div>
                <h2>Abrir no celular</h2>
                <p class="lp-section-lead">Aponte a câmera do celular para abrir a Agenda Acadêmica. Depois, é só entrar com o seu e-mail.</p>
                <p class="lp-qr-url"><?= e(urlParaCelular()) ?></p>
            </div>
            <div class="lp-qr-code"><?= qrSvg(urlParaCelular()) ?></div>
        </section>

        <section class="lp-section" id="como-funciona">
            <h2>Como o sistema funciona</h2>
            <p class="lp-section-lead">Três passos. A turma inteira acompanha o mesmo prazo, cada aluno no seu ritmo.</p>

            <ol class="lp-steps">
                <li>
                    <span class="lp-step-num">1</span>
                    <h3>O representante cadastra</h3>
                    <p>Define título, tipo, turma, data e horário. A atividade fica ligada só às turmas dele.</p>
                </li>
                <li>
                    <span class="lp-step-num">2</span>
                    <h3>A turma vê no calendário</h3>
                    <p>Todos os alunos daquela turma encontram a atividade na lista e nos dias do mês.</p>
                </li>
                <li>
                    <span class="lp-step-num">3</span>
                    <h3>A conclusão aparece para a turma</h3>
                    <p>O representante marca a atividade da turma como concluída e todos os alunos veem. O aluno só conclui o que ele mesmo criou.</p>
                </li>
            </ol>
        </section>

        <section class="lp-section">
            <h2>Dois perfis, funções diferentes</h2>
            <div class="lp-profiles">
                <article class="lp-profile">
                    <p class="lp-kicker">Representante de turma</p>
                    <h3>Organiza a turma</h3>
                    <ul>
                        <li>Vê as turmas às quais está associado</li>
                        <li>Cria, edita e exclui atividades</li>
                        <li>Define tipo, data e horário</li>
                        <li>Cadastra a grade de aulas da turma</li>
                        <li>Acompanha o que vem nos próximos dias</li>
                    </ul>
                </article>
                <article class="lp-profile">
                    <p class="lp-kicker">Aluno</p>
                    <h3>Acompanha a própria rotina</h3>
                    <ul>
                        <li>Vê só as atividades da sua turma</li>
                        <li>Consulta o calendário mensal</li>
                        <li>Filtra por prova, trabalho, atividade ou status</li>
                        <li>Consulta os horários cadastrados pelo representante</li>
                    </ul>
                </article>
            </div>
        </section>

        <section class="lp-section">
            <h2>O que dá para registrar</h2>
            <p class="lp-section-lead">Cada tipo aparece com uma cor própria no calendário, para identificar o prazo rapidamente.</p>
            <ul class="lp-types">
                <li><span class="agenda-mark mark-prova"></span> Prova</li>
                <li><span class="agenda-mark mark-trabalho"></span> Trabalho</li>
                <li><span class="agenda-mark mark-tarefa"></span> Atividade</li>
                <li><span class="agenda-mark mark-outro"></span> Reunião</li>
            </ul>
        </section>
    </main>

    <footer class="lp-footer">
        <div>
            <strong>Agenda Acadêmica</strong>
            <p>Ferramenta acadêmica para planejamento, prazos e acompanhamento de tarefas.</p>
        </div>
        <a class="btn btn-stamp" href="<?= BASE_URL ?>/login.php">Entrar</a>
    </footer>
    <script src="<?= asset('assets/js/viva.js') ?>"></script>
    <script src="<?= asset('assets/js/tema.js') ?>"></script>
</body>
</html>
