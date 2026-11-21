<?php

function garantirComunidade(PDO $pdo): void
{
    try {
        $pdo->exec(
        'CREATE TABLE IF NOT EXISTS aluno_atividade_anexos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            atividade_id INT NOT NULL,
            nome_original VARCHAR(255) NOT NULL,
            caminho VARCHAR(255) NOT NULL,
            tipo_mime VARCHAR(120) NOT NULL,
            tamanho INT NOT NULL DEFAULT 0,
            FOREIGN KEY (atividade_id) REFERENCES aluno_atividades(id) ON DELETE CASCADE
        )'
        );
    } catch (Throwable $e) {
        // A atividade pessoal continua mesmo se a tabela de anexos ainda não puder ser criada.
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS mural_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            turma_id INT NOT NULL,
            autor_id INT NOT NULL,
            texto TEXT NOT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
            FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS mural_anexos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            nome_original VARCHAR(255) NOT NULL,
            caminho VARCHAR(255) NOT NULL,
            tipo_mime VARCHAR(120) NOT NULL,
            tamanho INT NOT NULL DEFAULT 0,
            FOREIGN KEY (post_id) REFERENCES mural_posts(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS mural_comentarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            autor_id INT NOT NULL,
            texto TEXT NOT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES mural_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS salas_estudo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(150) NOT NULL,
            tema VARCHAR(150) NOT NULL DEFAULT \'\',
            descricao TEXT,
            criador_id INT NOT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (criador_id) REFERENCES usuarios(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS salas_inscricoes (
            sala_id INT NOT NULL,
            usuario_id INT NOT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (sala_id, usuario_id),
            FOREIGN KEY (sala_id) REFERENCES salas_estudo(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS salas_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sala_id INT NOT NULL,
            autor_id INT NOT NULL,
            texto TEXT NOT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sala_id) REFERENCES salas_estudo(id) ON DELETE CASCADE,
            FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS salas_anexos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            nome_original VARCHAR(255) NOT NULL,
            caminho VARCHAR(255) NOT NULL,
            tipo_mime VARCHAR(120) NOT NULL,
            tamanho INT NOT NULL DEFAULT 0,
            FOREIGN KEY (post_id) REFERENCES salas_posts(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS salas_comentarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            autor_id INT NOT NULL,
            texto TEXT NOT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES salas_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
        )'
    );

}

function destinoVoltarEscola(?string $voltar, string $padrao): string
{
    $padraoUrl = str_starts_with($padrao, BASE_URL) ? $padrao : BASE_URL . $padrao;
    if (!$voltar) {
        return $padraoUrl;
    }

    $caminho = parse_url($voltar, PHP_URL_PATH) ?: '';
    $permitido = str_starts_with($caminho, BASE_URL . '/aluno/') || str_starts_with($caminho, BASE_URL . '/professor/');

    return $permitido ? $voltar : $padraoUrl;
}

function podeAcessarMural(PDO $pdo, int $usuarioId, string $tipo, int $turmaId): bool
{
    if ($turmaId <= 0) {
        return false;
    }

    if ($tipo === 'aluno') {
        $aluno = obterAlunoPorUsuario($pdo, $usuarioId);
        return $aluno && (int) $aluno['turma_id'] === $turmaId;
    }

    if ($tipo === 'professor') {
        $professor = obterProfessorPorUsuario($pdo, $usuarioId);
        return $professor && professorTemTurma($pdo, (int) $professor['id'], $turmaId);
    }

    return false;
}

/**
 * Representante da turma modera o mural da própria turma (posts e comentários).
 * Nas salas, quem modera é o criador da sala.
 */
function podeModerarComunidade(PDO $pdo, string $contexto, int $alvo, int $usuarioId, string $tipo): bool
{
    if ($contexto === 'mural') {
        return ehRepresentantePorUsuario($pdo, $usuarioId, $tipo) && podeAcessarMural($pdo, $usuarioId, $tipo, $alvo);
    }

    $sala = obterSala($pdo, $alvo);

    return $sala !== null && (int) $sala['criador_id'] === $usuarioId;
}

function listarPostsComunitarios(PDO $pdo, string $contexto, int $alvoId): array
{
    $autor = "u.nome AS autor_nome, CASE WHEN a.representante = 1 THEN 'professor' ELSE u.tipo END AS autor_tipo, t.nome AS autor_turma";
    $juntarAutor = 'INNER JOIN usuarios u ON u.id = p.autor_id
                LEFT JOIN alunos a ON a.usuario_id = u.id
                LEFT JOIN turmas t ON t.id = a.turma_id';

    if ($contexto === 'mural') {
        $sql = "SELECT p.id, p.autor_id, p.texto, p.data_criacao, {$autor}
                FROM mural_posts p
                {$juntarAutor}
                WHERE p.turma_id = :alvo
                ORDER BY p.id DESC";
        $tabelaAnexos = 'mural_anexos';
        $tabelaComentarios = 'mural_comentarios';
    } else {
        $sql = "SELECT p.id, p.autor_id, p.texto, p.data_criacao, {$autor}
                FROM salas_posts p
                {$juntarAutor}
                WHERE p.sala_id = :alvo
                ORDER BY p.id DESC";
        $tabelaAnexos = 'salas_anexos';
        $tabelaComentarios = 'salas_comentarios';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['alvo' => $alvoId]);
    $posts = $stmt->fetchAll();
    if (!$posts) {
        return [];
    }

    $ids = array_map(static fn (array $post): int => (int) $post['id'], $posts);
    $lista = implode(',', $ids);

    $anexos = $pdo->query("SELECT * FROM {$tabelaAnexos} WHERE post_id IN ({$lista}) ORDER BY id")->fetchAll();
    $comentarios = $pdo->query(
        "SELECT c.id, c.post_id, c.autor_id, c.texto, c.data_criacao, {$autor}
         FROM {$tabelaComentarios} c
         INNER JOIN usuarios u ON u.id = c.autor_id
         LEFT JOIN alunos a ON a.usuario_id = u.id
         LEFT JOIN turmas t ON t.id = a.turma_id
         WHERE c.post_id IN ({$lista})
         ORDER BY c.id"
    )->fetchAll();

    $porPost = [];
    foreach ($posts as $post) {
        $post['anexos'] = [];
        $post['comentarios'] = [];
        $porPost[(int) $post['id']] = $post;
    }
    foreach ($anexos as $anexo) {
        $porPost[(int) $anexo['post_id']]['anexos'][] = $anexo;
    }
    foreach ($comentarios as $comentario) {
        $porPost[(int) $comentario['post_id']]['comentarios'][] = $comentario;
    }

    return array_values($porPost);
}

function obterSala(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, u.nome AS criador_nome
         FROM salas_estudo s
         INNER JOIN usuarios u ON u.id = s.criador_id
         WHERE s.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $sala = $stmt->fetch();

    return $sala ?: null;
}

function usuarioInscritoNaSala(PDO $pdo, int $salaId, int $usuarioId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM salas_inscricoes WHERE sala_id = :sala AND usuario_id = :usuario LIMIT 1'
    );
    $stmt->execute([
        'sala'    => $salaId,
        'usuario' => $usuarioId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function listarSalas(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, u.nome AS criador_nome,
                (SELECT COUNT(*) FROM salas_inscricoes i WHERE i.sala_id = s.id) AS inscritos,
                EXISTS(
                    SELECT 1 FROM salas_inscricoes i
                    WHERE i.sala_id = s.id AND i.usuario_id = :usuario
                ) AS inscrito
         FROM salas_estudo s
         INNER JOIN usuarios u ON u.id = s.criador_id
         ORDER BY s.id DESC'
    );
    $stmt->execute(['usuario' => $usuarioId]);

    return $stmt->fetchAll();
}

function apagarPostComunitario(PDO $pdo, string $contexto, int $postId): void
{
    $anexos = $contexto === 'mural' ? 'mural_anexos' : 'salas_anexos';
    $posts = $contexto === 'mural' ? 'mural_posts' : 'salas_posts';

    foreach (listarAnexosDaLinha($pdo, $anexos, 'post_id', $postId) as $anexo) {
        apagarArquivoUpload($anexo['caminho']);
    }

    $stmt = $pdo->prepare("DELETE FROM {$posts} WHERE id = :id");
    $stmt->execute(['id' => $postId]);
}

function listarMembrosSala(PDO $pdo, int $salaId, int $limite = 8): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nome, u.tipo, t.nome AS turma
         FROM salas_inscricoes i
         INNER JOIN usuarios u ON u.id = i.usuario_id
         LEFT JOIN alunos a ON a.usuario_id = u.id
         LEFT JOIN turmas t ON t.id = a.turma_id
         WHERE i.sala_id = :sala
         ORDER BY i.data_criacao ASC
         LIMIT ' . max(1, $limite)
    );
    $stmt->execute(['sala' => $salaId]);

    return $stmt->fetchAll();
}

function iniciaisNome(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $primeira = $partes[0] ?? '';
    $ultima = count($partes) > 1 ? $partes[count($partes) - 1] : '';

    return mb_strtoupper(mb_substr($primeira, 0, 1) . mb_substr($ultima, 0, 1));
}

function avatarSocial(int $id, string $nome, string $tamanho = ''): string
{
    $classe = 'avatar av-' . (($id % 6) + 1) . ($tamanho !== '' ? ' avatar-' . $tamanho : '');

    $foto = fotoUsuario($id);
    if ($foto !== null) {
        return '<span class="' . $classe . ' avatar-foto" aria-hidden="true"><img src="' . e(urlFotoPerfil($foto)) . '" alt="" loading="lazy"></span>';
    }

    return '<span class="' . $classe . '" aria-hidden="true">' . e(iniciaisNome($nome)) . '</span>';
}

function descricaoAutor(?string $tipo, ?string $turma): string
{
    if ($tipo === 'professor') {
        return 'Representante de turma';
    }
    if ($tipo === 'administrador') {
        return 'Desenvolvedor / Conselheiro';
    }

    return $turma ? 'Aluno · ' . $turma : 'Aluno';
}

function tempoRelativo(string $data): string
{
    $momento = strtotime($data);
    if (!$momento) {
        return '';
    }

    $segundos = time() - $momento;
    if ($segundos < 60) {
        return 'agora';
    }
    if ($segundos < 3600) {
        return 'há ' . (int) floor($segundos / 60) . ' min';
    }
    if ($segundos < 86400) {
        return 'há ' . (int) floor($segundos / 3600) . ' h';
    }
    $dias = (int) floor($segundos / 86400);
    if ($dias === 1) {
        return 'ontem';
    }
    if ($dias < 7) {
        return 'há ' . $dias . ' dias';
    }

    return date('d/m/Y', $momento);
}

function tipoArquivoSocial(string $nome): array
{
    $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    $mapa = [
        'pdf'  => ['pdf', 'PDF'],
        'doc'  => ['doc', 'Word'],
        'docx' => ['doc', 'Word'],
        'ppt'  => ['slide', 'Slides'],
        'pptx' => ['slide', 'Slides'],
        'xls'  => ['planilha', 'Planilha'],
        'xlsx' => ['planilha', 'Planilha'],
        'txt'  => ['texto', 'Texto'],
        'zip'  => ['zip', 'ZIP'],
    ];

    return $mapa[$extensao] ?? ['texto', strtoupper($extensao ?: 'Arquivo')];
}

function tamanhoLegivel(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return (int) round($bytes / 1024) . ' KB';
    }

    return $bytes . ' B';
}

function capaSala(int $id): string
{
    return 'capa-' . (($id % 5) + 1);
}

function autorSocial(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        "SELECT u.nome, CASE WHEN a.representante = 1 THEN 'professor' ELSE u.tipo END AS tipo, t.nome AS turma
         FROM usuarios u
         LEFT JOIN alunos a ON a.usuario_id = u.id
         LEFT JOIN turmas t ON t.id = a.turma_id
         WHERE u.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $usuarioId]);
    $linha = $stmt->fetch() ?: ['nome' => '', 'tipo' => 'aluno', 'turma' => null];

    return [
        'autor_id'    => $usuarioId,
        'autor_nome'  => $linha['nome'],
        'autor_tipo'  => $linha['tipo'],
        'autor_turma' => $linha['turma'],
    ];
}

function textoComunidade(string $texto, int $maximo): string
{
    $texto = trim($texto);
    if (mb_strlen($texto) > $maximo) {
        $texto = mb_substr($texto, 0, $maximo);
    }

    return $texto;
}
