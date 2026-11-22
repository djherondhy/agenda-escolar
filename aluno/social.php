<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feed_render.php';

exigirEscola();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/aluno/dashboard.php');
}

$pdo = getPDO();
$usuarioId = (int) $_SESSION['usuario_id'];
$tipo = (string) $_SESSION['usuario_tipo'];
$acao = $_POST['acao'] ?? '';
$contexto = $_POST['contexto'] ?? '';
$alvo = (int) ($_POST['alvo'] ?? 0);
$padrao = $tipo === 'professor' ? '/professor/mural.php' : '/aluno/dashboard.php';
$voltar = destinoVoltarEscola($_POST['voltar'] ?? '', $padrao);

$ajax = ($_SERVER['HTTP_X_CE_AJAX'] ?? '') === '1';

$redirecionarCom = static function (string $url, string $chave): void {
    $separador = str_contains($url, '?') ? '&' : '?';
    header('Location: ' . $url . $separador . $chave);
    exit;
};

$responder = static function (bool $ok, array $dados = [], string $redirect = 'ok=1', ?string $url = null) use ($ajax, $redirecionarCom, $voltar): void {
    if (!$ajax) {
        $redirecionarCom($url ?? $voltar, $redirect);
    }

    http_response_code($ok ? 200 : 422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok] + $dados, JSON_UNESCAPED_UNICODE);
    exit;
};

$ctxSocial = [
    'contexto'          => $contexto,
    'alvo'              => $alvo,
    'voltar'            => $voltar,
    'usuarioId'         => $usuarioId,
    'usuarioNome'       => (string) ($_SESSION['usuario_nome'] ?? ''),
    'podePublicar'      => true,
    'moderaPosts'       => false,
    'moderaComentarios' => false,
];

if ($acao === 'criar_sala') {
    $titulo = textoComunidade($_POST['titulo'] ?? '', 150);
    $tema = textoComunidade($_POST['tema'] ?? '', 150);
    $descricao = textoComunidade($_POST['descricao'] ?? '', 1000);
    $icone = iconeSalaValido((string) ($_POST['icone'] ?? ''));
    if ($titulo === '') {
        $responder(false, ['erro' => 'Dê um nome para a sala antes de criar.'], 'erro=sala', BASE_URL . '/aluno/salas.php');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO salas_estudo (titulo, tema, descricao, icone, criador_id)
         VALUES (:titulo, :tema, :descricao, :icone, :criador)'
    );
    $stmt->execute([
        'titulo'    => $titulo,
        'tema'      => $tema,
        'descricao' => $descricao,
        'icone'     => $icone,
        'criador'   => $usuarioId,
    ]);
    $salaId = (int) $pdo->lastInsertId();
    entrarNaSala($pdo, $salaId, $usuarioId, true);
    $salaNova = [
        'id'           => $salaId,
        'titulo'       => $titulo,
        'tema'         => $tema,
        'descricao'    => $descricao,
        'icone'        => $icone,
        'inscrito'     => true,
        'inscritos'    => 1,
        'criador_nome' => (string) ($_SESSION['usuario_nome'] ?? ''),
        'membros'      => [[
            'id'   => $usuarioId,
            'nome' => (string) ($_SESSION['usuario_nome'] ?? ''),
        ]],
    ];
    $responder(true, [
        'acao' => 'criar_sala',
        'html' => capturarRender(static function () use ($salaNova): void {
            renderCartaoSala($salaNova);
        }),
    ], 'ok=sala', BASE_URL . '/aluno/sala.php?id=' . $salaId);
}

