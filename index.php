<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$sessoesAbertas = sessoesAtivas();

if (count($sessoesAbertas) === 1) {
    redirecionar(destinoPorTipo($sessoesAbertas[0]['tipo']));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário Escolar · Organização da rotina</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="lp-body">
    <header class="lp-nav">
        <a class="brand" href="<?= BASE_URL ?>/">
            <span class="brand-mark" aria-hidden="true">CE</span>
            <span class="brand-text">
                <strong>Calendário</strong>
                <small>Escolar</small>
            </span>
        </a>
        <a class="btn btn-stamp lp-nav-btn" href="<?= BASE_URL ?>/login.php">Entrar</a>
    </header>

    <main>
        <section class="lp-hero">
            <div class="lp-hero-copy">
                <p class="lp-kicker">Sistema de organização da rotina escolar</p>
                <h1>Saiba o que vem pela frente: prova, trabalho e tarefa no mesmo lugar.</h1>
                <p class="lp-lead">
                    O Calendário Escolar ajuda a turma a acompanhar prazos acadêmicos.
                    O professor registra as atividades. O aluno vê o calendário da própria turma,
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

        <section class="lp-section" id="como-funciona">
            <h2>Como o sistema funciona</h2>
            <p class="lp-section-lead">Três passos. A turma inteira acompanha o mesmo prazo, cada aluno no seu ritmo.</p>

            <ol class="lp-steps">
                <li>
                    <span class="lp-step-num">1</span>
                    <h3>O professor cadastra</h3>
                    <p>Define título, tipo, turma, data e horário. A atividade fica ligada só às turmas dele.</p>
                </li>
                <li>
                    <span class="lp-step-num">2</span>
                    <h3>A turma vê no calendário</h3>
                    <p>Todos os alunos daquela turma encontram a atividade na lista e nos dias do mês.</p>
                </li>
                <li>
                    <span class="lp-step-num">3</span>
                    <h3>O aluno marca a conclusão</h3>
                    <p>O status é individual. Quando um aluno conclui, o dos colegas não muda.</p>
                </li>
            </ol>
        </section>

        <section class="lp-section">
            <h2>Dois perfis, funções diferentes</h2>
            <div class="lp-profiles">
                <article class="lp-profile">
                    <p class="lp-kicker">Professor</p>
                    <h3>Organiza a turma</h3>
                    <ul>
                        <li>Vê as turmas às quais está associado</li>
                        <li>Cria, edita e exclui atividades</li>
                        <li>Define tipo, data e horário</li>
                        <li>Acompanha o que vem nos próximos dias</li>
                    </ul>
                </article>
                <article class="lp-profile">
                    <p class="lp-kicker">Aluno</p>
                    <h3>Acompanha a própria rotina</h3>
                    <ul>
                        <li>Vê só as atividades da sua turma</li>
                        <li>Consulta o calendário mensal</li>
                        <li>Filtra por prova, trabalho, tarefa ou status</li>
                        <li>Marca pendentes e concluídas</li>
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
                <li><span class="agenda-mark mark-tarefa"></span> Tarefa</li>
                <li><span class="agenda-mark mark-outro"></span> Outro compromisso</li>
            </ul>
        </section>
    </main>

    <footer class="lp-footer">
        <div>
            <strong>Calendário Escolar</strong>
            <p>Ferramenta acadêmica para planejamento, prazos e acompanhamento de tarefas.</p>
        </div>
        <a class="btn btn-stamp" href="<?= BASE_URL ?>/login.php">Entrar</a>
    </footer>
</body>
</html>
