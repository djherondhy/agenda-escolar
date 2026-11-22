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
            icone VARCHAR(30) NOT NULL DEFAULT \'\',
            exemplo TINYINT(1) NOT NULL DEFAULT 0,
            criador_id INT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (criador_id) REFERENCES usuarios(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS salas_inscricoes (
            sala_id INT NOT NULL,
            usuario_id INT NOT NULL,
            admin TINYINT(1) NOT NULL DEFAULT 0,
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

    try {
        $temIcone = $pdo->query("SHOW COLUMNS FROM salas_estudo LIKE 'icone'")->fetch();
        if (!$temIcone) {
            $pdo->exec("ALTER TABLE salas_estudo ADD COLUMN icone VARCHAR(30) NOT NULL DEFAULT '' AFTER descricao");
        }
        $temExemplo = $pdo->query("SHOW COLUMNS FROM salas_estudo LIKE 'exemplo'")->fetch();
        if (!$temExemplo) {
            $pdo->exec('ALTER TABLE salas_estudo ADD COLUMN exemplo TINYINT(1) NOT NULL DEFAULT 0');
        }
        $pdo->exec('ALTER TABLE salas_estudo MODIFY criador_id INT NULL');
        $temAdmin = $pdo->query("SHOW COLUMNS FROM salas_inscricoes LIKE 'admin'")->fetch();
        if (!$temAdmin) {
            $pdo->exec('ALTER TABLE salas_inscricoes ADD COLUMN admin TINYINT(1) NOT NULL DEFAULT 0');
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_marcadores (
                chave VARCHAR(60) PRIMARY KEY
            )'
        );
        $herdou = $pdo->query("SELECT 1 FROM app_marcadores WHERE chave = 'admins_criador_v1'")->fetch();
        if (!$herdou) {
            $pdo->exec(
                'UPDATE salas_inscricoes i
                 INNER JOIN salas_estudo s ON s.id = i.sala_id AND s.criador_id = i.usuario_id
                 SET i.admin = 1
                 WHERE i.admin = 0'
            );
            $pdo->exec("INSERT INTO app_marcadores (chave) VALUES ('admins_criador_v1')");
        }
        garantirSalasExemplo($pdo);
    } catch (Throwable $e) {
        // A sala continua utilizável se o ajuste de administrador ainda não puder ser aplicado.
    }

}

function garantirSalasExemplo(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_marcadores (
            chave VARCHAR(60) PRIMARY KEY
        )'
    );
    $marcada = $pdo->query("SELECT 1 FROM app_marcadores WHERE chave = 'salas_exemplo_v1'")->fetch();
    if ($marcada) {
        return;
    }

    $salas = [
        ['Matemática', 'Matemática', 'Revisão de funções, equações, geometria e questões de prova. Aberta para todas as turmas.', 'calculo'],
        ['Português e Redação', 'Língua Portuguesa', 'Interpretação de texto, gramática e produção da redação.', 'lapis'],
        ['Programação', 'Informática', 'Lógica, PHP, banco de dados e os projetos das turmas técnicas.', 'codigo'],
        ['Ciências da Natureza', 'Ciências', 'Biologia, química e física do ensino médio integrado.', 'ciencia'],
        ['História e Geografia', 'Humanas', 'Conteúdos de história, geografia e atualidades.', 'globo'],
        ['Inglês', 'Língua Inglesa', 'Vocabulário, leitura e prática de conversação.', 'conversa'],
        ['Administração', 'Administração', 'Gestão, marketing e empreendedorismo do curso técnico.', 'estrela'],
        ['Agropecuária', 'Agropecuária', 'Produção animal, vegetal e as práticas de campo.', 'ideia'],
        ['Informática', 'Informática', 'Redes, sistemas, hardware e banco de dados.', 'codigo'],
        ['Dúvidas da semana', 'Estudo livre', 'Traga a dúvida de qualquer matéria. Quem entra pode responder e publicar material.', 'trofeu'],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO salas_estudo (titulo, tema, descricao, icone, criador_id, exemplo)
         VALUES (:titulo, :tema, :descricao, :icone, NULL, 1)'
    );
    foreach ($salas as [$titulo, $tema, $descricao, $icone]) {
        $existe = $pdo->prepare('SELECT 1 FROM salas_estudo WHERE titulo = :titulo AND exemplo = 1 LIMIT 1');
        $existe->execute(['titulo' => $titulo]);
        if ($existe->fetchColumn()) {
            continue;
        }
        $stmt->execute([
            'titulo'    => $titulo,
            'tema'      => $tema,
            'descricao' => $descricao,
            'icone'     => $icone,
        ]);
    }
    $pdo->exec("INSERT INTO app_marcadores (chave) VALUES ('salas_exemplo_v1')");
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
 * Nas salas, quem modera é um administrador da sala.
 */
function podeModerarComunidade(PDO $pdo, string $contexto, int $alvo, int $usuarioId, string $tipo): bool
{
    if ($contexto === 'mural') {
        return ehRepresentantePorUsuario($pdo, $usuarioId, $tipo) && podeAcessarMural($pdo, $usuarioId, $tipo, $alvo);
    }

    return usuarioAdministraSala($pdo, $alvo, $usuarioId);
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
        'SELECT s.*, COALESCE(u.nome, \'\') AS criador_nome
         FROM salas_estudo s
         LEFT JOIN usuarios u ON u.id = s.criador_id
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

function usuarioAdministraSala(PDO $pdo, int $salaId, int $usuarioId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM salas_inscricoes WHERE sala_id = :sala AND usuario_id = :usuario AND admin = 1 LIMIT 1'
    );
    $stmt->execute([
        'sala'    => $salaId,
        'usuario' => $usuarioId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function entrarNaSala(PDO $pdo, int $salaId, int $usuarioId, bool $comoAdmin = false): bool
{
    $pdo->beginTransaction();
    try {
        $sala = $pdo->prepare('SELECT criador_id FROM salas_estudo WHERE id = :id LIMIT 1 FOR UPDATE');
        $sala->execute(['id' => $salaId]);
        $linha = $sala->fetch();
        if (!$linha) {
            $pdo->rollBack();
            return false;
        }

        $atual = $pdo->prepare(
            'SELECT admin FROM salas_inscricoes WHERE sala_id = :sala AND usuario_id = :usuario LIMIT 1 FOR UPDATE'
        );
        $atual->execute(['sala' => $salaId, 'usuario' => $usuarioId]);
        $membro = $atual->fetch();
        $contagem = $pdo->prepare('SELECT COUNT(*) FROM salas_inscricoes WHERE sala_id = :sala AND admin = 1');
        $contagem->execute(['sala' => $salaId]);
        $temAdmin = (int) $contagem->fetchColumn() > 0;
        $viraAdmin = $comoAdmin || !$temAdmin || ($membro && (int) $membro['admin'] === 1);

        if ($membro) {
            if ($viraAdmin && !(int) $membro['admin']) {
                $promover = $pdo->prepare(
                    'UPDATE salas_inscricoes SET admin = 1 WHERE sala_id = :sala AND usuario_id = :usuario'
                );
                $promover->execute(['sala' => $salaId, 'usuario' => $usuarioId]);
            }
        } else {
            $inserir = $pdo->prepare(
                'INSERT INTO salas_inscricoes (sala_id, usuario_id, admin) VALUES (:sala, :usuario, :admin)'
            );
            $inserir->execute([
                'sala'    => $salaId,
                'usuario' => $usuarioId,
                'admin'   => $viraAdmin ? 1 : 0,
            ]);
        }

        if ($viraAdmin && empty($linha['criador_id'])) {
            $origem = $pdo->prepare(
                'UPDATE salas_estudo SET criador_id = :usuario WHERE id = :id AND criador_id IS NULL'
            );
            $origem->execute(['usuario' => $usuarioId, 'id' => $salaId]);
        }

        $pdo->commit();

        return $viraAdmin;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function definirAdminDaSala(PDO $pdo, int $salaId, int $membroId, bool $admin): bool
{
    if (!$admin) {
        $contagem = $pdo->prepare('SELECT COUNT(*) FROM salas_inscricoes WHERE sala_id = :sala AND admin = 1');
        $contagem->execute(['sala' => $salaId]);
        $totalAdmins = (int) $contagem->fetchColumn();
        $alvo = $pdo->prepare(
            'SELECT admin FROM salas_inscricoes WHERE sala_id = :sala AND usuario_id = :usuario LIMIT 1'
        );
        $alvo->execute(['sala' => $salaId, 'usuario' => $membroId]);
        if ((int) $alvo->fetchColumn() === 1 && $totalAdmins <= 1) {
            return false;
        }
    }

    $stmt = $pdo->prepare(
        'UPDATE salas_inscricoes SET admin = :admin WHERE sala_id = :sala AND usuario_id = :usuario'
    );
    $stmt->execute([
        'admin'   => $admin ? 1 : 0,
        'sala'    => $salaId,
        'usuario' => $membroId,
    ]);

    return $stmt->rowCount() > 0 || usuarioAdministraSala($pdo, $salaId, $membroId) === $admin;
}

function salaTemOutroAdmin(PDO $pdo, int $salaId, int $usuarioId): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM salas_inscricoes WHERE sala_id = :sala AND admin = 1 AND usuario_id <> :usuario'
    );
    $stmt->execute(['sala' => $salaId, 'usuario' => $usuarioId]);

    return (int) $stmt->fetchColumn() > 0;
}

function origemDaSala(?string $nome): string
{
    $nome = trim((string) $nome);

    return $nome !== '' ? 'criada por ' . primeiroNome($nome) : 'o primeiro aluno administra';
}

function listarSalas(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, COALESCE(u.nome, \'\') AS criador_nome,
                (SELECT COUNT(*) FROM salas_inscricoes i WHERE i.sala_id = s.id) AS inscritos,
                EXISTS(
                    SELECT 1 FROM salas_inscricoes i
                    WHERE i.sala_id = s.id AND i.usuario_id = :usuario
                ) AS inscrito
         FROM salas_estudo s
         LEFT JOIN usuarios u ON u.id = s.criador_id
         ORDER BY s.exemplo DESC, s.id DESC'
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
        'SELECT u.id, u.nome, u.tipo, t.nome AS turma, i.admin
         FROM salas_inscricoes i
         INNER JOIN usuarios u ON u.id = i.usuario_id
         LEFT JOIN alunos a ON a.usuario_id = u.id
         LEFT JOIN turmas t ON t.id = a.turma_id
         WHERE i.sala_id = :sala
         ORDER BY i.admin DESC, i.data_criacao ASC
         LIMIT ' . max(1, $limite)
    );
    $stmt->execute(['sala' => $salaId]);

    return $stmt->fetchAll();
}

function listarCandidatosSala(PDO $pdo, int $salaId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nome, u.tipo, t.nome AS turma
         FROM usuarios u
         LEFT JOIN alunos a ON a.usuario_id = u.id
         LEFT JOIN turmas t ON t.id = a.turma_id
         WHERE u.tipo IN (\'aluno\', \'professor\')
           AND NOT EXISTS (
               SELECT 1 FROM salas_inscricoes i
               WHERE i.sala_id = :sala AND i.usuario_id = u.id
           )
         ORDER BY u.nome'
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

function catalogoIconesSala(): array
{
    return [
        'livro'    => ['Livro', '<path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H19v17H7.5A2.5 2.5 0 0 0 5 21.5Z"/><path d="M5 4.5A2.5 2.5 0 0 1 7.5 7H19"/>'],
        'lapis'    => ['Lápis', '<path d="M4 20h4L18.5 9.5l-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>'],
        'calculo'  => ['Cálculo', '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"/>'],
        'ciencia'  => ['Ciência', '<path d="M9 3h6M10 3v6L5.5 18a2 2 0 0 0 1.7 3h9.6a2 2 0 0 0 1.7-3L14 9V3"/>'],
        'globo'    => ['Geografia', '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.2 2.4 3.3 5.1 3.3 8S14.2 17.6 12 20c-2.2-2.4-3.3-5.1-3.3-8S9.8 6.4 12 4Z"/>'],
        'musica'   => ['Música', '<path d="M9 18V6l10-2v12"/><circle cx="7" cy="18" r="2.2"/><circle cx="17" cy="16" r="2.2"/>'],
        'codigo'   => ['Código', '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13 6l-2 12"/>'],
        'arte'     => ['Arte', '<path d="M12 4a8 8 0 1 0 .2 16H13a2 2 0 0 0 0-4h-1a2 2 0 0 1 0-4h.5"/><circle cx="8" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="10" cy="7.5" r="1" fill="currentColor" stroke="none"/><circle cx="14" cy="7.5" r="1" fill="currentColor" stroke="none"/>'],
        'ideia'    => ['Ideia', '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.2 11c.5.4.8 1 .8 1.6V17h5v-1.4c0-.6.3-1.2.8-1.6A6 6 0 0 0 12 3Z"/>'],
        'estrela'  => ['Destaque', '<path d="m12 4 2.1 4.3 4.7.7-3.4 3.3.8 4.7L12 15.8 7.8 17l.8-4.7L5.2 9l4.7-.7L12 4Z"/>'],
        'conversa' => ['Conversa', '<path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v7A2.5 2.5 0 0 1 16.5 16H10l-4 3.5V6.5Z"/>'],
        'trofeu'   => ['Meta', '<path d="M8 4h8v6a4 4 0 0 1-8 0V4Z"/><path d="M8 6H5.5A1.5 1.5 0 0 0 4 7.5 3.5 3.5 0 0 0 8 11M16 6h2.5A1.5 1.5 0 0 1 20 7.5 3.5 3.5 0 0 1 16 11M12 14v3M9 20h6"/>'],
    ];
}

function iconeSalaValido(string $icone): string
{
    return isset(catalogoIconesSala()[$icone]) ? $icone : '';
}

function htmlIconeSala(array $sala, string $tamanho = ''): string
{
    $classe = 'sala-icone' . ($tamanho !== '' ? ' sala-icone-' . $tamanho : '') . ' ' . capaSala((int) ($sala['id'] ?? 0));
    $icone = (string) ($sala['icone'] ?? '');
    $catalogo = catalogoIconesSala();
    if ($icone !== '' && isset($catalogo[$icone])) {
        return '<span class="' . e($classe) . ' sala-icone-svg" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $catalogo[$icone][1] . '</svg></span>';
    }

    return '<span class="' . e($classe) . '">' . e(iniciaisNome((string) ($sala['titulo'] ?? ''))) . '</span>';
}

function campoIconesSala(string $selecionado): void
{
    $selecionado = iconeSalaValido($selecionado);
    echo '<fieldset class="sala-icones">';
    echo '<legend>Ícone</legend>';
    echo '<label><input type="radio" name="icone" value=""' . ($selecionado === '' ? ' checked' : '') . '><span class="sala-icone-opcao" title="Letras do nome">Aa</span></label>';
    foreach (catalogoIconesSala() as $id => [$rotulo, $svg]) {
        echo '<label><input type="radio" name="icone" value="' . e($id) . '"' . ($selecionado === $id ? ' checked' : '') . '>';
        echo '<span class="sala-icone-opcao" title="' . e($rotulo) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $svg . '</svg></span></label>';
    }
    echo '</fieldset>';
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
