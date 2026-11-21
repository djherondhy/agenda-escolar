<?php

require_once __DIR__ . '/env.php';

// Credenciais: variáveis do servidor ou arquivo .env (ver config/env.php).
// Sem nada configurado, usa o padrão do XAMPP local.
carregarEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
$bancoCfg = configurarBanco();

define('DB_HOST', $bancoCfg['host']);
define('DB_PORT', $bancoCfg['port']);
define('DB_NAME', $bancoCfg['name']);
define('DB_USER', $bancoCfg['user']);
define('DB_PASS', $bancoCfg['pass']);
define('DB_CHARSET', $bancoCfg['charset']);
define('BASE_URL', detectarBaseUrl());
define('UPLOAD_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
unset($bancoCfg);

const ADMIN_EMAIL_PADRAO = 'admin@admin.com';
const ADMIN_SENHA_PADRAO = 'adminadmin';

function dsnBanco(bool $comBanco = true): string
{
    return 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
        . ($comBanco ? ';dbname=' . DB_NAME : '') . ';charset=' . DB_CHARSET;
}

function instalarBancoSeNecessario(): void
{
    static $verificado = false;
    if ($verificado) {
        return;
    }
    $verificado = true;

    $opcoes = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

    try {
        // Hospedagem compartilhada: o banco já existe e o usuário não pode criar bancos.
        $pdo = new PDO(dsnBanco(true), DB_USER, DB_PASS, $opcoes);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1049) {
            throw $e;
        }

        // Ambiente local (XAMPP): cria o banco na primeira execução.
        $pdo = new PDO(dsnBanco(false), DB_USER, DB_PASS, $opcoes);
        $nome = str_replace('`', '', DB_NAME);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $nome . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . $nome . '`');
    }

    if ($pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch()) {
        return;
    }

    $sql = file_get_contents(dirname(__DIR__) . '/database/calendario_estudantil.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);

    foreach (preg_split('/;\s*[\r\n]+/', $sql) as $parte) {
        $comando = trim($parte);
        if ($comando === '' || stripos($comando, 'CREATE DATABASE') === 0 || stripos($comando, 'USE ') === 0) {
            continue;
        }
        $pdo->exec($comando);
    }
}

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        instalarBancoSeNecessario();
        $pdo = new PDO(dsnBanco(true), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ]);

        try {
            garantirEsquemaProfessorTurmas($pdo);
            garantirEsquemaAgendaAluno($pdo);
            garantirConclusaoEvento($pdo);
            garantirNotificacoes($pdo);
            garantirPush($pdo);
            garantirRepresentanteAluno($pdo);
            require_once dirname(__DIR__) . '/includes/comunidade.php';
            garantirComunidade($pdo);
            prepararProducao($pdo);
        } catch (Throwable $e) {
            // Migração incompleta não pode derrubar login, filtros nem o restante do sistema.
        }
    }

    return $pdo;
}

/**
 * Prepara o banco para produção:
 *  - remove uma única vez as contas de teste (e tudo que elas criaram, via ON DELETE CASCADE);
 *  - garante que exista um administrador (admin@admin.com).
 */
