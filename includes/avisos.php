<?php

/**
 * Avisos dentro do sistema e no celular.
 * Atividade nova, publicação, comentário e lembrete no dia anterior.
 */

function resumoAviso(string $texto, int $limite = 140): string
{
    $limpo = trim(preg_replace('/\s+/', ' ', strip_tags($texto)) ?? '');
    if (mb_strlen($limpo) <= $limite) {
        return $limpo;
    }

    return rtrim(mb_substr($limpo, 0, $limite - 1)) . '…';
}

function dataAmanhaEscola(): string
{
    $amanha = new DateTimeImmutable('tomorrow', new DateTimeZone('America/Manaus'));

    return $amanha->format('Y-m-d');
}

/**
 * @return list<array{usuario_id:int,aluno_id:?int,tipo:string}>
 */
function destinatariosDaTurma(PDO $pdo, int $turmaId, bool $somenteAlunos = false): array
{
    $lista = [];
    $stmt = $pdo->prepare(
        'SELECT a.usuario_id, a.id AS aluno_id, u.tipo
         FROM alunos a
         INNER JOIN usuarios u ON u.id = a.usuario_id
         WHERE a.turma_id = :turma'
    );
    $stmt->execute(['turma' => $turmaId]);
    foreach ($stmt->fetchAll() as $linha) {
        $lista[(int) $linha['usuario_id']] = [
            'usuario_id' => (int) $linha['usuario_id'],
            'aluno_id'   => (int) $linha['aluno_id'],
            'tipo'       => (string) $linha['tipo'],
        ];
    }

    if ($somenteAlunos) {
        return array_values($lista);
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT p.usuario_id, u.tipo
         FROM professor_turmas pt
         INNER JOIN professores p ON p.id = pt.professor_id
         INNER JOIN usuarios u ON u.id = p.usuario_id
         WHERE pt.turma_id = :turma'
    );
    $stmt->execute(['turma' => $turmaId]);
    foreach ($stmt->fetchAll() as $linha) {
        $id = (int) $linha['usuario_id'];
        if (isset($lista[$id])) {
            continue;
        }
        $lista[$id] = [
            'usuario_id' => $id,
            'aluno_id'   => null,
            'tipo'       => (string) $linha['tipo'],
        ];
    }

    return array_values($lista);
}

/**
 * @return list<array{usuario_id:int,aluno_id:?int,tipo:string}>
 */
function destinatariosDaSala(PDO $pdo, int $salaId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.usuario_id, a.id AS aluno_id, u.tipo
         FROM salas_inscricoes s
         INNER JOIN usuarios u ON u.id = s.usuario_id
         LEFT JOIN alunos a ON a.usuario_id = s.usuario_id
         WHERE s.sala_id = :sala'
    );
    $stmt->execute(['sala' => $salaId]);
    $lista = [];
    foreach ($stmt->fetchAll() as $linha) {
        $lista[(int) $linha['usuario_id']] = [
            'usuario_id' => (int) $linha['usuario_id'],
            'aluno_id'   => $linha['aluno_id'] !== null ? (int) $linha['aluno_id'] : null,
            'tipo'       => (string) $linha['tipo'],
        ];
    }

    return array_values($lista);
}

function caminhoDoAviso(array $pessoa, string $contexto, int $alvoId): string
{
    if ($contexto === 'sala') {
        return '/aluno/sala.php?id=' . $alvoId;
    }

    if (($pessoa['tipo'] ?? '') === 'professor') {
        return '/professor/mural.php?turma=' . $alvoId;
    }

    return '/aluno/dashboard.php';
}

/**
 * Grava o sino e dispara o push. Quem escreveu não entra na lista.
 *
 * @param list<array{usuario_id:int,aluno_id:?int,tipo:string}> $pessoas
 */
function avisarPessoas(PDO $pdo, array $pessoas, int $autorId, array $aviso): void
{
    $insert = $pdo->prepare(
        'INSERT INTO notificacoes (usuario_id, aluno_id, tipo, evento_id, titulo, mensagem, caminho)
         VALUES (:usuario_id, :aluno_id, :tipo, :evento_id, :titulo, :mensagem, :caminho)'
    );
    $porCaminho = [];

    foreach ($pessoas as $pessoa) {
        if ((int) $pessoa['usuario_id'] === $autorId) {
            continue;
        }
        $caminho = $pessoa['caminho'] ?? $aviso['caminho'];
        $insert->execute([
            'usuario_id' => (int) $pessoa['usuario_id'],
            'aluno_id'   => $pessoa['aluno_id'],
            'tipo'       => $aviso['tipo'],
            'evento_id'  => $aviso['evento_id'],
            'titulo'     => $aviso['titulo'],
            'mensagem'   => $aviso['mensagem'],
            'caminho'    => $caminho,
        ]);
        $porCaminho[$caminho][] = (int) $pessoa['usuario_id'];
    }

    if (!$porCaminho) {
        return;
    }

    require_once __DIR__ . '/push.php';
    try {
        foreach ($porCaminho as $caminho => $ids) {
            enviarPushParaUsuarios($pdo, $ids, montarPayloadPush(
                $aviso['titulo'],
                $aviso['mensagem'],
                $caminho,
                $aviso['tag']
            ));
        }
    } catch (Throwable $e) {
        // O sino fica gravado mesmo se o celular não receber.
    }
}