if ($acao === 'inscrever') {
    $sala = obterSala($pdo, $alvo);
    if (!$sala) {
        $responder(false, ['erro' => 'Esta sala não está mais disponível.'], 'erro=sala', BASE_URL . '/aluno/salas.php');
    }
    $virouAdmin = entrarNaSala($pdo, $alvo, $usuarioId, false);
    $sala['inscrito'] = true;
    $sala['membros'] = listarMembrosSala($pdo, $alvo, 4);
    $conta = $pdo->prepare('SELECT COUNT(*) FROM salas_inscricoes WHERE sala_id = :sala');
    $conta->execute(['sala' => $alvo]);
    $sala['inscritos'] = (int) $conta->fetchColumn();
    if ($virouAdmin && ($sala['criador_nome'] ?? '') === '') {
        $sala['criador_nome'] = (string) ($_SESSION['usuario_nome'] ?? '');
    }
    $feedInscricao = capturarRender(static function () use ($pdo, $alvo, $usuarioId, $virouAdmin): void {
        $feedPosts = listarPostsComunitarios($pdo, 'sala', $alvo);
        $feedPodePublicar = true;
        $feedUsuarioId = $usuarioId;
        $feedModeraPosts = $virouAdmin;
        $feedModeraComentarios = $virouAdmin;
        $feedContexto = 'sala';
        $feedAlvo = $alvo;
        $feedVoltar = BASE_URL . '/aluno/sala.php?id=' . $alvo;
        require __DIR__ . '/../includes/feed_social.php';
    });
    $status = '<span class="grupo-status"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.6 9.2 16.8 19 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        . ($virouAdmin ? 'Você administra esta sala' : 'Você participa') . '</span>';
    $responder(true, [
        'acao'   => 'inscrever',
        'html'   => capturarRender(static function () use ($sala): void {
            renderCartaoSala($sala);
        }),
        'feed'   => $feedInscricao,
        'status' => $status,
    ], $virouAdmin ? 'ok=admin' : 'ok=inscrito', BASE_URL . '/aluno/sala.php?id=' . $alvo);
}

if (in_array($acao, ['editar_sala', 'adicionar_membro', 'remover_membro', 'tornar_admin', 'remover_admin', 'excluir_sala'], true)) {
    $sala = obterSala($pdo, $alvo);
    $destino = BASE_URL . '/aluno/sala.php?id=' . $alvo . '&aba=config';
    if (!$sala || !usuarioAdministraSala($pdo, $alvo, $usuarioId)) {
        $responder(false, ['erro' => 'Você não pode alterar esta sala.'], 'erro=acesso', BASE_URL . '/aluno/salas.php');
    }

    if ($acao === 'editar_sala') {
        $titulo = textoComunidade($_POST['titulo'] ?? '', 150);
        $tema = textoComunidade($_POST['tema'] ?? '', 150);
        $descricao = textoComunidade($_POST['descricao'] ?? '', 1000);
        $icone = iconeSalaValido((string) ($_POST['icone'] ?? ''));
        if ($titulo === '') {
            $responder(false, ['erro' => 'Dê um nome para a sala.'], 'erro=titulo', $destino);
        }
        $stmt = $pdo->prepare(
            'UPDATE salas_estudo SET titulo = :titulo, tema = :tema, descricao = :descricao, icone = :icone WHERE id = :id'
        );
        $stmt->execute([
            'titulo'    => $titulo,
            'tema'      => $tema,
            'descricao' => $descricao,
            'icone'     => $icone,
            'id'        => $alvo,
        ]);
        $responder(true, [], 'ok=editada', $destino);
    }

    if ($acao === 'adicionar_membro') {
        $membroId = (int) ($_POST['membro_id'] ?? 0);
        $stmt = $pdo->prepare(
            'SELECT id FROM usuarios WHERE id = :id AND tipo IN (\'aluno\', \'professor\') LIMIT 1'
        );
        $stmt->execute(['id' => $membroId]);
        if (!$stmt->fetchColumn()) {
            $responder(false, ['erro' => 'Escolha um aluno para adicionar.'], 'erro=membro', $destino);
        }
        $comoAdmin = ($_POST['como_admin'] ?? '') === '1';
        entrarNaSala($pdo, $alvo, $membroId, $comoAdmin);
        $responder(true, [], $comoAdmin ? 'ok=admin_novo' : 'ok=membro', $destino);
    }

    if ($acao === 'tornar_admin' || $acao === 'remover_admin') {
        $membroId = (int) ($_POST['membro_id'] ?? 0);
        $promover = $acao === 'tornar_admin';
        if (!definirAdminDaSala($pdo, $alvo, $membroId, $promover)) {
            $responder(false, ['erro' => 'A sala precisa de pelo menos um administrador.'], 'erro=ultimo', $destino);
        }
        $responder(true, [], $promover ? 'ok=admin_promovido' : 'ok=admin_removido', $destino);
    }

    if ($acao === 'remover_membro') {
        $membroId = (int) ($_POST['membro_id'] ?? 0);
        if ($membroId === $usuarioId) {
            $responder(false, ['erro' => 'Peça a outro administrador para ajustar o seu acesso.'], 'erro=criador', $destino);
        }
        if (usuarioAdministraSala($pdo, $alvo, $membroId) && !salaTemOutroAdmin($pdo, $alvo, $membroId)) {
            $responder(false, ['erro' => 'A sala precisa de pelo menos um administrador.'], 'erro=ultimo', $destino);
        }
        $stmt = $pdo->prepare('DELETE FROM salas_inscricoes WHERE sala_id = :sala AND usuario_id = :usuario');
        $stmt->execute(['sala' => $alvo, 'usuario' => $membroId]);
        $responder(true, [], 'ok=membro_removido', $destino);
    }

    $posts = $pdo->prepare('SELECT id FROM salas_posts WHERE sala_id = :sala');
    $posts->execute(['sala' => $alvo]);
    foreach ($posts->fetchAll() as $post) {
        apagarPostComunitario($pdo, 'sala', (int) $post['id']);
    }
    $apagar = $pdo->prepare('DELETE FROM salas_estudo WHERE id = :id');
    $apagar->execute(['id' => $alvo]);
    $responder(true, [], 'ok=excluida', BASE_URL . '/aluno/salas.php');
}

