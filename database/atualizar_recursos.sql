USE calendario_estudantil;

CREATE TABLE IF NOT EXISTS evento_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    tamanho INT NOT NULL DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
);

INSERT INTO turmas (nome, ano)
SELECT '1º Ano Administração', '2026' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '1º Ano Administração');
INSERT INTO turmas (nome, ano)
SELECT '2º Ano Administração', '2026' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '2º Ano Administração');
INSERT INTO turmas (nome, ano)
SELECT '3º Ano Administração', '2026' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '3º Ano Administração');
INSERT INTO turmas (nome, ano)
SELECT '1º Ano Agropecuária', '2026' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '1º Ano Agropecuária');
INSERT INTO turmas (nome, ano)
SELECT '2º Ano Agropecuária', '2026' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '2º Ano Agropecuária');
INSERT INTO turmas (nome, ano)
SELECT '3º Ano Agropecuária', '2026' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '3º Ano Agropecuária');

INSERT IGNORE INTO professor_turmas (professor_id, turma_id)
SELECT 1, id FROM turmas WHERE nome = '1º Ano Administração';
INSERT IGNORE INTO professor_turmas (professor_id, turma_id)
SELECT 2, id FROM turmas WHERE nome IN ('2º Ano Agropecuária', '3º Ano Agropecuária');
