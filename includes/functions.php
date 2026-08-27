<?php

require_once __DIR__ . '/../config/database.php';

function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function primeiroNome(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome));
    return $partes[0] ?? $nome;
}

function formatarData(?string $data): string
{
    if (empty($data)) {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));
    return $dt ? $dt->format('d/m/Y') : $data;
}

function formatarDataCurta(?string $data): string
{
    if (empty($data)) {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));
    return $dt ? $dt->format('d/m') : $data;
}

function formatarHora(?string $hora): string
{
    if (empty($hora)) {
        return '';
    }

    return substr($hora, 0, 5);
}

function rotuloTipo(string $tipo): string
{
    return match ($tipo) {
        'prova'     => 'Prova',
        'trabalho'  => 'Trabalho',
        'tarefa'    => 'Tarefa',
        default     => 'Outro',
    };
}

function rotuloPerfil(string $tipo): string
{
    return match ($tipo) {
        'professor'     => 'Professor',
        'administrador' => 'Administrador',
        default         => 'Aluno',
    };
}

function listarTurmas(PDO $pdo): array
{
    return $pdo->query('SELECT id, nome, ano FROM turmas ORDER BY nome')->fetchAll();
}

function contarAdministradores(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'administrador'")->fetchColumn();
}

function emailEmUso(PDO $pdo, string $email, ?int $ignorarId = null): bool
{
    $sql = 'SELECT id FROM usuarios WHERE email = :email';
    $params = ['email' => $email];

    if ($ignorarId) {
        $sql .= ' AND id <> :id';
        $params['id'] = $ignorarId;
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function sincronizarPerfilUsuario(PDO $pdo, int $usuarioId, string $tipo, array $dados): void
{
    if ($tipo !== 'professor') {
        $stmt = $pdo->prepare('DELETE FROM professores WHERE usuario_id = :usuario_id');
        $stmt->execute(['usuario_id' => $usuarioId]);
    }

    if ($tipo !== 'aluno') {
        $stmt = $pdo->prepare('DELETE FROM alunos WHERE usuario_id = :usuario_id');
        $stmt->execute(['usuario_id' => $usuarioId]);
    }

    if ($tipo === 'professor') {
        $stmt = $pdo->prepare('SELECT id FROM professores WHERE usuario_id = :usuario_id LIMIT 1');
        $stmt->execute(['usuario_id' => $usuarioId]);
        $professorId = $stmt->fetchColumn();

        if (!$professorId) {
            $insert = $pdo->prepare('INSERT INTO professores (usuario_id) VALUES (:usuario_id)');
            $insert->execute(['usuario_id' => $usuarioId]);
            $professorId = (int) $pdo->lastInsertId();
        }

        $limpar = $pdo->prepare('DELETE FROM professor_turmas WHERE professor_id = :professor_id');
        $limpar->execute(['professor_id' => $professorId]);

        $vincular = $pdo->prepare(
            'INSERT INTO professor_turmas (professor_id, turma_id, materia)
             VALUES (:professor_id, :turma_id, :materia)'
        );

        $materiasEnviadas = $dados['turma_materias'] ?? [];

        foreach ($dados['turma_ids'] ?? [] as $turmaId) {
            $turmaId = (int) $turmaId;
            $texto = (string) ($materiasEnviadas[$turmaId] ?? $materiasEnviadas[(string) $turmaId] ?? '');
            $materias = normalizarMaterias($texto);

            if (!$materias) {
                $materias = [''];
            }

            foreach ($materias as $materia) {
                $vincular->execute([
                    'professor_id' => $professorId,
                    'turma_id'     => $turmaId,
                    'materia'      => $materia,
                ]);
            }
        }
    }

    if ($tipo === 'aluno') {
        $stmt = $pdo->prepare('SELECT id FROM alunos WHERE usuario_id = :usuario_id LIMIT 1');
        $stmt->execute(['usuario_id' => $usuarioId]);
        $alunoId = $stmt->fetchColumn();

        if ($alunoId) {
            $update = $pdo->prepare('UPDATE alunos SET turma_id = :turma_id WHERE id = :id');
            $update->execute([
                'turma_id' => $dados['turma_id'],
                'id'       => $alunoId,
            ]);
            return;
        }

        $insert = $pdo->prepare('INSERT INTO alunos (usuario_id, turma_id) VALUES (:usuario_id, :turma_id)');
        $insert->execute([
            'usuario_id' => $usuarioId,
            'turma_id'   => $dados['turma_id'],
        ]);
    }
}

function obterProfessorPorUsuario(PDO $pdo, int $usuarioId): ?array
{
    $sql = 'SELECT p.id, p.usuario_id, u.nome, u.email
            FROM professores p
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE p.usuario_id = :usuario_id
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['usuario_id' => $usuarioId]);
    $professor = $stmt->fetch();

    return $professor ?: null;
}

function obterAlunoPorUsuario(PDO $pdo, int $usuarioId): ?array
{
    $sql = 'SELECT a.id, a.usuario_id, a.turma_id, u.nome, u.email, t.nome AS turma_nome, t.ano AS turma_ano
            FROM alunos a
            INNER JOIN usuarios u ON u.id = a.usuario_id
            INNER JOIN turmas t ON t.id = a.turma_id
            WHERE a.usuario_id = :usuario_id
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['usuario_id' => $usuarioId]);
    $aluno = $stmt->fetch();

    return $aluno ?: null;
}