$permitido = $contexto === 'mural'
    ? podeAcessarMural($pdo, $usuarioId, $tipo, $alvo)
    : ($contexto === 'sala' && obterSala($pdo, $alvo) && usuarioInscritoNaSala($pdo, $alvo, $usuarioId));

if (!$permitido) {
    $responder(false, ['erro' => 'Você não pode publicar aqui.'], 'erro=acesso');
}

$ctxSocial['moderaPosts'] = podeModerarComunidade($pdo, $contexto, $alvo, $usuarioId, $tipo);
$ctxSocial['moderaComentarios'] = $ctxSocial['moderaPosts'];

if ($acao === 'publicar') {
    $texto = textoComunidade($_POST['texto'] ?? '', 4000);
    if ($texto === '') {
        $responder(false, ['erro' => 'Escreva um texto para publicar.'], 'erro=texto');
    }

    if ($contexto === 'mural') {
        $stmt = $pdo->prepare('INSERT INTO mural_posts (turma_id, autor_id, texto) VALUES (:alvo, :autor, :texto)');
        $tabela = 'mural_anexos';
        $pasta = 'mural';
    } else {
        $stmt = $pdo->prepare('INSERT INTO salas_posts (sala_id, autor_id, texto) VALUES (:alvo, :autor, :texto)');
        $tabela = 'salas_anexos';
        $pasta = 'salas';
    }

    $stmt->execute([
        'alvo'  => $alvo,
        'autor' => $usuarioId,
        'texto' => $texto,
    ]);
    $postId = (int) $pdo->lastInsertId();
    $erros = [];
    salvarAnexosNaTabela($pdo, $tabela, 'post_id', $postId, $pasta . '/' . $postId, $_FILES['anexos'] ?? [], $erros);
    try {
        require_once __DIR__ . '/../includes/avisos.php';
        avisarNovaPublicacao($pdo, $contexto, $alvo, $usuarioId, (string) ($_SESSION['usuario_nome'] ?? ''), $texto);
    } catch (Throwable $e) {
        // A publicação permanece mesmo se o aviso falhar.
    }
    $post = autorSocial($pdo, $usuarioId) + [
        'id'           => $postId,
        'texto'        => $texto,
        'data_criacao' => date('Y-m-d H:i:s'),
        'anexos'       => listarAnexosDaLinha($pdo, $tabela, 'post_id', $postId),
        'comentarios'  => [],
    ];
    $responder(true, [
        'acao'  => 'publicar',
        'html'  => capturarRender(static function () use ($post, $ctxSocial): void {
            renderPostSocial($post, $ctxSocial);
        }),
        'aviso' => $erros ? 'A publicação foi salva, mas um arquivo não foi aceito. Use imagem, PDF, Word ou slides de até 5 MB.' : '',
    ], $erros ? 'aviso=anexo' : 'ok=publicado');
}

