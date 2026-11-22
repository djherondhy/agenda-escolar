CREATE DATABASE IF NOT EXISTS calendario_estudantil
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE calendario_estudantil;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS salas_comentarios;
DROP TABLE IF EXISTS salas_anexos;
DROP TABLE IF EXISTS salas_posts;
DROP TABLE IF EXISTS salas_inscricoes;
DROP TABLE IF EXISTS salas_estudo;
DROP TABLE IF EXISTS mural_comentarios;
DROP TABLE IF EXISTS mural_anexos;
DROP TABLE IF EXISTS mural_posts;
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
    representante TINYINT(1) NOT NULL DEFAULT 0,
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
    tipo ENUM('prova', 'trabalho', 'atividade', 'reuniao') NOT NULL,
    concluido TINYINT(1) NOT NULL DEFAULT 0,
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

CREATE TABLE mural_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turma_id INT NOT NULL,
    autor_id INT NOT NULL,
    texto TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE mural_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(120) NOT NULL,
    tamanho INT NOT NULL DEFAULT 0,
    FOREIGN KEY (post_id) REFERENCES mural_posts(id) ON DELETE CASCADE
);

CREATE TABLE mural_comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    autor_id INT NOT NULL,
    texto TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES mural_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE salas_estudo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    tema VARCHAR(150) NOT NULL DEFAULT '',
    descricao TEXT,
    icone VARCHAR(30) NOT NULL DEFAULT '',
    exemplo TINYINT(1) NOT NULL DEFAULT 0,
    criador_id INT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criador_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE salas_inscricoes (
    sala_id INT NOT NULL,
    usuario_id INT NOT NULL,
    admin TINYINT(1) NOT NULL DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sala_id, usuario_id),
    FOREIGN KEY (sala_id) REFERENCES salas_estudo(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE salas_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    autor_id INT NOT NULL,
    texto TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id) REFERENCES salas_estudo(id) ON DELETE CASCADE,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE salas_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(120) NOT NULL,
    tamanho INT NOT NULL DEFAULT 0,
    FOREIGN KEY (post_id) REFERENCES salas_posts(id) ON DELETE CASCADE
);

CREATE TABLE salas_comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    autor_id INT NOT NULL,
    texto TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES salas_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE app_marcadores (
    chave VARCHAR(60) PRIMARY KEY
);

-- Administrador padrão (senha: adminadmin). Altere a senha após o primeiro acesso.
INSERT INTO usuarios (id, nome, email, senha, tipo) VALUES
(1, 'Administrador', 'admin@admin.com', '$2y$10$SRQsXWDjnjY/azwHDt4bO.L63dsisaZMZENaFOx.EXdEB.BXUYdaG', 'administrador');

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