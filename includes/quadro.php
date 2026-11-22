<?php

function periodosDoDia(): array
{
    return [
        ['ordem' => 1, 'inicio' => '07:30', 'fim' => '08:20'],
        ['ordem' => 2, 'inicio' => '08:20', 'fim' => '09:10'],
        ['ordem' => 3, 'inicio' => '09:10', 'fim' => '10:00'],
        ['ordem' => 4, 'inicio' => '10:20', 'fim' => '11:10'],
        ['ordem' => 5, 'inicio' => '11:10', 'fim' => '12:00'],
        ['ordem' => 6, 'inicio' => '12:00', 'fim' => '12:50'],
    ];
}

function diasDoQuadro(): array
{
    return [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta'];
}

function slotDoQuadro(int $dia, string $inicio): ?array
{
    if (!isset(diasDoQuadro()[$dia])) {
        return null;
    }

    $inicio = substr($inicio, 0, 5);
    foreach (periodosDoDia() as $periodo) {
        if ($periodo['inicio'] === $inicio) {
            return $periodo;
        }
    }

    return null;
}

function professorDaMateria(PDO $pdo, int $turmaId, string $materia): ?int
{
    if ($materia === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT professor_id FROM professor_turmas
         WHERE turma_id = :turma AND materia = :materia
         ORDER BY id LIMIT 1'
    );
    $stmt->execute(['turma' => $turmaId, 'materia' => $materia]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $rep = $pdo->prepare(
        'SELECT p.id
         FROM alunos a
         INNER JOIN professores p ON p.usuario_id = a.usuario_id
         WHERE a.turma_id = :turma AND a.representante = 1
         LIMIT 1'
    );
    $rep->execute(['turma' => $turmaId]);
    $id = $rep->fetchColumn();

    return $id ? (int) $id : null;
}

function montarQuadroAutomatico(array $nomes): array
{
    $nomes = array_values(array_filter(array_map('strval', $nomes), static fn ($nome) => $nome !== ''));
    $total = count($nomes);
    if ($total === 0) {
        return [];
    }

    $aulas = [];
    $cursor = 0;
    $periodos = periodosDoDia();

    foreach (array_keys(diasDoQuadro()) as $dia) {
        $noDia = [];
        $anterior = null;
        foreach ($periodos as $periodo) {
            $escolhida = null;
            for ($tentativa = 0; $tentativa < $total; $tentativa++) {
                $candidata = $nomes[($cursor + $tentativa) % $total];
                if ($candidata === $anterior) {
                    continue;
                }
                if ($total >= count($periodos) && in_array($candidata, $noDia, true)) {
                    continue;
                }
                $escolhida = $candidata;
                $cursor = ($cursor + $tentativa + 1) % $total;
                break;
            }
            if ($escolhida === null) {
                $escolhida = $nomes[$cursor % $total];
                $cursor = ($cursor + 1) % $total;
            }
            $noDia[] = $escolhida;
            $anterior = $escolhida;
            $aulas[] = [
                'dia'    => $dia,
                'inicio' => $periodo['inicio'],
                'fim'    => $periodo['fim'],
                'materia'=> $escolhida,
            ];
        }
    }

    return $aulas;
}

function garantirQuadroHorario(PDO $pdo): void
{
    if (!$pdo->query("SHOW COLUMNS FROM turmas LIKE 'horario_aplicado'")->fetch()) {
        $pdo->exec('ALTER TABLE turmas ADD COLUMN horario_aplicado TINYINT(1) NOT NULL DEFAULT 0');
    }

    $coluna = $pdo->query("SHOW COLUMNS FROM horarios_aula LIKE 'professor_id'")->fetch();
    if ($coluna && strtoupper((string) $coluna['Null']) === 'NO') {
        try {
            $chaves = $pdo->query(
                "SELECT CONSTRAINT_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'horarios_aula'
                   AND COLUMN_NAME = 'professor_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL"
            )->fetchAll();
            foreach ($chaves as $chave) {
                $nome = str_replace('`', '', (string) $chave['CONSTRAINT_NAME']);
                $pdo->exec('ALTER TABLE horarios_aula DROP FOREIGN KEY `' . $nome . '`');
            }
            $pdo->exec('ALTER TABLE horarios_aula MODIFY professor_id INT NULL');
            $pdo->exec(
                'ALTER TABLE horarios_aula
                 ADD CONSTRAINT horarios_professor
                 FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE SET NULL'
            );
        } catch (Throwable $e) {
            // A grade continua utilizável mesmo se a chave estrangeira não puder ser recriada.
        }
    }

    $turmas = $pdo->query('SELECT id FROM turmas WHERE horario_aplicado = 0')->fetchAll();
    foreach ($turmas as $turma) {
        aplicarHorarioPadrao($pdo, (int) $turma['id']);
    }
}

function aplicarHorarioPadrao(PDO $pdo, int $turmaId, bool $substituir = false): bool
{
    if (!$substituir) {
        $flag = $pdo->prepare('SELECT horario_aplicado FROM turmas WHERE id = :id');
        $flag->execute(['id' => $turmaId]);
        $conta = $pdo->prepare('SELECT COUNT(*) FROM horarios_aula WHERE turma_id = :id');
        $conta->execute(['id' => $turmaId]);
        if ((int) $flag->fetchColumn() === 1 || (int) $conta->fetchColumn() > 0) {
            $pdo->prepare('UPDATE turmas SET horario_aplicado = 1 WHERE id = :id')->execute(['id' => $turmaId]);
            return false;
        }
    }

    $nomes = array_column(listarGradeDaTurma($pdo, $turmaId), 'nome');
    if (!$nomes) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        if ($substituir) {
            $pdo->prepare('DELETE FROM horarios_aula WHERE turma_id = :id')->execute(['id' => $turmaId]);
        }

        $inserir = $pdo->prepare(
            'INSERT INTO horarios_aula (turma_id, professor_id, materia, dia_semana, hora_inicio, hora_fim)
             VALUES (:turma, :professor, :materia, :dia, :inicio, :fim)'
        );
        foreach (montarQuadroAutomatico($nomes) as $aula) {
            $professorId = professorDaMateria($pdo, $turmaId, $aula['materia']);
            $inserir->bindValue('turma', $turmaId, PDO::PARAM_INT);
            if ($professorId === null) {
                $inserir->bindValue('professor', null, PDO::PARAM_NULL);
            } else {
                $inserir->bindValue('professor', $professorId, PDO::PARAM_INT);
            }
            $inserir->bindValue('materia', $aula['materia']);
            $inserir->bindValue('dia', $aula['dia'], PDO::PARAM_INT);
            $inserir->bindValue('inicio', $aula['inicio'] . ':00');
            $inserir->bindValue('fim', $aula['fim'] . ':00');
            $inserir->execute();
        }

        $pdo->prepare('UPDATE turmas SET horario_aplicado = 1 WHERE id = :id')->execute(['id' => $turmaId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return true;
}

function aulaDoSlot(PDO $pdo, int $turmaId, int $dia, string $inicio, int $ignorarId = 0): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM horarios_aula
         WHERE turma_id = :turma AND dia_semana = :dia AND hora_inicio = :inicio AND id <> :id
         LIMIT 1'
    );
    $stmt->execute([
        'turma'  => $turmaId,
        'dia'    => $dia,
        'inicio' => substr($inicio, 0, 5) . ':00',
        'id'     => $ignorarId,
    ]);
    $aula = $stmt->fetch();

    return $aula ?: null;
}

function moverAulaNoQuadro(PDO $pdo, int $turmaId, int $origemId, int $dia, string $inicio): bool
{
    $slot = slotDoQuadro($dia, $inicio);
    if (!$slot || $origemId <= 0) {
        return false;
    }

    $origem = $pdo->prepare('SELECT * FROM horarios_aula WHERE id = :id AND turma_id = :turma LIMIT 1');
    $origem->execute(['id' => $origemId, 'turma' => $turmaId]);
    $aula = $origem->fetch();
    if (!$aula) {
        return false;
    }

    $destino = aulaDoSlot($pdo, $turmaId, $dia, $slot['inicio'], $origemId);
    $atualizar = $pdo->prepare(
        'UPDATE horarios_aula
         SET dia_semana = :dia, hora_inicio = :inicio, hora_fim = :fim
         WHERE id = :id AND turma_id = :turma'
    );

    if ($destino) {
        $atualizar->execute([
            'dia'    => (int) $aula['dia_semana'],
            'inicio' => $aula['hora_inicio'],
            'fim'    => $aula['hora_fim'],
            'id'     => (int) $destino['id'],
            'turma'  => $turmaId,
        ]);
    }

    $atualizar->execute([
        'dia'    => $dia,
        'inicio' => $slot['inicio'] . ':00',
        'fim'    => $slot['fim'] . ':00',
        'id'     => $origemId,
        'turma'  => $turmaId,
    ]);

    return true;
}

function definirAulaNoQuadro(PDO $pdo, int $turmaId, int $dia, string $inicio, string $materia): bool
{
    $slot = slotDoQuadro($dia, $inicio);
    if (!$slot) {
        return false;
    }

    $materia = mb_substr(trim($materia), 0, 100);
    $nomes = array_column(listarGradeDaTurma($pdo, $turmaId), 'nome');
    if ($materia !== '' && !in_array($materia, $nomes, true)) {
        return false;
    }

    $atual = aulaDoSlot($pdo, $turmaId, $dia, $slot['inicio']);
    if ($materia === '') {
        if ($atual) {
            $pdo->prepare('DELETE FROM horarios_aula WHERE id = :id AND turma_id = :turma')
                ->execute(['id' => (int) $atual['id'], 'turma' => $turmaId]);
        }
        return true;
    }

    $professorId = professorDaMateria($pdo, $turmaId, $materia);
    if ($atual) {
        $stmt = $pdo->prepare(
            'UPDATE horarios_aula SET materia = :materia, professor_id = :professor WHERE id = :id AND turma_id = :turma'
        );
        $stmt->bindValue('materia', $materia);
        if ($professorId === null) {
            $stmt->bindValue('professor', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue('professor', $professorId, PDO::PARAM_INT);
        }
        $stmt->bindValue('id', (int) $atual['id'], PDO::PARAM_INT);
        $stmt->bindValue('turma', $turmaId, PDO::PARAM_INT);
        $stmt->execute();
        return true;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO horarios_aula (turma_id, professor_id, materia, dia_semana, hora_inicio, hora_fim)
         VALUES (:turma, :professor, :materia, :dia, :inicio, :fim)'
    );
    $stmt->bindValue('turma', $turmaId, PDO::PARAM_INT);
    if ($professorId === null) {
        $stmt->bindValue('professor', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue('professor', $professorId, PDO::PARAM_INT);
    }
    $stmt->bindValue('materia', $materia);
    $stmt->bindValue('dia', $dia, PDO::PARAM_INT);
    $stmt->bindValue('inicio', $slot['inicio'] . ':00');
    $stmt->bindValue('fim', $slot['fim'] . ':00');
    $stmt->execute();

    return true;
}

function esvaziarAulaNoQuadro(PDO $pdo, int $turmaId, int $horarioId): bool
{
    if ($horarioId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM horarios_aula WHERE id = :id AND turma_id = :turma');
    $stmt->execute(['id' => $horarioId, 'turma' => $turmaId]);

    return $stmt->rowCount() > 0;
}

function processarQuadroHorario(PDO $pdo, array $post, int $turmaId, array &$erros): ?string
{
    $acao = (string) ($post['acao'] ?? '');

    if ($acao === 'gerar_horario') {
        if (!aplicarHorarioPadrao($pdo, $turmaId, true)) {
            $erros[] = 'Cadastre as disciplinas da turma antes de gerar a grade.';
            return null;
        }
        return 'horario_gerado';
    }

    if ($acao === 'mover_horario') {
        $ok = moverAulaNoQuadro(
            $pdo,
            $turmaId,
            (int) ($post['horario_id'] ?? 0),
            (int) ($post['dia_semana'] ?? 0),
            (string) ($post['hora_inicio'] ?? '')
        );
        if (!$ok) {
            $erros[] = 'Não foi possível mover essa aula.';
            return null;
        }
        return 'horario_quadro';
    }

    if ($acao === 'definir_horario') {
        $ok = definirAulaNoQuadro(
            $pdo,
            $turmaId,
            (int) ($post['dia_semana'] ?? 0),
            (string) ($post['hora_inicio'] ?? ''),
            (string) ($post['materia'] ?? '')
        );
        if (!$ok) {
            $erros[] = 'Não foi possível alterar essa aula.';
            return null;
        }
        return 'horario_quadro';
    }

    if ($acao === 'esvaziar_horario') {
        if (!esvaziarAulaNoQuadro($pdo, $turmaId, (int) ($post['horario_id'] ?? 0))) {
            $erros[] = 'Não foi possível esvaziar esse horário.';
            return null;
        }
        return 'horario_quadro';
    }

    return null;
}
