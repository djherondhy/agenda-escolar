<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

$perfilAcao = BASE_URL . '/admin/perfil.php';
require __DIR__ . '/../includes/perfil_pagina.php';
