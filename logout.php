<?php

require_once __DIR__ . '/includes/auth.php';

$contexto = $_GET['contexto'] ?? 'usuario';
iniciarSessaoNome($contexto === 'administrador' ? SESSAO_ADMIN : SESSAO_USER);
encerrarSessao();
redirecionar('/login.php');