function prepararProducao(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS migracoes (
            nome VARCHAR(100) PRIMARY KEY,
            executada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $jaExecutou = $pdo->prepare('SELECT 1 FROM migracoes WHERE nome = :nome');
    $jaExecutou->execute(['nome' => 'remover_contas_teste']);

    if (!$jaExecutou->fetchColumn()) {
        $emails = [
            'joao@escola.com', 'maria@escola.com', 'carlos@escola.com',
            'ana@escola.com', 'pedro@escola.com', 'lucas@escola.com',
        ];
        $marcas = implode(',', array_fill(0, count($emails), '?'));

        $ids = $pdo->prepare("SELECT id FROM usuarios WHERE email IN ({$marcas})");
        $ids->execute($emails);
        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $usuarioId) {
            removerArquivosDoUsuarioTeste($pdo, (int) $usuarioId);
        }

        $pdo->prepare("DELETE FROM usuarios WHERE email IN ({$marcas})")->execute($emails);

        // Padroniza o administrador (uma única vez; depois a senha pode ser trocada no painel).
        $existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
        $existe->execute(['email' => ADMIN_EMAIL_PADRAO]);
        $hash = password_hash(ADMIN_SENHA_PADRAO, PASSWORD_DEFAULT);
        if ($existe->fetchColumn()) {
            $pdo->prepare('UPDATE usuarios SET senha = :senha, tipo = :tipo WHERE email = :email')
                ->execute(['senha' => $hash, 'tipo' => 'administrador', 'email' => ADMIN_EMAIL_PADRAO]);
        } else {
            $pdo->prepare('INSERT INTO usuarios (nome, email, senha, tipo) VALUES (:nome, :email, :senha, :tipo)')
                ->execute(['nome' => 'Administrador', 'email' => ADMIN_EMAIL_PADRAO, 'senha' => $hash, 'tipo' => 'administrador']);
        }

        $pdo->prepare('INSERT INTO migracoes (nome) VALUES (?)')->execute(['remover_contas_teste']);
    }

    $admin = $pdo->prepare('SELECT 1 FROM usuarios WHERE tipo = :tipo LIMIT 1');
    $admin->execute(['tipo' => 'administrador']);
    if (!$admin->fetchColumn()) {
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha, tipo) VALUES (:nome, :email, :senha, :tipo)')
            ->execute([
                'nome'  => 'Administrador',
                'email' => ADMIN_EMAIL_PADRAO,
                'senha' => password_hash(ADMIN_SENHA_PADRAO, PASSWORD_DEFAULT),
                'tipo'  => 'administrador',
            ]);
    }
}

function removerArquivosDoUsuarioTeste(PDO $pdo, int $usuarioId): void
{
    // Anexos de posts/comentários feitos pelas contas de teste ficam em uploads/.
    $consultas = [
        'SELECT a.caminho FROM mural_anexos a INNER JOIN mural_posts p ON p.id = a.post_id WHERE p.autor_id = :id',
        'SELECT a.caminho FROM salas_anexos a INNER JOIN salas_posts p ON p.id = a.post_id WHERE p.autor_id = :id',
    ];

    foreach ($consultas as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $usuarioId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $caminho) {
                $arquivo = UPLOAD_PATH . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $caminho);
                if (strpos($caminho, '..') === false && is_file($arquivo)) {
                    @unlink($arquivo);
                }
            }
        } catch (Throwable $e) {
            // Falha ao limpar arquivo não impede a remoção da conta.
        }
    }
}

function nomesFkProfessorTurmas(PDO $pdo): array
{
    $linha = $pdo->query('SHOW CREATE TABLE professor_turmas')->fetch();
    $sql = $linha['Create Table'] ?? $linha[1] ?? '';
    preg_match_all('/CONSTRAINT `([^`]+)` FOREIGN KEY/', $sql, $matches);

    return $matches[1] ?? [];
}

function garantirEsquemaProfessorTurmas(PDO $pdo): void
{
    try {
        $coluna = $pdo->query("SHOW COLUMNS FROM professor_turmas LIKE 'materia'")->fetch();
        if (!$coluna) {
            $pdo->exec("ALTER TABLE professor_turmas ADD COLUMN materia VARCHAR(100) NOT NULL DEFAULT '' AFTER turma_id");
        }

        $indiceNovo = $pdo->query("SHOW INDEX FROM professor_turmas WHERE Key_name = 'professor_turma_materia'")->fetch();
        if ($indiceNovo) {
            return;
        }

        foreach (nomesFkProfessorTurmas($pdo) as $fk) {
            $pdo->exec('ALTER TABLE professor_turmas DROP FOREIGN KEY `' . str_replace(['`', ';'], '', $fk) . '`');
        }

        $indices = $pdo->query(
            "SHOW INDEX FROM professor_turmas WHERE Non_unique = 0 AND Key_name <> 'PRIMARY'"
        )->fetchAll();
        $removidos = [];

        foreach ($indices as $indice) {
            $nome = $indice['Key_name'];
            if ($nome === 'professor_turma_materia' || isset($removidos[$nome])) {
                continue;
            }
            $removidos[$nome] = true;
            $pdo->exec('ALTER TABLE professor_turmas DROP INDEX `' . str_replace(['`', ';'], '', $nome) . '`');
        }

        $pdo->exec(
            'ALTER TABLE professor_turmas ADD UNIQUE KEY professor_turma_materia (professor_id, turma_id, materia)'
        );

        $indiceTurma = $pdo->query("SHOW INDEX FROM professor_turmas WHERE Column_name = 'turma_id'")->fetch();
        if (!$indiceTurma) {
            $pdo->exec('ALTER TABLE professor_turmas ADD KEY professor_turmas_turma (turma_id)');
        }

        if (!nomesFkProfessorTurmas($pdo)) {
            $pdo->exec(
                'ALTER TABLE professor_turmas
                 ADD CONSTRAINT professor_turmas_professor_fk
                 FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE'
            );
            $pdo->exec(
                'ALTER TABLE professor_turmas
                 ADD CONSTRAINT professor_turmas_turma_fk
                 FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE'
            );
        }
    } catch (PDOException $e) {
        // A tela de login não pode quebrar se a migração já tiver rodado em parte.
    }
}