if ($acao === 'comentar') {
    $texto = textoComunidade($_POST['texto'] ?? '', 1000);
    $postId = (int) ($_POST['post_id'] ?? 0);
    $post = buscarPostComunitario($pdo, $contexto, $postId, $alvo);
    if ($texto === '' || !$post) {
        $responder(false, ['erro' => 'Escreva um comentário.'], 'erro=texto');
    }

    $tabela = $contexto === 'mural' ? 'mural_comentarios' : 'salas_comentarios';
    $stmt = $pdo->prepare("INSERT INTO {$tabela} (post_id, autor_id, texto) VALUES (:post, :autor, :texto)");
    $stmt->execute([
        'post'  => $postId,
        'autor' => $usuarioId,
        'texto' => $texto,
    ]);
    $comentarioIdNovo = (int) $pdo->lastInsertId();
    try {
        require_once __DIR__ . '/../includes/avisos.php';
        avisarNovoComentario($pdo, $contexto, $alvo, $usuarioId, (string) ($_SESSION['usuario_nome'] ?? ''), $texto);
    } catch (Throwable $e) {
        // O comentário permanece mesmo se o aviso falhar.
    }
    $comentario = autorSocial($pdo, $usuarioId) + [
        'id'           => $comentarioIdNovo,
        'texto'        => $texto,
        'data_criacao' => date('Y-m-d H:i:s'),
    ];
    $responder(true, [
        'acao'    => 'comentar',
        'post_id' => $postId,
        'html'    => capturarRender(static function () use ($comentario, $ctxSocial): void {
            renderComentarioSocial($comentario, $ctxSocial);
        }),
    ], 'ok=comentario');
}

if ($acao === 'apagar_post') {
    $postId = (int) ($_POST['post_id'] ?? 0);
    $post = buscarPostComunitario($pdo, $contexto, $postId, $alvo);
    $modera = podeModerarComunidade($pdo, $contexto, $alvo, $usuarioId, $tipo);
    if (!$post || ((int) $post['autor_id'] !== $usuarioId && !$modera)) {
        $responder(false, ['erro' => 'Você não pode apagar esta publicação.'], 'erro=acesso');
    }
    apagarPostComunitario($pdo, $contexto, $postId);
    $responder(true, [
        'acao'    => 'apagar_post',
        'post_id' => $postId,
    ], 'ok=apagado');
}

if ($acao === 'apagar_comentario') {
    $comentarioId = (int) ($_POST['comentario_id'] ?? 0);
    $comentario = buscarComentarioComunitario($pdo, $contexto, $comentarioId, $alvo);
    $modera = podeModerarComunidade($pdo, $contexto, $alvo, $usuarioId, $tipo);
    if (!$comentario || ((int) $comentario['autor_id'] !== $usuarioId && !$modera)) {
        $responder(false, ['erro' => 'Você não pode apagar este comentário.'], 'erro=acesso');
    }
    $tabela = $contexto === 'mural' ? 'mural_comentarios' : 'salas_comentarios';
    $stmt = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
    $stmt->execute(['id' => $comentarioId]);
    $responder(true, [
        'acao' => 'apagar_comentario',
    ], 'ok=apagado');
}

$responder(false, ['erro' => 'Não foi possível concluir esta ação.'], 'erro=acao');

function buscarPostComunitario(PDO $pdo, string $contexto, int $postId, int $alvo): ?array
{
    if ($postId <= 0) {
        return null;
    }

    if ($contexto === 'mural') {
        $stmt = $pdo->prepare('SELECT id, autor_id FROM mural_posts WHERE id = :id AND turma_id = :alvo LIMIT 1');
    } else {
        $stmt = $pdo->prepare('SELECT id, autor_id FROM salas_posts WHERE id = :id AND sala_id = :alvo LIMIT 1');
    }
    $stmt->execute(['id' => $postId, 'alvo' => $alvo]);
    $post = $stmt->fetch();

    return $post ?: null;
}

function buscarComentarioComunitario(PDO $pdo, string $contexto, int $comentarioId, int $alvo): ?array
{
    if ($comentarioId <= 0) {
        return null;
    }

    if ($contexto === 'mural') {
        $sql = 'SELECT c.id, c.autor_id
                FROM mural_comentarios c
                INNER JOIN mural_posts p ON p.id = c.post_id
                WHERE c.id = :id AND p.turma_id = :alvo
                LIMIT 1';
    } else {
        $sql = 'SELECT c.id, c.autor_id
                FROM salas_comentarios c
                INNER JOIN salas_posts p ON p.id = c.post_id
                WHERE c.id = :id AND p.sala_id = :alvo
                LIMIT 1';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $comentarioId, 'alvo' => $alvo]);
    $comentario = $stmt->fetch();

    return $comentario ?: null;
}

function salaCriadaPeloUsuario(PDO $pdo, int $salaId, int $usuarioId): bool
{
    $sala = obterSala($pdo, $salaId);

    return $sala && usuarioAdministraSala($pdo, $salaId, $usuarioId);
}
