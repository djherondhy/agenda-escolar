<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$usuario = usuarioAtual();
$tituloPagina = $tituloPagina ?? 'Calendário Escolar';
$menuAtivo = $menuAtivo ?? '';
$tipoUsuario = $usuario['tipo'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tituloPagina) ?> · Calendário Escolar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if (!empty($usarEditor)): ?>
        <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="app-body">
    <div class="app-shell">
        <aside class="app-sidebar" id="sidebar">
            <a class="brand" href="<?= BASE_URL . destinoPorTipo($tipoUsuario) ?>">
                <span class="brand-mark" aria-hidden="true">CE</span>
                <span class="brand-text">
                    <strong>Calendário</strong>
                    <small>Escolar</small>
                </span>
            </a>

            <nav class="side-nav" aria-label="Menu principal">
                <?php if ($tipoUsuario === 'administrador'): ?>
                    <a class="<?= $menuAtivo === 'dashboard' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
                    <a class="<?= $menuAtivo === 'usuarios' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/admin/usuarios.php">Usuários</a>
                    <a class="<?= $menuAtivo === 'turmas' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/admin/turmas.php">Turmas</a>
                <?php elseif ($tipoUsuario === 'professor'): ?>
                    <a class="<?= $menuAtivo === 'dashboard' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/professor/dashboard.php">Dashboard</a>
                    <a class="<?= $menuAtivo === 'horarios' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/professor/horarios.php">Horários</a>
                    <a class="<?= $menuAtivo === 'eventos' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/professor/eventos.php">Atividades</a>
                    <a class="<?= $menuAtivo === 'turmas' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/professor/turmas.php">Turmas</a>
                <?php else: ?>
                    <a class="<?= $menuAtivo === 'dashboard' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/aluno/dashboard.php">Dashboard</a>
                    <a class="<?= $menuAtivo === 'horarios' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/aluno/horarios.php">Horários</a>
                    <a class="<?= $menuAtivo === 'disciplinas' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/aluno/disciplinas.php">Disciplinas</a>
                    <a class="<?= $menuAtivo === 'atividades' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/aluno/atividades.php">Atividades</a>
                    <a class="<?= $menuAtivo === 'calendario' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/aluno/calendario.php">Calendário</a>
                <?php endif; ?>
            </nav>

            <?php if ($usuario): ?>
                <div class="side-user">
                    <span class="side-user-name"><?= e($usuario['nome']) ?></span>
                    <span class="side-user-role"><?= e(rotuloPerfil($tipoUsuario)) ?></span>
                    <a class="side-user-out" href="<?= BASE_URL ?>/logout.php?contexto=<?= $tipoUsuario === 'administrador' ? 'administrador' : 'usuario' ?>">Sair</a>
                </div>
            <?php endif; ?>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <button class="nav-toggle" type="button" data-nav-toggle aria-controls="sidebar" aria-expanded="false">
                    Menu
                </button>
                <h1 class="page-title <?= $menuAtivo === 'dashboard' ? 'is-date' : '' ?>"><?= e($tituloPagina) ?></h1>
                <?php if (!empty($acaoTopo)): ?>
                    <div class="topbar-action"><?= $acaoTopo ?></div>
                <?php endif; ?>
            </header>
            <main class="app-content">
                <div class="nav-backdrop" data-nav-close hidden></div>
