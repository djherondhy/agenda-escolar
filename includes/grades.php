<?php

/**
 * Grade padrão do ensino médio integrado do IFAM.
 * Formação geral: núcleo básico comum aos cursos integrados.
 * Formação técnica e núcleo politécnico: PPC de cada curso.
 * Administração — campus Presidente Figueiredo.
 * Informática — campi Humaitá e Manaus Centro.
 * Agropecuária — campus Humaitá.
 */

function rotuloNucleo(string $nucleo): string
{
    return match ($nucleo) {
        'politecnico' => 'Núcleo politécnico',
        'tecnico'     => 'Formação técnica',
        default       => 'Formação geral',
    };
}

function cursosDaGrade(): array
{
    return [
        'informatica'    => 'Informática',
        'administracao'  => 'Administração',
        'agropecuaria'   => 'Agropecuária',
    ];
}

function serieDaTurma(string $nome): ?int
{
    $texto = mb_strtolower($nome);
    if (preg_match('/3\s*[º°o]|terceiro/u', $texto)) {
        return 3;
    }
    if (preg_match('/2\s*[º°o]|segundo/u', $texto)) {
        return 2;
    }
    if (preg_match('/1\s*[º°o]|primeiro/u', $texto)) {
        return 1;
    }

    return null;
}

function cursoDaTurma(string $nome): ?string
{
    $texto = mb_strtolower($nome);
    if (str_contains($texto, 'inform')) {
        return 'informatica';
    }
    if (str_contains($texto, 'admin')) {
        return 'administracao';
    }
    if (str_contains($texto, 'agro')) {
        return 'agropecuaria';
    }

    return null;
}

function formacaoGeralIfam(int $serie): array
{
    $comuns = [
        'Língua Portuguesa e Literatura',
        'Arte',
        'Língua Inglesa',
        'Educação Física',
        'Matemática',
        'Biologia',
        'Física',
        'Química',
        'História',
        'Geografia',
        'Filosofia',
        'Sociologia',
    ];
    $porSerie = [
        1 => $comuns,
        2 => array_values(array_filter($comuns, static fn (string $nome): bool => $nome !== 'Arte')),
        3 => [
            'Língua Portuguesa e Literatura',
            'Matemática',
            'Biologia',
            'Física',
            'Química',
            'História',
            'Geografia',
        ],
    ];

    return $porSerie[$serie] ?? $comuns;
}

function gradeTecnicaIfam(string $curso, int $serie): array
{
    $grades = [
        'informatica' => [
            1 => [
                'politecnico' => ['Elaboração de Documentos Técnicos e Científicos'],
                'tecnico' => [
                    'Lógica de Programação e Estrutura de Dados',
                    'Montagem e Manutenção de Computadores',
                    'Fundamentos de Redes de Computadores',
                    'Fundamentos de Sistemas Operacionais',
                ],
            ],
            2 => [
                'politecnico' => ['Projeto Integrador I'],
                'tecnico' => [
                    'Banco de Dados',
                    'Programação Orientada a Objetos',
                    'Introdução à Análise de Sistemas',
                    'Interação Humano-Computador',
                ],
            ],
            3 => [
                'politecnico' => ['Projeto Integrador II'],
                'tecnico' => [
                    'Meio Ambiente, Saúde e Segurança',
                    'Empreendedorismo, Relações Interpessoais e Ética',
                    'Programação para Dispositivos Móveis',
                    'Programação Web',
                ],
            ],
        ],
        'administracao' => [
            1 => [
                'politecnico' => [
                    'Tópicos Especiais de Informática',
                    'Metodologia da Pesquisa e Elaboração de Projetos',
                ],
                'tecnico' => [
                    'Teoria Geral da Administração',
                    'Introdução à Legislação Tributária, Trabalhista e Empresarial',
                ],
            ],
            2 => [
                'politecnico' => [
                    'Língua Espanhola',
                    'Projeto Integrador I',
                ],
                'tecnico' => [
                    'Introdução à Economia',
                    'Marketing',
                    'Contabilidade Básica e de Custos',
                    'Associativismo e Cooperativismo',
                ],
            ],
            3 => [
                'politecnico' => ['Projeto Integrador II — Ética e Cidadania'],
                'tecnico' => [
                    'Matemática e Estatística Aplicada',
                    'Ambiente, Saúde e Segurança',
                    'Empreendedorismo',
                    'Gestão de Pessoas',
                    'Gestão de Produção e Logística',
                    'Gestão Pública',
                    'Tópicos Especiais de Gestão',
                ],
            ],
        ],
        'agropecuaria' => [
            1 => [
                'politecnico' => [
                    'Informática Básica',
                    'Metodologia da Pesquisa e Elaboração de Projetos',
                ],
                'tecnico' => [
                    'Produção Vegetal I (Solos e Olericultura)',
                    'Produção Animal I (Aves e Suínos)',
                    'Ambiente, Saúde e Segurança',
                    'Desenho Técnico e Topografia',
                    'Empreendedorismo',
                ],
            ],
            2 => [
                'politecnico' => ['Projeto Integrador I'],
                'tecnico' => [
                    'Construções Rurais',
                    'Administração e Economia Rural',
                    'Produção Vegetal II (Culturas Anuais)',
                    'Irrigação e Drenagem',
                    'Produção Animal II (Ruminantes e Forragens)',
                    'Extensão Rural e Associativismo',
                ],
            ],
            3 => [
                'politecnico' => ['Projeto Integrador II'],
                'tecnico' => [
                    'Produção Vegetal III (Silvicultura e Fruticultura)',
                    'Produção Animal III (Aquicultura)',
                    'Agroindustrialização de Alimentos',
                    'Agroecologia',
                ],
            ],
        ],
    ];

    return $grades[$curso][$serie] ?? ['politecnico' => [], 'tecnico' => []];
}