function listarTurmasDoProfessor(PDO $pdo, int $professorId): array
{
    $sql = 'SELECT t.id, t.nome, t.ano,
                   GROUP_CONCAT(DISTINCT NULLIF(pt.materia, "") ORDER BY pt.materia SEPARATOR ", ") AS materias
            FROM turmas t
            INNER JOIN professor_turmas pt ON pt.turma_id = t.id
            WHERE pt.professor_id = :professor_id
            GROUP BY t.id, t.nome, t.ano
            ORDER BY t.nome';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['professor_id' => $professorId]);

    return $stmt->fetchAll();
}

function normalizarMaterias(string $texto): array
{
    $partes = preg_split('/[,;]+/', $texto) ?: [];
    $unicas = [];

    foreach ($partes as $parte) {
        $materia = trim($parte);
        if ($materia === '') {
            continue;
        }

        $materia = mb_substr($materia, 0, 100);
        $unicas[mb_strtolower($materia)] = $materia;
    }

    return array_values($unicas);
}

function rotuloMateria(?string $materia): string
{
    $materia = trim((string) $materia);
    return $materia !== '' ? $materia : 'Sem matéria';
}

function listarProfessores(PDO $pdo): array
{
    return $pdo->query(
        'SELECT p.id, u.nome, u.email
         FROM professores p
         INNER JOIN usuarios u ON u.id = p.usuario_id
         ORDER BY u.nome'
    )->fetchAll();
}

function listarAlunosDaTurma(PDO $pdo, int $turmaId): array
{
    $stmt = $pdo->prepare(
        'SELECT a.id, u.id AS usuario_id, u.nome, u.email
         FROM alunos a
         INNER JOIN usuarios u ON u.id = a.usuario_id
         WHERE a.turma_id = :turma_id
         ORDER BY u.nome'
    );
    $stmt->execute(['turma_id' => $turmaId]);

    return $stmt->fetchAll();
}

function listarVinculosDaTurma(PDO $pdo, int $turmaId): array
{
    $stmt = $pdo->prepare(
        'SELECT pt.id, pt.professor_id, pt.materia, u.id AS usuario_id, u.nome, u.email
         FROM professor_turmas pt
         INNER JOIN professores p ON p.id = pt.professor_id
         INNER JOIN usuarios u ON u.id = p.usuario_id
         WHERE pt.turma_id = :turma_id
         ORDER BY u.nome, pt.materia'
    );
    $stmt->execute(['turma_id' => $turmaId]);

    return $stmt->fetchAll();
}

function agruparProfessoresDaTurma(array $vinculos): array
{
    $grupos = [];

    foreach ($vinculos as $vinculo) {
        $id = (int) $vinculo['professor_id'];
        if (!isset($grupos[$id])) {
            $grupos[$id] = [
                'professor_id' => $id,
                'usuario_id'   => (int) $vinculo['usuario_id'],
                'nome'         => $vinculo['nome'],
                'email'        => $vinculo['email'],
                'materias'     => [],
            ];
        }

        $grupos[$id]['materias'][] = [
            'id'      => (int) $vinculo['id'],
            'materia' => $vinculo['materia'],
        ];
    }

    return array_values($grupos);
}

