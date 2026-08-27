<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/admin/usuarios.php');
}

$pdo = getPDO();
$usuarioId = (int) ($_POST['id'] ?? 0);

if ($usuarioId === (int) $_SESSION['usuario_id']) {
    redirecionar('/admin/usuarios.php');
}

$stmt = $pdo->prepare('SELECT id, tipo FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $usuarioId]);
$usuario = $stmt->fetch();

if ($usuario) {
    if ($usuario['tipo'] === 'administrador' && contarAdministradores($pdo) <= 1) {
        redirecionar('/admin/usuarios.php');
    }

    $delete = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
    $delete->execute(['id' => $usuarioId]);
}

redirecionar('/admin/usuarios.php?excluido=1');