function disciplinasPadraoIfam(string $curso, int $serie): array
{
    $lista = [];
    $ordem = 1;
    foreach (formacaoGeralIfam($serie) as $nome) {
        $lista[] = ['nome' => $nome, 'nucleo' => 'basico', 'ordem' => $ordem++];
    }
    $tecnica = gradeTecnicaIfam($curso, $serie);
    foreach (['politecnico', 'tecnico'] as $nucleo) {
        foreach ($tecnica[$nucleo] as $nome) {
            $lista[] = ['nome' => $nome, 'nucleo' => $nucleo, 'ordem' => $ordem++];
        }
    }

    return $lista;
}

function aplicarGradePadrao(PDO $pdo, int $turmaId, bool $substituir = false, ?string $curso = null, ?int $serie = null): bool
{
    $stmt = $pdo->prepare('SELECT nome FROM turmas WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $turmaId]);
    $nome = (string) $stmt->fetchColumn();
    if ($nome === '') {
        return false;
    }

    $curso = $curso ?: cursoDaTurma($nome);
    $serie = $serie ?: serieDaTurma($nome);
    if (!isset(cursosDaGrade()[$curso]) || !in_array($serie, [1, 2, 3], true)) {
        return false;
    }

    if (!$substituir) {
        $conta = $pdo->prepare('SELECT COUNT(*) FROM disciplinas_turma WHERE turma_id = :id');
        $conta->execute(['id' => $turmaId]);
        if ((int) $conta->fetchColumn() > 0) {
            $pdo->prepare('UPDATE turmas SET grade_aplicada = 1 WHERE id = :id')->execute(['id' => $turmaId]);
            return false;
        }
    } else {
        $pdo->prepare('DELETE FROM disciplinas_turma WHERE turma_id = :id')->execute(['id' => $turmaId]);
    }

    $inserir = $pdo->prepare(
        'INSERT INTO disciplinas_turma (turma_id, nome, nucleo, ordem)
         VALUES (:turma_id, :nome, :nucleo, :ordem)'
    );
    foreach (disciplinasPadraoIfam($curso, $serie) as $disciplina) {
        $inserir->execute([
            'turma_id' => $turmaId,
            'nome'     => $disciplina['nome'],
            'nucleo'   => $disciplina['nucleo'],
            'ordem'    => $disciplina['ordem'],
        ]);
    }
    $pdo->prepare('UPDATE turmas SET grade_aplicada = 1 WHERE id = :id')->execute(['id' => $turmaId]);

    return true;
}

function garantirGradesTurmas(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS disciplinas_turma (
            id INT AUTO_INCREMENT PRIMARY KEY,
            turma_id INT NOT NULL,
            nome VARCHAR(100) NOT NULL,
            nucleo VARCHAR(20) NOT NULL DEFAULT \'basico\',
            ordem INT NOT NULL DEFAULT 0,
            UNIQUE KEY disciplina_turma_nome (turma_id, nome),
            FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
        )'
    );

    $coluna = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'grade_aplicada'")->fetch();
    if (!$coluna) {
        $pdo->exec('ALTER TABLE turmas ADD COLUMN grade_aplicada TINYINT(1) NOT NULL DEFAULT 0');
    }

    $turmas = $pdo->query('SELECT id FROM turmas WHERE grade_aplicada = 0')->fetchAll();
    foreach ($turmas as $turma) {
        aplicarGradePadrao($pdo, (int) $turma['id']);
    }
}

function listarGradeDaTurma(PDO $pdo, int $turmaId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, nome, nucleo, ordem
         FROM disciplinas_turma
         WHERE turma_id = :turma_id
         ORDER BY ordem, nome'
    );
    $stmt->execute(['turma_id' => $turmaId]);

    return $stmt->fetchAll();
}

function renomearDisciplinaNaTurma(PDO $pdo, int $turmaId, string $atual, string $novo): void
{
    $pdo->prepare('UPDATE disciplinas_turma SET nome = :novo WHERE turma_id = :turma AND nome = :atual')
        ->execute(['novo' => $novo, 'turma' => $turmaId, 'atual' => $atual]);
    $pdo->prepare('UPDATE professor_turmas SET materia = :novo WHERE turma_id = :turma AND materia = :atual')
        ->execute(['novo' => $novo, 'turma' => $turmaId, 'atual' => $atual]);
    $pdo->prepare('UPDATE horarios_aula SET materia = :novo WHERE turma_id = :turma AND materia = :atual')
        ->execute(['novo' => $novo, 'turma' => $turmaId, 'atual' => $atual]);
    $pdo->prepare('UPDATE eventos SET materia = :novo WHERE turma_id = :turma AND materia = :atual')
        ->execute(['novo' => $novo, 'turma' => $turmaId, 'atual' => $atual]);
    $pdo->prepare(
        'UPDATE aluno_atividades a
         INNER JOIN alunos al ON al.id = a.aluno_id
         SET a.materia = :novo
         WHERE al.turma_id = :turma AND a.materia = :atual'
    )->execute(['novo' => $novo, 'turma' => $turmaId, 'atual' => $atual]);
}