function avisarNovaPublicacao(PDO $pdo, string $contexto, int $alvoId, int $autorId, string $autorNome, string $texto): void
{
    if ($contexto === 'sala') {
        $pessoas = destinatariosDaSala($pdo, $alvoId);
        $sala = obterSala($pdo, $alvoId);
        $onde = $sala ? $sala['titulo'] : 'sala de estudo';
        $titulo = 'Nova publicação em ' . $onde;
    } else {
        $pessoas = destinatariosDaTurma($pdo, $alvoId);
        $titulo = 'Nova publicação no mural';
    }

    foreach ($pessoas as &$pessoa) {
        $pessoa['caminho'] = caminhoDoAviso($pessoa, $contexto, $alvoId);
    }
    unset($pessoa);

    avisarPessoas($pdo, $pessoas, $autorId, [
        'tipo'      => 'post',
        'evento_id' => null,
        'titulo'    => $titulo,
        'mensagem'  => resumoAviso($autorNome . ': ' . $texto),
        'caminho'   => caminhoDoAviso(['tipo' => 'aluno'], $contexto, $alvoId),
        'tag'       => 'post-' . $contexto . '-' . $alvoId,
    ]);
}

function avisarNovoComentario(PDO $pdo, string $contexto, int $alvoId, int $autorId, string $autorNome, string $texto): void
{
    if ($contexto === 'sala') {
        $pessoas = destinatariosDaSala($pdo, $alvoId);
        $sala = obterSala($pdo, $alvoId);
        $onde = $sala ? $sala['titulo'] : 'sala de estudo';
        $titulo = 'Novo comentário em ' . $onde;
    } else {
        $pessoas = destinatariosDaTurma($pdo, $alvoId);
        $titulo = 'Novo comentário no mural';
    }

    foreach ($pessoas as &$pessoa) {
        $pessoa['caminho'] = caminhoDoAviso($pessoa, $contexto, $alvoId);
    }
    unset($pessoa);

    avisarPessoas($pdo, $pessoas, $autorId, [
        'tipo'      => 'comentario',
        'evento_id' => null,
        'titulo'    => $titulo,
        'mensagem'  => resumoAviso($autorNome . ': ' . $texto),
        'caminho'   => caminhoDoAviso(['tipo' => 'aluno'], $contexto, $alvoId),
        'tag'       => 'comentario-' . $contexto . '-' . $alvoId,
    ]);
}

/**
 * Uma vez por atividade: avisa a turma no dia anterior.
 * Roda na primeira página do dia e também pelo cron da Hostinger.
 */
function enviarLembretesVespera(PDO $pdo): int
{
    static $feito = false;
    static $total = 0;
    if ($feito) {
        return $total;
    }
    if (!function_exists('formatarHora') || !function_exists('rotuloTipo')) {
        return 0;
    }
    $feito = true;

    try {
        $coluna = $pdo->query("SHOW COLUMNS FROM eventos LIKE 'lembrete_vespera_em'")->fetch();
    } catch (Throwable $e) {
        return 0;
    }
    if (!$coluna) {
        return 0;
    }

    $amanha = dataAmanhaEscola();
    $stmt = $pdo->prepare(
        'SELECT e.id, e.titulo, e.tipo, e.hora, e.turma_id, e.professor_id
         FROM eventos e
         WHERE e.data_evento = :amanha AND e.concluido = 0 AND e.lembrete_vespera_em IS NULL'
    );
    $stmt->execute(['amanha' => $amanha]);
    $eventos = $stmt->fetchAll();
    if (!$eventos) {
        return 0;
    }

    $enviados = 0;
    $marcar = $pdo->prepare('UPDATE eventos SET lembrete_vespera_em = NOW() WHERE id = :id AND lembrete_vespera_em IS NULL');

    foreach ($eventos as $evento) {
        $hora = $evento['hora'] ? ' às ' . formatarHora($evento['hora']) : '';
        $pessoas = destinatariosDaTurma($pdo, (int) $evento['turma_id'], true);
        foreach ($pessoas as &$pessoa) {
            $pessoa['caminho'] = '/aluno/atividade.php?id=' . (int) $evento['id'];
        }
        unset($pessoa);

        avisarPessoas($pdo, $pessoas, 0, [
            'tipo'      => 'lembrete',
            'evento_id' => (int) $evento['id'],
            'titulo'    => 'Amanhã: ' . $evento['titulo'],
            'mensagem'  => rotuloTipo($evento['tipo']) . $hora,
            'caminho'   => '/aluno/atividade.php?id=' . (int) $evento['id'],
            'tag'       => 'vespera-' . (int) $evento['id'],
        ]);
        $marcar->execute(['id' => (int) $evento['id']]);
        $enviados++;
    }

    $total = $enviados;

    return $total;
}
