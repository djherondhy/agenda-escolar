<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$usuario = usuarioAtual();
$tituloPagina = $tituloPagina ?? 'Agenda Acadêmica';
$menuAtivo = $menuAtivo ?? '';
$tipoUsuario = $usuario['tipo'] ?? '';
$ehRepresentante = $usuario ? usuarioEhRepresentante() : false;
$notificacoesNaoLidas = 0;
if ($tipoUsuario === 'aluno' && $usuario) {
    $alunoAvisos = obterAlunoPorUsuario(getPDO(), (int) $usuario['id']);
    if ($alunoAvisos) {
        $notificacoesNaoLidas = contarNotificacoesNaoLidas(getPDO(), (int) $alunoAvisos['id']);
    }
}

$urlPerfil = BASE_URL . ($tipoUsuario === 'administrador' ? '/admin/perfil.php' : '/aluno/perfil.php');

$itensMenu = [];
$adicionarMenu = static function (string $id, string $rotulo, string $href, bool $centro = false) use (&$itensMenu, $menuAtivo): void {
    $itensMenu[] = [
        'id' => $id,
        'rotulo' => $rotulo,
        'href' => $href,
        'centro' => $centro,
        'ativo' => $menuAtivo === $id,
    ];
};

if ($tipoUsuario === 'administrador') {
    $adicionarMenu('dashboard', 'Dashboard', BASE_URL . '/admin/dashboard.php');
    $adicionarMenu('usuarios', 'Usuários', BASE_URL . '/admin/usuarios.php');
    $adicionarMenu('turmas', 'Turmas', BASE_URL . '/admin/turmas.php');
} elseif ($tipoUsuario === 'professor') {
    $adicionarMenu('dashboard', 'Dashboard', BASE_URL . '/professor/dashboard.php');
    $adicionarMenu('mural', 'Mural', BASE_URL . '/professor/mural.php');
    $adicionarMenu('salas', 'Salas', BASE_URL . '/aluno/salas.php');
    $adicionarMenu('horarios', 'Horários', BASE_URL . '/professor/horarios.php');
    $adicionarMenu('eventos', 'Atividades', BASE_URL . '/professor/eventos.php', true);
    $adicionarMenu('turmas', 'Turmas', BASE_URL . '/professor/turmas.php');
} else {
    $adicionarMenu('mural', 'Mural', BASE_URL . '/aluno/dashboard.php');
    $adicionarMenu('salas', 'Salas', BASE_URL . '/aluno/salas.php');
    $adicionarMenu('horarios', 'Horários', $ehRepresentante ? BASE_URL . '/professor/horarios.php' : BASE_URL . '/aluno/horarios.php');
    $adicionarMenu('disciplinas', 'Disciplinas', BASE_URL . '/aluno/disciplinas.php');
    $adicionarMenu('atividades', 'Atividades', BASE_URL . '/aluno/atividades.php');
    $adicionarMenu('calendario', 'Calendário', BASE_URL . '/aluno/calendario.php');
    if ($ehRepresentante) {
        $adicionarMenu('gerenciar', 'Gerenciar', BASE_URL . '/professor/eventos.php');
        $itensMenu[array_key_last($itensMenu)]['ativo'] = $menuAtivo === 'eventos';
    }
}

$itensBarra = $itensMenu;
if ($tipoUsuario === 'aluno') {
    array_unshift($itensBarra, [
        'id' => 'nova',
        'rotulo' => 'Nova',
        'href' => '',
        'centro' => true,
        'ativo' => false,
        'modal' => 'pessoal',
    ]);
}
$itensBarra[] = [
    'id' => 'sair',
    'rotulo' => 'Sair',
    'href' => BASE_URL . '/logout.php?contexto=' . ($tipoUsuario === 'administrador' ? 'administrador' : 'usuario'),
    'centro' => false,
    'ativo' => false,
];

$centroBarra = null;
$lateraisBarra = [];
foreach ($itensBarra as $itemMenu) {
    if ($itemMenu['centro'] && $centroBarra === null) {
        $centroBarra = $itemMenu;
        continue;
    }
    $lateraisBarra[] = $itemMenu;
}

if (count($lateraisBarra) > 4) {
    $visiveisBarra = array_slice($lateraisBarra, 0, 3);
    $extrasBarra = array_slice($lateraisBarra, 3);
} else {
    $visiveisBarra = $lateraisBarra;
    $extrasBarra = [];
}
$corteBarra = (int) ceil(count($visiveisBarra) / 2);
$barraEsquerda = array_slice($visiveisBarra, 0, $corteBarra);
$barraDireita = array_slice($visiveisBarra, $corteBarra);
$maisAtivo = false;
foreach ($extrasBarra as $extraBarra) {
    if ($extraBarra['ativo']) {
        $maisAtivo = true;
    }
}