function obterTurma(PDO $pdo, int $turmaId): ?array
{
    $stmt = $pdo->prepare('SELECT id, nome, ano FROM turmas WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $turmaId]);
    $turma = $stmt->fetch();

    return $turma ?: null;
}

function materiasDoProfessorPorTurma(PDO $pdo, int $professorId): array
{
    $stmt = $pdo->prepare(
        'SELECT turma_id, materia
         FROM professor_turmas
         WHERE professor_id = :professor_id
         ORDER BY materia'
    );
    $stmt->execute(['professor_id' => $professorId]);

    $porTurma = [];
    foreach ($stmt->fetchAll() as $linha) {
        $porTurma[(int) $linha['turma_id']][] = $linha['materia'];
    }

    $texto = [];
    foreach ($porTurma as $turmaId => $materias) {
        $texto[$turmaId] = implode(', ', array_values(array_filter($materias, static fn ($item) => trim((string) $item) !== '')));
    }

    return $texto;
}

function professorTemTurma(PDO $pdo, int $professorId, int $turmaId): bool
{
    $sql = 'SELECT 1
            FROM professor_turmas
            WHERE professor_id = :professor_id AND turma_id = :turma_id
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'professor_id' => $professorId,
        'turma_id'     => $turmaId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function obterEventoDoProfessor(PDO $pdo, int $eventoId, int $professorId): ?array
{
    $sql = 'SELECT e.*, t.nome AS turma_nome
            FROM eventos e
            INNER JOIN turmas t ON t.id = e.turma_id
            WHERE e.id = :id AND e.professor_id = :professor_id
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id'           => $eventoId,
        'professor_id' => $professorId,
    ]);

    $evento = $stmt->fetch();
    return $evento ?: null;
}

function obterEventoDaTurma(PDO $pdo, int $eventoId, int $turmaId): ?array
{
    $sql = 'SELECT e.*, t.nome AS turma_nome, u.nome AS professor_nome
            FROM eventos e
            INNER JOIN turmas t ON t.id = e.turma_id
            INNER JOIN professores p ON p.id = e.professor_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE e.id = :id AND e.turma_id = :turma_id
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id'       => $eventoId,
        'turma_id' => $turmaId,
    ]);

    $evento = $stmt->fetch();
    return $evento ?: null;
}

function eventoConcluido(PDO $pdo, int $alunoId, int $eventoId): bool
{
    $sql = 'SELECT concluido
            FROM aluno_eventos
            WHERE aluno_id = :aluno_id AND evento_id = :evento_id
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'aluno_id'  => $alunoId,
        'evento_id' => $eventoId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function alternarConclusao(PDO $pdo, int $alunoId, int $eventoId): void
{
    $sql = 'SELECT id, concluido
            FROM aluno_eventos
            WHERE aluno_id = :aluno_id AND evento_id = :evento_id
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'aluno_id'  => $alunoId,
        'evento_id' => $eventoId,
    ]);

    $registro = $stmt->fetch();

    if ($registro) {
        $novoStatus = $registro['concluido'] ? 0 : 1;
        $update = $pdo->prepare(
            'UPDATE aluno_eventos
             SET concluido = :concluido, data_conclusao = :data_conclusao
             WHERE id = :id'
        );
        $update->execute([
            'concluido'      => $novoStatus,
            'data_conclusao' => $novoStatus ? date('Y-m-d H:i:s') : null,
            'id'             => $registro['id'],
        ]);
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO aluno_eventos (aluno_id, evento_id, concluido, data_conclusao)
         VALUES (:aluno_id, :evento_id, 1, :data_conclusao)'
    );
    $insert->execute([
        'aluno_id'       => $alunoId,
        'evento_id'      => $eventoId,
        'data_conclusao' => date('Y-m-d H:i:s'),
    ]);
}

function agruparEventosPorData(array $eventos): array
{
    $agrupados = [];

    foreach ($eventos as $evento) {
        $data = $evento['data_evento'];
        $agrupados[$data][] = $evento;
    }

    return $agrupados;
}

