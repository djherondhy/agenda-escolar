<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

instalarBancoSeNecessario();

$sessoesAbertas = sessoesAtivas();

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Informe e-mail e senha para entrar.';
    } else {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT id, nome, email, senha, tipo FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            iniciarSessaoUsuario($usuario);
            redirecionar(destinoPorTipo($usuario['tipo']));
        }

        $erro = 'E-mail ou senha incorretos. Confira os dados e tente de novo.';
    }
}

$temErro = $erro !== '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require __DIR__ . '/includes/tema_inicio.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar · Agenda Acadêmica</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700&family=Nunito:wght@700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/lapis.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/viva.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/tema.css') ?>">
</head>
<body class="auth-body">
    <div class="auth-shell">
        <aside class="auth-aside" aria-hidden="true">
            <a class="brand" href="<?= BASE_URL ?>/">
                <span class="brand-mark"><img src="<?= BASE_URL ?>/assets/img/lapis.svg" alt=""></span>
                <span class="brand-text">
                    <strong>Agenda</strong>
                    <small>Acadêmica</small>
                </span>
            </a>
            <h2>Prazos da turma em um só lugar.</h2>
            <p>Entre para ver provas, trabalhos e o que vence nesta semana.</p>
            <ul class="auth-preview">
                <li><span class="agenda-mark mark-prova"></span> Prova de Matemática · 28/08</li>
                <li><span class="agenda-mark mark-trabalho"></span> Trabalho de História · 02/09</li>
                <li><span class="agenda-mark mark-tarefa"></span> Atividade de PHP · 05/09</li>
            </ul>
        </aside>

        <main class="auth-main">
            <div class="auth-tools">
                <a class="auth-back" href="<?= BASE_URL ?>/">Voltar ao início</a>
                <?php require __DIR__ . '/includes/tema_botao.php'; ?>
            </div>

            <form class="auth-card" method="post" action="<?= BASE_URL ?>/login.php" novalidate>
                <div class="auth-brand">
                    <h1>Entrar</h1>
                    <p>Use o e-mail da escola para acessar seu perfil.</p>
                </div>

                <?php if ($sessoesAbertas): ?>
                    <div class="auth-sessions">
                        <p>Há sessão aberta. Você pode entrar com outro perfil sem encerrar esta.</p>
                        <?php foreach ($sessoesAbertas as $sessao): ?>
                            <a class="auth-session-link" href="<?= BASE_URL . destinoPorTipo($sessao['tipo']) ?>">
                                Continuar como <?= e(rotuloPerfil($sessao['tipo'])) ?> · <?= e($sessao['nome']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($temErro): ?>
                    <div class="alert alert-error" role="alert" id="login-erro"><?= e($erro) ?></div>
                <?php endif; ?>

                <div class="field">
                    <label for="email">E-mail</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="<?= e($email) ?>"
                        required
                        autocomplete="username"
                        spellcheck="false"
                        autofocus
                        <?= $temErro ? 'aria-invalid="true" aria-describedby="login-erro"' : '' ?>
                    >
                </div>

                <div class="field">
                    <label for="senha">Senha</label>
                    <div class="field-password">
                        <input
                            id="senha"
                            name="senha"
                            type="password"
                            required
                            autocomplete="current-password"
                            <?= $temErro ? 'aria-invalid="true" aria-describedby="login-erro"' : '' ?>
                        >
                        <button class="field-toggle" type="button" data-toggle-password="senha" aria-controls="senha">
                            Mostrar
                        </button>
                    </div>
                </div>

                <button class="btn btn-stamp" type="submit">Entrar</button>
            </form>

        </main>
    </div>
    <script src="<?= asset('assets/js/script.js') ?>"></script>
    <script src="<?= asset('assets/js/viva.js') ?>"></script>
    <script src="<?= asset('assets/js/tema.js') ?>"></script>
</body>
</html>
