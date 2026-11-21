<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirEscola();

$perfilAcao = BASE_URL . '/aluno/perfil.php';
require __DIR__ . '/../includes/perfil_pagina.php';