function validarEvento(array $dados, array $turmasPermitidas): array
{
    $erros = [];
    $tipos = ['prova', 'trabalho', 'tarefa', 'outro'];

    $titulo     = trim($dados['titulo'] ?? '');
    $descricao  = trim($dados['descricao'] ?? '');
    $tipo       = $dados['tipo'] ?? '';
    $turmaId    = (int) ($dados['turma_id'] ?? 0);
    $dataEvento = $dados['data_evento'] ?? '';
    $hora       = trim($dados['hora'] ?? '');
    $materia    = trim((string) ($dados['materia'] ?? ''));

    if ($titulo === '') {
        $erros[] = 'Informe o título da atividade.';
    }

    if (!in_array($tipo, $tipos, true)) {
        $erros[] = 'Selecione um tipo válido.';
    }

    $idsTurmas = array_map(static fn ($turma) => (int) $turma['id'], $turmasPermitidas);
    if (!in_array($turmaId, $idsTurmas, true)) {
        $erros[] = 'Selecione uma turma válida.';
    }

    $dataValida = DateTime::createFromFormat('Y-m-d', $dataEvento);
    if (!$dataValida || $dataValida->format('Y-m-d') !== $dataEvento) {
        $erros[] = 'Informe uma data válida.';
    }

    if ($hora !== '') {
        $horaValida = DateTime::createFromFormat('H:i', $hora);
        if (!$horaValida || $horaValida->format('H:i') !== $hora) {
            $erros[] = 'Informe um horário válido.';
        }
    }

    return [
        'erros' => $erros,
        'dados' => [
            'titulo'      => $titulo,
            'descricao'   => htmlSeguro($descricao),
            'tipo'        => $tipo,
            'turma_id'    => $turmaId,
            'data_evento' => $dataEvento,
            'hora'        => $hora !== '' ? $hora . ':00' : null,
            'materia'     => mb_substr($materia, 0, 100),
        ],
    ];
}

function dataPorExtenso(?DateTimeInterface $data = null): string
{
    $data = $data ?? new DateTime('today');
    $dias = [1 => 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'];
    $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    $diaSemana = $dias[(int) $data->format('N')] ?? '';
    $mes = $meses[(int) $data->format('n')] ?? '';

    return $diaSemana . ', ' . $data->format('j') . ' de ' . $mes;
}

function rotuloPrazo(?string $data): string
{
    if (empty($data)) {
        return '';
    }

    $evento = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));
    if (!$evento) {
        return formatarData($data);
    }

    $hoje = new DateTime('today');
    $evento->setTime(0, 0);
    $diff = (int) $hoje->diff($evento)->format('%r%a');

    return match (true) {
        $diff === 0  => 'Hoje',
        $diff === 1  => 'Amanhã',
        $diff === -1 => 'Ontem',
        $diff > 1 && $diff <= 7 => 'Em ' . $diff . ' dias',
        $diff < -1 && $diff >= -7 => 'Há ' . abs($diff) . ' dias',
        default => formatarData($data),
    };
}

function classePrazo(?string $data): string
{
    if (empty($data)) {
        return '';
    }

    $evento = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));
    if (!$evento) {
        return '';
    }

    $hoje = new DateTime('today');
    $evento->setTime(0, 0);
    $diff = (int) $hoje->diff($evento)->format('%r%a');

    if ($diff < 0) {
        return 'is-past';
    }

    if ($diff === 0) {
        return 'is-today';
    }

    if ($diff <= 2) {
        return 'is-soon';
    }

    return '';
}

function htmlSeguro(?string $html): string
{
    $html = $html ?? '';
    $permitidas = '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><a><img><blockquote><span>';
    $limpo = strip_tags($html, $permitidas);
    $limpo = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $limpo) ?? $limpo;
    $limpo = preg_replace('/javascript\s*:/i', '', $limpo) ?? $limpo;
    $limpo = preg_replace('/data\s*:/i', '', $limpo) ?? $limpo;

    return trim($limpo);
}

function descricaoHtml(?string $texto): string
{
    $texto = (string) $texto;

    if ($texto === '') {
        return '';
    }

    if (strip_tags($texto) === $texto) {
        return '<p>' . nl2br(e($texto), false) . '</p>';
    }

    return htmlSeguro($texto);
}

function tiposAnexoPermitidos(): array
{
    return [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'txt'  => ['text/plain'],
        'zip'  => ['application/zip'],
    ];
}

function listarAnexos(PDO $pdo, int $eventoId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, nome_original, caminho, tipo_mime, tamanho
         FROM evento_anexos
         WHERE evento_id = :evento_id
         ORDER BY id'
    );
    $stmt->execute(['evento_id' => $eventoId]);

    return $stmt->fetchAll();
}

function anexoEhImagem(string $mime): bool
{
    return str_starts_with($mime, 'image/');
}

