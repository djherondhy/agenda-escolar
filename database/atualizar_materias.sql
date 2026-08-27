USE calendario_estudantil;

-- Preferível deixar o PHP em config/database.php aplicar esta mudança
-- na primeira carga, para não quebrar as chaves estrangeiras.

ALTER TABLE professor_turmas
    ADD COLUMN materia VARCHAR(100) NOT NULL DEFAULT '' AFTER turma_id;
