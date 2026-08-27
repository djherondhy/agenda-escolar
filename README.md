# Sistema de Calendário Estudantil

Sistema web para organização da rotina escolar. Professores cadastram provas, trabalhos e tarefas para suas turmas. Alunos acompanham prazos no calendário e marcam atividades como concluídas.

## Requisitos

- PHP 8+
- MySQL
- Apache
- XAMPP
- Navegador

## Instalação

1. Instale o XAMPP.
2. Ative o Apache e o MySQL no painel do XAMPP.
3. Copie o projeto para:

```text
C:\xampp\htdocs\c-estudantil
```

4. Crie o banco `calendario_estudantil` e importe os dados:

- Abra o phpMyAdmin: `http://localhost/phpmyadmin`
- Importe o arquivo `database/calendario_estudantil.sql`

Ou, no Prompt de Comando:

```text
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < C:\xampp\htdocs\c-estudantil\database\calendario_estudantil.sql
```

5. Confira as credenciais do banco em `config/database.php`.

O padrão do XAMPP é:

```php
DB_HOST = localhost
DB_NAME = calendario_estudantil
DB_USER = root
DB_PASS = (vazio)
```

Se a pasta do projeto for diferente de `c-estudantil`, ajuste também o `BASE_URL` nesse arquivo.

6. Acesse:

```text
http://localhost/c-estudantil
```

## Usuários de teste

| Nome | E-mail | Senha | Perfil |
| --- | --- | --- | --- |
| Administrador | admin@admin.com | admin | Administrador |
| João da Silva | joao@escola.com | senha123 | Professor |
| Maria Oliveira | maria@escola.com | senha123 | Professor |
| Carlos Souza | carlos@escola.com | senha123 | Aluno |
| Ana Pereira | ana@escola.com | senha123 | Aluno |
| Pedro Santos | pedro@escola.com | senha123 | Aluno |
| Lucas Almeida | lucas@escola.com | senha123 | Aluno |

## Funcionalidades

### Professor

- Login e dashboard com totais da turma
- Visualização das turmas associadas
- Cadastro, edição e exclusão de atividades
- Associação da atividade a uma turma, data, horário e tipo

### Aluno

- Login e dashboard com pendências da própria turma
- Calendário mensal com indicadores por dia
- Filtro por tipo (prova, trabalho, tarefa, outro) e status
- Conclusão individual da atividade, sem alterar o status dos colegas

### Administrador

- Login em `/admin/dashboard.php`
- Cadastro, edição e exclusão de alunos, professores e outros administradores
- Associação de aluno a uma turma e de professor a uma ou mais turmas
- Cadastro de turmas

## Estrutura

```text
c-estudantil/
├── config/database.php
├── includes/
├── assets/
├── admin/
├── professor/
├── aluno/
├── database/calendario_estudantil.sql
├── index.php
├── login.php
└── logout.php
```

## Segurança

- Senhas gravadas com `password_hash()` e conferidas com `password_verify()`
- Consultas com PDO e prepared statements
- Páginas protegidas por sessão e perfil
- Saída HTML escapada com `htmlspecialchars()`
- Exclusão de atividade exige confirmação