function salvarArquivoEnviado(array $arquivo, string $pastaRelativa, array &$erros): ?array
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $erros[] = 'Não foi possível enviar um dos arquivos.';
        return null;
    }

    if (($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
        $erros[] = 'Cada arquivo deve ter no máximo 5 MB.';
        return null;
    }

    $nomeOriginal = $arquivo['name'] ?? 'arquivo';
    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    $permitidos = tiposAnexoPermitidos();

    if (!isset($permitidos[$extensao])) {
        $erros[] = 'Tipo de arquivo não permitido: ' . $extensao;
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']) ?: '';

    if (!in_array($mime, $permitidos[$extensao], true)) {
        $erros[] = 'O conteúdo do arquivo não corresponde à extensão.';
        return null;
    }

    $destinoDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pastaRelativa);
    if (!is_dir($destinoDir) && !mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
        $erros[] = 'Não foi possível criar a pasta de uploads.';
        return null;
    }

    $nomeSalvo = bin2hex(random_bytes(8)) . '.' . $extensao;
    $caminhoAbsoluto = $destinoDir . DIRECTORY_SEPARATOR . $nomeSalvo;

    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoAbsoluto)) {
        $erros[] = 'Falha ao gravar o arquivo enviado.';
        return null;
    }

    return [
        'nome_original' => $nomeOriginal,
        'caminho'       => $pastaRelativa . '/' . $nomeSalvo,
        'tipo_mime'     => $mime,
        'tamanho'       => (int) $arquivo['size'],
    ];
}

function salvarAnexosEvento(PDO $pdo, int $eventoId, array $files, array &$erros): void
{
    if (empty($files['name']) || !is_array($files['name'])) {
        return;
    }

    $total = count($files['name']);
    $insert = $pdo->prepare(
        'INSERT INTO evento_anexos (evento_id, nome_original, caminho, tipo_mime, tamanho)
         VALUES (:evento_id, :nome_original, :caminho, :tipo_mime, :tamanho)'
    );

    for ($i = 0; $i < $total; $i++) {
        $arquivo = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$i] ?? 0,
        ];

        $salvo = salvarArquivoEnviado($arquivo, 'eventos/' . $eventoId, $erros);
        if (!$salvo) {
            continue;
        }

        $insert->execute([
            'evento_id'     => $eventoId,
            'nome_original' => $salvo['nome_original'],
            'caminho'       => $salvo['caminho'],
            'tipo_mime'     => $salvo['tipo_mime'],
            'tamanho'       => $salvo['tamanho'],
        ]);
    }
}

function removerAnexosEvento(PDO $pdo, int $eventoId, array $ids): void
{
    if (!$ids) {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT id, caminho FROM evento_anexos WHERE evento_id = :evento_id AND id = :id LIMIT 1'
    );

    foreach ($ids as $id) {
        $stmt->execute([
            'evento_id' => $eventoId,
            'id'        => (int) $id,
        ]);
        $anexo = $stmt->fetch();
        if (!$anexo) {
            continue;
        }

        $caminho = UPLOAD_PATH . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $anexo['caminho']);
        if (is_file($caminho)) {
            unlink($caminho);
        }

        $delete = $pdo->prepare('DELETE FROM evento_anexos WHERE id = :id');
        $delete->execute(['id' => $anexo['id']]);
    }
}

function excluirArquivosEvento(PDO $pdo, int $eventoId): void
{
    foreach (listarAnexos($pdo, $eventoId) as $anexo) {
        $caminho = UPLOAD_PATH . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $anexo['caminho']);
        if (is_file($caminho)) {
            unlink($caminho);
        }
    }
}

function turmasPadrao(): array
{
    $ano = '2026';

    return [
        ['1º Ano Informática', $ano],
        ['2º Ano Informática', $ano],
        ['3º Ano Informática', $ano],
        ['1º Ano Administração', $ano],
        ['2º Ano Administração', $ano],
        ['3º Ano Administração', $ano],
        ['1º Ano Agropecuária', $ano],
        ['2º Ano Agropecuária', $ano],
        ['3º Ano Agropecuária', $ano],
    ];
}

function popularTurmasPadrao(PDO $pdo): int
{
    $inseridas = 0;
    $buscar = $pdo->prepare('SELECT id FROM turmas WHERE nome = :nome LIMIT 1');
    $inserir = $pdo->prepare('INSERT INTO turmas (nome, ano) VALUES (:nome, :ano)');

    foreach (turmasPadrao() as [$nome, $ano]) {
        $buscar->execute(['nome' => $nome]);
        if ($buscar->fetchColumn()) {
            continue;
        }

        $inserir->execute(['nome' => $nome, 'ano' => $ano]);
        $inseridas++;
    }

    return $inseridas;
}

