<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'calendario_estudantil');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('BASE_URL', '/c-estudantil');
define('UPLOAD_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ]);

        try {
            garantirEsquemaProfessorTurmas($pdo);
            garantirEsquemaAgendaAluno($pdo);
        } catch (Throwable $e) {
            // Migração incompleta não pode derrubar login, filtros nem o restante do sistema.
        }
    }

    return $pdo;
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

    popularHorariosDemo($pdo);

    $pdo->exec("UPDATE eventos SET materia = 'Matemática' WHERE titulo = 'Prova de Matemática' AND materia = ''");
    $pdo->exec("UPDATE eventos SET materia = 'História' WHERE titulo = 'Trabalho de História' AND materia = ''");
    $pdo->exec("UPDATE eventos SET materia = 'Banco de Dados' WHERE titulo LIKE 'Seminário de Banco%' AND materia = ''");
    $pdo->exec("UPDATE eventos SET materia = 'PHP' WHERE titulo = 'Atividade de PHP' AND materia = ''");
    $pdo->exec("UPDATE eventos SET materia = 'Redes' WHERE titulo = 'Prova de Redes' AND materia = ''");
    $pdo->exec("UPDATE eventos SET materia = 'Inglês' WHERE titulo = 'Trabalho de Inglês' AND materia = ''");
    } catch (Throwable $e) {
        // Tabelas novas ou coluna materia ausente não podem quebrar as telas.
    }
}

function popularHorariosDemo(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM horarios_aula')->fetchColumn() > 0) {
        return;
    }

    $turma = $pdo->query("SELECT id FROM turmas WHERE nome = '2º Ano Informática' LIMIT 1")->fetchColumn();
    $joao = $pdo->query("SELECT p.id FROM professores p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email = 'joao@escola.com' LIMIT 1")->fetchColumn();
    $maria = $pdo->query("SELECT p.id FROM professores p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email = 'maria@escola.com' LIMIT 1")->fetchColumn();

    if (!$turma || !$joao || !$maria) {
        return;
    }

    $inserir = $pdo->prepare(
        'INSERT INTO horarios_aula (turma_id, professor_id, materia, dia_semana, hora_inicio, hora_fim)
         VALUES (:turma_id, :professor_id, :materia, :dia_semana, :hora_inicio, :hora_fim)'
    );

    $aulas = [
        [$joao, 'Matemática', 1, '08:00:00', '09:40:00'],
        [$maria, 'Banco de Dados', 2, '08:00:00', '09:40:00'],
        [$joao, 'História', 3, '10:00:00', '11:40:00'],
        [$maria, 'Banco de Dados', 4, '14:00:00', '15:40:00'],
        [$joao, 'Matemática', 5, '08:00:00', '09:40:00'],
    ];

    foreach ($aulas as [$professorId, $materia, $dia, $inicio, $fim]) {
        $inserir->execute([
            'turma_id'      => $turma,
            'professor_id'  => $professorId,
            'materia'       => $materia,
            'dia_semana'    => $dia,
            'hora_inicio'   => $inicio,
            'hora_fim'      => $fim,
        ]);
    }
}