if (!function_exists('iconeBarra')) {
    function iconeBarra(string $id): string
    {
        $traco = 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';
        $icones = [
            'mural' => '<rect x="4" y="5" width="16" height="14" rx="2" ' . $traco . '/><path d="M8 9h8M8 13h5" ' . $traco . '/>',
            'salas' => '<circle cx="9" cy="8" r="2.2" ' . $traco . '/><circle cx="16" cy="9.2" r="1.7" ' . $traco . '/><path d="M4.8 18c.5-2.3 2.2-3.4 4.2-3.4s3.7 1.1 4.2 3.4M13.8 14.8c1.1-.5 2.3-.2 3.2.7.8.8 1.1 1.8 1 2.5" ' . $traco . '/>',
            'horarios' => '<circle cx="12" cy="12" r="8" ' . $traco . '/><path d="M12 8v4.5L15 15" ' . $traco . '/>',
            'disciplinas' => '<path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H19v16H7.5A2.5 2.5 0 0 0 5 21.5Z" ' . $traco . '/><path d="M5 5.5A2.5 2.5 0 0 1 7.5 8H19" ' . $traco . '/>',
            'atividades' => '<circle cx="12" cy="12" r="8" ' . $traco . '/><path d="m8.5 12.2 2.5 2.4 4.8-5.2" ' . $traco . '/>',
            'eventos' => '<circle cx="12" cy="12" r="8" ' . $traco . '/><path d="m8.5 12.2 2.5 2.4 4.8-5.2" ' . $traco . '/>',
            'gerenciar' => '<path d="M4 20h4L18 10l-4-4L4 16v4Z" ' . $traco . '/><path d="m13 7 4 4" ' . $traco . '/>',
            'calendario' => '<rect x="4" y="5" width="16" height="15" rx="2" ' . $traco . '/><path d="M8 3.5v3.5M16 3.5v3.5M4 10h16" ' . $traco . '/>',
            'dashboard' => '<rect x="4" y="4" width="7" height="7" rx="1.5" ' . $traco . '/><rect x="13" y="4" width="7" height="7" rx="1.5" ' . $traco . '/><rect x="4" y="13" width="7" height="7" rx="1.5" ' . $traco . '/><rect x="13" y="13" width="7" height="7" rx="1.5" ' . $traco . '/>',
            'usuarios' => '<circle cx="12" cy="8" r="3" ' . $traco . '/><path d="M5.5 19c.8-2.8 2.8-4.2 6.5-4.2s5.7 1.4 6.5 4.2" ' . $traco . '/>',
            'turmas' => '<path d="m4 9 8-4 8 4-8 4-8-4Z" ' . $traco . '/><path d="M8 11v5l4 2 4-2v-5" ' . $traco . '/>',
            'sair' => '<path d="M10 7V5H5v14h5v-2" ' . $traco . '/><path d="M10 12h9M16 8.5 19.5 12 16 15.5" ' . $traco . '/>',
            'nova' => '<path d="M12 5v14M5 12h14" ' . $traco . '/>',
        ];
        $corpo = $icones[$id] ?? $icones['mural'];

        return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $corpo . '</svg>';
    }
}