function usuarioPodeVerTurma(PDO $pdo, array $usuario, int $turmaId): bool
{
    if ($usuario['tipo'] === 'administrador') {
        return true;
    }

    if ($usuario['tipo'] === 'professor') {
        $professor = obterProfessorPorUsuario($pdo, (int) $usuario['id']);
        return $professor ? professorTemTurma($pdo, (int) $professor['id'], $turmaId) : false;
    }

    $aluno = obterAlunoPorUsuario($pdo, (int) $usuario['id']);
    return $aluno && (int) $aluno['turma_id'] === $turmaId;
}

function usuarioPodeVerEvento(PDO $pdo, array $usuario, int $eventoId): ?array
{
    $sql = 'SELECT e.*, t.nome AS turma_nome, u.nome AS professor_nome
            FROM eventos e
            INNER JOIN turmas t ON t.id = e.turma_id
            INNER JOIN professores p ON p.id = e.professor_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE e.id = :id
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $eventoId]);
    $evento = $stmt->fetch();

    if (!$evento) {
        return null;
    }

    if ($usuario['tipo'] === 'administrador') {
        return $evento;
    }

    if ($usuario['tipo'] === 'professor') {
        $professor = obterProfessorPorUsuario($pdo, (int) $usuario['id']);
        if (!$professor) {
            return null;
        }

        if ((int) $evento['professor_id'] === (int) $professor['id'] || professorTemTurma($pdo, (int) $professor['id'], (int) $evento['turma_id'])) {
            $evento['pode_editar'] = (int) $evento['professor_id'] === (int) $professor['id'];
            return $evento;
        }

        return null;
    }

    $aluno = obterAlunoPorUsuario($pdo, (int) $usuario['id']);
    if ($aluno && (int) $aluno['turma_id'] === (int) $evento['turma_id']) {
        $evento['concluido'] = eventoConcluido($pdo, (int) $aluno['id'], $eventoId);
        return $evento;
    }

    return null;
}