function garantirEsquemaAgendaAluno(PDO $pdo): void
{
    try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS aluno_atividades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aluno_id INT NOT NULL,
            titulo VARCHAR(150) NOT NULL,
            descricao TEXT,
            data_evento DATE NOT NULL,
            hora TIME NULL,
            materia VARCHAR(100) NOT NULL DEFAULT \'\',
            concluido TINYINT(1) NOT NULL DEFAULT 0,
            data_conclusao DATETIME NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS horarios_aula (
            id INT AUTO_INCREMENT PRIMARY KEY,
            turma_id INT NOT NULL,
            professor_id INT NOT NULL,
            materia VARCHAR(100) NOT NULL DEFAULT \'\',
            dia_semana TINYINT NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fim TIME NULL,
            FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
            FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE
        )'
    );

    $coluna = $pdo->query("SHOW COLUMNS FROM eventos LIKE 'materia'")->fetch();
    if (!$coluna) {
        $pdo->exec("ALTER TABLE eventos ADD COLUMN materia VARCHAR(100) NOT NULL DEFAULT '' AFTER hora");
    }


    } catch (Throwable $e) {
        // Tabelas novas ou coluna materia ausente não podem quebrar as telas.
    }
}

function garantirConclusaoEvento(PDO $pdo): void
{
    $coluna = $pdo->query("SHOW COLUMNS FROM eventos LIKE 'concluido'")->fetch();
    if (!$coluna) {
        $pdo->exec('ALTER TABLE eventos ADD COLUMN concluido TINYINT(1) NOT NULL DEFAULT 0');
    }

    $pdo->exec("ALTER TABLE eventos MODIFY tipo ENUM('prova', 'trabalho', 'tarefa', 'outro', 'atividade', 'reuniao') NOT NULL");
    $pdo->exec("UPDATE eventos SET tipo = 'atividade' WHERE tipo = 'tarefa'");
    $pdo->exec("UPDATE eventos SET tipo = 'reuniao' WHERE tipo = 'outro'");
    $pdo->exec("ALTER TABLE eventos MODIFY tipo ENUM('prova', 'trabalho', 'atividade', 'reuniao') NOT NULL");
}

function garantirRepresentanteAluno(PDO $pdo): void
{
    $coluna = $pdo->query("SHOW COLUMNS FROM alunos LIKE 'representante'")->fetch();
    if (!$coluna) {
        $pdo->exec('ALTER TABLE alunos ADD COLUMN representante TINYINT(1) NOT NULL DEFAULT 0 AFTER turma_id');
    }
}

function garantirPush(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS push_inscricoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aluno_id INT NOT NULL,
            endpoint VARCHAR(500) NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            UNIQUE KEY push_endpoint (endpoint),
            FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
        )'
    );
}

function garantirNotificacoes(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notificacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aluno_id INT NOT NULL,
            evento_id INT NOT NULL,
            lida TINYINT(1) NOT NULL DEFAULT 0,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
            FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
        )'
    );
}