$linkBarra = static function (array $item, bool $centro = false): string {
    $classe = $centro ? 'app-barra-centro' : 'app-barra-item';
    if ($item['ativo']) {
        $classe .= ' is-active';
    }
    $nome = '<span class="app-barra-nome">' . e($item['rotulo']) . '</span>';
    $icone = iconeBarra($item['id']);
    $miolo = $centro ? '<span class="app-barra-bolha">' . $icone . '</span>' . $nome : $icone . $nome;
    $dados = ' data-barra-item' . ($centro ? ' data-barra-centro' : '');
    if (!empty($item['modal'])) {
        return '<button class="' . $classe . '" type="button" data-modal="' . e($item['modal']) . '"' . $dados . ' aria-label="Nova atividade">' . $miolo . '</button>';
    }
    $atual = $item['ativo'] ? ' aria-current="page"' : '';

    return '<a class="' . $classe . '" href="' . e($item['href']) . '"' . $dados . $atual . '>' . $miolo . '</a>';
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require __DIR__ . '/tema_inicio.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tituloPagina) ?> · Agenda Acadêmica</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700&family=Nunito:wght@700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/lapis.svg" type="image/svg+xml">
    <?php require __DIR__ . '/pwa_head.php'; ?>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/viva.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/social.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/tema.css') ?>">
    <?php if (!empty($usarEditor)): ?>
        <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="app-body">
    <div class="app-shell">
        <aside class="app-sidebar" id="sidebar">
            <a class="brand" href="<?= BASE_URL . destinoPorTipo($tipoUsuario) ?>">
                <span class="brand-mark"><img src="<?= BASE_URL ?>/assets/img/lapis.svg" alt=""></span>
                <span class="brand-text">
                    <strong>Agenda</strong>
                    <small>Acadêmica</small>
                </span>
            </a>

            <nav class="side-nav" aria-label="Menu principal">
                <?php foreach ($itensMenu as $itemMenu): ?>
                    <a class="<?= $itemMenu['ativo'] ? 'is-active' : '' ?>" href="<?= e($itemMenu['href']) ?>"><?= $itemMenu['id'] === 'gerenciar' ? 'Gerenciar atividades' : e($itemMenu['rotulo']) ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if ($usuario): ?>
                <div class="side-user">
                    <span class="side-user-name"><?= e($usuario['nome']) ?></span>
                    <span class="side-user-role"><?= e($ehRepresentante && $tipoUsuario === 'aluno' ? 'Representante de turma' : rotuloPerfil($tipoUsuario)) ?></span>
                    <a class="side-user-out side-user-perfil" href="<?= e($urlPerfil) ?>">Meu perfil e senha</a>
                    <a class="side-user-out" href="<?= BASE_URL ?>/logout.php?contexto=<?= $tipoUsuario === 'administrador' ? 'administrador' : 'usuario' ?>">Sair</a>
                </div>
            <?php endif; ?>
        </aside>

        <div id="barra-fonte" hidden>
            <?php foreach ($itensBarra as $itemMenu): ?>
                <?= $linkBarra($itemMenu, $itemMenu['centro']) ?>
            <?php endforeach; ?>
        </div>

        <nav class="app-barra<?= $centroBarra ? '' : ' is-plana' ?>" aria-label="Navegação do aplicativo">
            <div class="app-barra-lado" data-barra-esq>
                <?php foreach ($barraEsquerda as $itemMenu): ?>
                    <?= $linkBarra($itemMenu) ?>
                <?php endforeach; ?>
            </div>
            <div class="app-barra-meio" data-barra-meio>
                <?php if ($centroBarra): ?>
                    <?= $linkBarra($centroBarra, true) ?>
                <?php endif; ?>
            </div>
            <div class="app-barra-lado" data-barra-dir>
                <?php foreach ($barraDireita as $itemMenu): ?>
                    <?= $linkBarra($itemMenu) ?>
                <?php endforeach; ?>
                <details class="app-mais" data-mais <?= $extrasBarra ? '' : 'hidden' ?>>
                    <summary class="app-barra-item app-barra-mais<?= $maisAtivo ? ' is-active' : '' ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="18" cy="12" r="1.5" fill="currentColor"/></svg>
                        <span class="app-barra-nome">Mais</span>
                    </summary>
                    <div class="app-mais-lista" data-mais-lista>
                        <?php foreach ($extrasBarra as $itemMenu): ?>
                            <?= $linkBarra($itemMenu) ?>
                        <?php endforeach; ?>
                    </div>
                </details>
            </div>
        </nav>

        <div class="app-main">
            <header class="app-topbar">
                <button class="nav-toggle" type="button" data-nav-toggle aria-controls="sidebar" aria-expanded="false">
                    Menu
                </button>
                <h1 class="page-title <?= $menuAtivo === 'dashboard' ? 'is-date' : '' ?>"><?= e($tituloPagina) ?></h1>
                <div class="topbar-tools">
                    <?php require __DIR__ . '/tema_botao.php'; ?>
                    <?php if ($usuario): ?>
                        <a class="topbar-perfil" href="<?= e($urlPerfil) ?>" aria-label="Meu perfil" title="Meu perfil"><?= avatarSocial((int) $usuario['id'], (string) $usuario['nome'], 'sm') ?></a>
                    <?php endif; ?>
                    <?php if ($tipoUsuario === 'aluno'): ?>
                        <a class="notif-bell<?= $notificacoesNaoLidas ? ' has-alert' : '' ?>" href="<?= BASE_URL ?>/aluno/notificacoes.php" aria-label="Notificações<?= $notificacoesNaoLidas ? ', ' . $notificacoesNaoLidas . ' não lidas' : '' ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 20a2 2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            <?php if ($notificacoesNaoLidas): ?>
                                <span class="notif-count"><?= $notificacoesNaoLidas > 9 ? '9+' : $notificacoesNaoLidas ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($acaoTopo)): ?>
                        <div class="topbar-action"><?= $acaoTopo ?></div>
                    <?php endif; ?>
                </div>
            </header>
            <main class="app-content">
                <?php if ($usuario && $menuAtivo !== 'perfil' && senhaProvisoriaAtiva((int) $usuario['id'])): ?>
                    <div class="alert alert-aviso senha-aviso" role="status">
                        Você está usando uma senha provisória. <a href="<?= e($urlPerfil) ?>#senha">Crie a sua senha agora</a>.
                    </div>
                <?php endif; ?>
                <div class="nav-backdrop" data-nav-close hidden></div>
