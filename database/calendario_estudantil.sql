CREATE DATABASE IF NOT EXISTS calendario_estudantil
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE calendario_estudantil;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS aluno_eventos;
DROP TABLE IF EXISTS evento_anexos;
DROP TABLE IF EXISTS eventos;
DROP TABLE IF EXISTS professor_turmas;
DROP TABLE IF EXISTS alunos;
DROP TABLE IF EXISTS professores;
DROP TABLE IF EXISTS turmas;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('professor', 'aluno', 'administrador') NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    ano VARCHAR(20),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE professores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    turma_id INT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
);

CREATE TABLE professor_turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,
    turma_id INT NOT NULL,
    materia VARCHAR(100) NOT NULL DEFAULT '',
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    UNIQUE KEY professor_turma_materia (professor_id, turma_id, materia)
);

CREATE TABLE eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,
    turma_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT,
    tipo ENUM('prova', 'trabalho', 'tarefa', 'outro') NOT NULL,
    data_evento DATE NOT NULL,
    hora TIME NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
);

CREATE TABLE evento_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    tamanho INT NOT NULL DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
);

CREATE TABLE aluno_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    evento_id INT NOT NULL,
    concluido BOOLEAN DEFAULT FALSE,
    data_conclusao DATETIME NULL,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    UNIQUE (aluno_id, evento_id)
);

-- Senha de todos os usuários de teste: senha123
INSERT INTO usuarios (id, nome, email, senha, tipo) VALUES
(1, 'João da Silva', 'joao@escola.com', '$2y$10$AYw97HR9L42AyUpuAtYfkup67zr8wMh.dEmctu4OTWpn9iK7ZRb3W', 'professor'),
(2, 'Maria Oliveira', 'maria@escola.com', '$2y$10$AYw97HR9L42AyUpuAtYfkup67zr8wMh.dEmctu4OTWpn9iK7ZRb3W', 'professor'),
(3, 'Carlos Souza', 'carlos@escola.com', '$2y$10$AYw97HR9L42AyUpuAtYfkup67zr8wMh.dEmctu4OTWpn9iK7ZRb3W', 'aluno'),
(4, 'Ana Pereira', 'ana@escola.com', '$2y$10$AYw97HR9L42AyUpuAtYfkup67zr8wMh.dEmctu4OTWpn9iK7ZRb3W', 'aluno'),
(5, 'Pedro Santos', 'pedro@escola.com', '$2y$10$AYw97HR9L42AyUpuAtYfkup67zr8wMh.dEmctu4OTWpn9iK7ZRb3W', 'aluno'),
(6, 'Lucas Almeida', 'lucas@escola.com', '$2y$10$AYw97HR9L42AyUpuAtYfkup67zr8wMh.dEmctu4OTWpn9iK7ZRb3W', 'aluno'),
(7, 'Administrador', 'admin@admin.com', '$2y$10$q.D4lIpO40hAhNc04vp/hOoAKcdsmGD9fAMc3yRdny0tTPO27RNse', 'administrador');

INSERT INTO turmas (id, nome, ano) VALUES
(1, '1º Ano Informática', '2026'),
(2, '2º Ano Informática', '2026'),
(3, '3º Ano Informática', '2026'),
(4, '1º Ano Administração', '2026'),
(5, '2º Ano Administração', '2026'),
(6, '3º Ano Administração', '2026'),
(7, '1º Ano Agropecuária', '2026'),
(8, '2º Ano Agropecuária', '2026'),
(9, '3º Ano Agropecuária', '2026');

INSERT INTO professores (id, usuario_id) VALUES
(1, 1),
(2, 2);

INSERT INTO alunos (id, usuario_id, turma_id) VALUES
(1, 3, 2),
(2, 4, 2),
(3, 5, 1),
(4, 6, 3);

INSERT INTO professor_turmas (professor_id, turma_id, materia) VALUES
(1, 1, 'PHP'),
(1, 1, 'Lógica'),
(1, 2, 'Matemática'),
(1, 2, 'História'),
(1, 4, ''),
(2, 2, 'Banco de Dados'),
(2, 3, 'Redes'),
(2, 3, 'Inglês'),
(2, 8, ''),
(2, 9, '');

INSERT INTO eventos (id, professor_id, turma_id, titulo, descricao, tipo, data_evento, hora) VALUES
(1, 1, 2, 'Prova de Matemática', 'Avaliação sobre funções, equações e interpretação gráfica.', 'prova', '2026-08-28', '08:00:00'),
(2, 1, 2, 'Trabalho de História', 'Pesquisa sobre a Revolução Industrial e seus impactos sociais.', 'trabalho', '2026-09-02', NULL),
(3, 1, 1, 'Atividade de PHP', 'Criar um formulário com validação e inserção no banco de dados.', 'tarefa', '2026-09-05', '10:00:00'),
(4, 2, 2, 'Seminário de Banco de Dados', 'Apresentação em grupo sobre modelagem relacional e SQL.', 'trabalho', '2026-09-10', '14:00:00'),
(5, 2, 3, 'Prova de Redes', 'Avaliação sobre protocolos TCP/IP, endereçamento e topologias.', 'prova', '2026-09-03', '10:00:00'),
(6, 1, 1, 'Tarefa de Lógica', 'Resolver a lista de exercícios sobre estruturas de decisão e repetição.', 'tarefa', '2026-08-20', NULL),
(7, 2, 3, 'Trabalho de Inglês', 'Produzir um texto sobre rotina escolar em inglês.', 'trabalho', '2026-09-15', NULL),
(8, 1, 2, 'Reunião de orientação', 'Encontro para alinhar o cronograma do segundo semestre.', 'outro', '2026-08-30', '19:00:00');

INSERT INTO aluno_eventos (aluno_id, evento_id, concluido, data_conclusao) VALUES
(2, 2, 1, '2026-08-26 11:00:00'),
(3, 6, 1, '2026-08-19 21:10:00'),
(3, 3, 1, '2026-08-25 16:00:00');