function diasDaSemana(): array
{
    return [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
}

function rotuloDiaSemana(int $dia): string
{
    return diasDaSemana()[$dia] ?? 'Dia';
}

function listarDisciplinasDaTurma(PDO $pdo, int $turmaId): array
{
    $stmt = $pdo->prepare(
        'SELECT pt.materia, p.id AS professor_id, u.nome AS professor_nome
         FROM professor_turmas pt
         INNER JOIN professores p ON p.id = pt.professor_id
         INNER JOIN usuarios u ON u.id = p.usuario_id
         WHERE pt.turma_id = :turma_id
         ORDER BY pt.materia = \'\', pt.materia, u.nome'
    );
    $stmt->execute(['turma_id' => $turmaId]);

    $grupos = [];
    foreach ($stmt->fetchAll() as $linha) {
        $chave = mb_strtolower(trim((string) $linha['materia']));
        if (!isset($grupos[$chave])) {
            $grupos[$chave] = [
                'materia'     => $linha['materia'],
                'professores' => [],
            ];
        }
        $grupos[$chave]['professores'][] = [
            'id'   => (int) $linha['professor_id'],
            'nome' => $linha['professor_nome'],
        ];
    }

    return array_values($grupos);
}

function listarHorariosDaTurma(PDO $pdo, int $turmaId, ?string $materia = null): array
{
    $sql = 'SELECT h.*, u.nome AS professor_nome
            FROM horarios_aula h
            INNER JOIN professores p ON p.id = h.professor_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE h.turma_id = :turma_id';
    $params = ['turma_id' => $turmaId];

    if ($materia !== null) {
        $sql .= ' AND h.materia = :materia';
        $params['materia'] = $materia;
    }

    $sql .= ' ORDER BY h.dia_semana, h.hora_inicio';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function agruparHorariosPorDia(array $horarios): array
{
    $grupos = [];
    foreach (diasDaSemana() as $dia => $nome) {
        $grupos[$dia] = [
            'dia'    => $dia,
            'nome'   => $nome,
            'aulas'  => [],
        ];
    }

    foreach ($horarios as $aula) {
        $dia = (int) $aula['dia_semana'];
        if (!isset($grupos[$dia])) {
            continue;
        }
        $grupos[$dia]['aulas'][] = $aula;
    }

    return $grupos;
}

function proximaAulaHoje(array $horarios): ?array
{
    $hoje = (int) (new DateTime('today'))->format('N');
    $agora = (new DateTime())->format('H:i:s');
    $candidata = null;

    foreach ($horarios as $aula) {
        if ((int) $aula['dia_semana'] !== $hoje) {
            continue;
        }
        if ($aula['hora_fim'] && $aula['hora_fim'] < $agora) {
            continue;
        }
        if ($candidata === null || $aula['hora_inicio'] < $candidata['hora_inicio']) {
            $candidata = $aula;
        }
    }

    return $candidata;
}

function listarEventosDaDisciplina(PDO $pdo, int $turmaId, int $alunoId, string $materia): array
{
    $ids = [];
    foreach (listarDisciplinasDaTurma($pdo, $turmaId) as $disciplina) {
        if (strcasecmp($disciplina['materia'], $materia) === 0) {
            $ids = array_map(static fn ($item) => (int) $item['id'], $disciplina['professores']);
            break;
        }
    }

    $sql = 'SELECT e.id, e.titulo, e.tipo, e.data_evento, e.hora, e.materia,
                   COALESCE(ae.concluido, 0) AS concluido
            FROM eventos e
            LEFT JOIN aluno_eventos ae ON ae.evento_id = e.id AND ae.aluno_id = :aluno_id
            WHERE e.turma_id = :turma_id';
    $params = [
        'aluno_id' => $alunoId,
        'turma_id' => $turmaId,
    ];

    if ($materia !== '') {
        $sql .= ' AND (e.materia = :materia';
        $params['materia'] = $materia;
        if ($ids) {
            $sql .= ' OR (e.materia = \'\' AND e.professor_id IN (' . implode(',', $ids) . '))';
        }
        $sql .= ')';
    }

    $sql .= ' ORDER BY e.data_evento ASC, e.hora ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function listarAtividadesPessoais(PDO $pdo, int $alunoId, ?string $materia = null): array
{
    $sql = 'SELECT * FROM aluno_atividades WHERE aluno_id = :aluno_id';
    $params = ['aluno_id' => $alunoId];

    if ($materia !== null) {
        $sql .= ' AND materia = :materia';
        $params['materia'] = $materia;
    }

    $sql .= ' ORDER BY data_evento ASC, hora ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function obterAtividadePessoal(PDO $pdo, int $id, int $alunoId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM aluno_atividades WHERE id = :id AND aluno_id = :aluno_id LIMIT 1'
    );
    $stmt->execute([
        'id'       => $id,
        'aluno_id' => $alunoId,
    ]);
    $atividade = $stmt->fetch();

    return $atividade ?: null;
}

function validarAtividadePessoal(array $dados, array $disciplinas): array
{
    $erros = [];
    $titulo = trim($dados['titulo'] ?? '');
    $descricao = trim($dados['descricao'] ?? '');
    $dataEvento = $dados['data_evento'] ?? '';
    $hora = trim($dados['hora'] ?? '');
    $materia = trim((string) ($dados['materia'] ?? ''));

    if ($titulo === '') {
        $erros[] = 'Informe o título da atividade.';
    }

    $dataValida = DateTime::createFromFormat('Y-m-d', $dataEvento);
    if (!$dataValida || $dataValida->format('Y-m-d') !== $dataEvento) {
        $erros[] = 'Informe uma data válida.';
    }

    if ($hora !== '') {
        $horaValida = DateTime::createFromFormat('H:i', substr($hora, 0, 5));
        if (!$horaValida) {
            $erros[] = 'Informe um horário válido.';
        } else {
            $hora = $horaValida->format('H:i') . ':00';
        }
    } else {
        $hora = null;
    }

    $materiasValidas = array_map(static fn ($item) => $item['materia'], $disciplinas);
    if ($materia !== '' && !in_array($materia, $materiasValidas, true)) {
        $erros[] = 'Selecione uma disciplina válida.';
    }

    return [
        'erros' => $erros,
        'dados' => [
            'titulo'      => $titulo,
            'descricao'   => $descricao,
            'data_evento' => $dataEvento,
            'hora'        => $hora,
            'materia'     => $materia,
        ],
    ];
}

function validarHorarioAula(array $dados): array
{
    $erros = [];
    $dia = (int) ($dados['dia_semana'] ?? 0);
    $inicio = trim($dados['hora_inicio'] ?? '');
    $fim = trim($dados['hora_fim'] ?? '');
    $materia = trim((string) ($dados['materia'] ?? ''));

    if (!isset(diasDaSemana()[$dia])) {
        $erros[] = 'Selecione o dia da semana.';
    }

    $inicioValido = DateTime::createFromFormat('H:i', substr($inicio, 0, 5));
    if (!$inicioValido) {
        $erros[] = 'Informe o horário de início.';
        $inicio = null;
    } else {
        $inicio = $inicioValido->format('H:i:s');
    }

    if ($fim !== '') {
        $fimValido = DateTime::createFromFormat('H:i', substr($fim, 0, 5));
        if (!$fimValido) {
            $erros[] = 'Informe um horário de término válido.';
            $fim = null;
        } else {
            $fim = $fimValido->format('H:i:s');
        }
    } else {
        $fim = null;
    }

    if ($inicio && $fim && $fim <= $inicio) {
        $erros[] = 'O término deve ser depois do início.';
    }

    return [
        'erros' => $erros,
        'dados' => [
            'materia'     => mb_substr($materia, 0, 100),
            'dia_semana'  => $dia,
            'hora_inicio' => $inicio,
            'hora_fim'    => $fim,
        ],
    ];
}

function processarHorarioTurma(PDO $pdo, array $post, int $turmaId, ?int $professorRestrito, array &$erros): ?string
{
    $acao = $post['acao'] ?? '';

    if ($acao === 'remover_horario') {
        $sql = 'DELETE FROM horarios_aula WHERE id = :id AND turma_id = :turma_id';
        $params = [
            'id'       => (int) ($post['horario_id'] ?? 0),
            'turma_id' => $turmaId,
        ];
        if ($professorRestrito) {
            $sql .= ' AND professor_id = :professor_id';
            $params['professor_id'] = $professorRestrito;
        }
        $pdo->prepare($sql)->execute($params);
        return 'horario_removido';
    }

    if ($acao !== 'adicionar_horario') {
        return null;
    }

    $partes = explode('|', (string) ($post['vinculo'] ?? ''), 2);
    $professorId = (int) ($partes[0] ?? 0);
    $materia = $partes[1] ?? '';

    if ($professorRestrito && $professorId !== $professorRestrito) {
        $erros[] = 'Você só pode cadastrar horários das suas disciplinas.';
        return null;
    }

    if (!professorTemTurma($pdo, $professorId, $turmaId)) {
        $erros[] = 'Selecione uma disciplina válida.';
        return null;
    }

    $validacao = validarHorarioAula([
        'dia_semana'  => $post['dia_semana'] ?? '',
        'hora_inicio' => $post['hora_inicio'] ?? '',
        'hora_fim'    => $post['hora_fim'] ?? '',
        'materia'     => $materia,
    ]);

    if ($validacao['erros']) {
        $erros = array_merge($erros, $validacao['erros']);
        return null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO horarios_aula (turma_id, professor_id, materia, dia_semana, hora_inicio, hora_fim)
         VALUES (:turma_id, :professor_id, :materia, :dia_semana, :hora_inicio, :hora_fim)'
    );
    $stmt->execute([
        'turma_id'     => $turmaId,
        'professor_id' => $professorId,
        'materia'      => $validacao['dados']['materia'],
        'dia_semana'   => $validacao['dados']['dia_semana'],
        'hora_inicio'  => $validacao['dados']['hora_inicio'],
        'hora_fim'     => $validacao['dados']['hora_fim'],
    ]);

    return 'horario_ok';
}

function opcoesHorarioDaTurma(array $professores, ?int $somenteProfessor = null): array
{
    $opcoes = [];
    foreach ($professores as $professor) {
        if ($somenteProfessor && (int) $professor['professor_id'] !== $somenteProfessor) {
            continue;
        }
        foreach ($professor['materias'] as $item) {
            $opcoes[] = [
                'professor_id' => (int) $professor['professor_id'],
                'materia'      => $item['materia'],
                'label'        => $professor['nome'] . ' · ' . rotuloMateria($item['materia']),
            ];
        }
    }
    return $opcoes;
}

function destinovoltarAluno(?string $voltar): string
{
    $padrao = BASE_URL . '/aluno/atividades.php?origem=pessoais';
    if (!$voltar) {
        return $padrao;
    }

    $caminho = parse_url($voltar, PHP_URL_PATH) ?: '';
    if (!str_starts_with($caminho, BASE_URL . '/aluno/')) {
        return $padrao;
    }

    return $voltar;
}
