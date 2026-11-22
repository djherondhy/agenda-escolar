# Agenda Acadêmica

Sistema web para organização da rotina escolar. Representantes de turma cadastram provas, trabalhos, atividades e a grade de horários. Alunos acompanham prazos no calendário e marcam as próprias atividades como concluídas. Desenvolvedores e conselheiros administram usuários e turmas.

## Requisitos

- PHP 8+
- MySQL
- Apache
- XAMPP
- Navegador

## Instalação e configuração

O banco é configurado automaticamente por `config/env.php`, nesta ordem:

1. Variáveis de ambiente do servidor (`DATABASE_URL`, `MYSQL_URL`, `DB_*`, `MYSQL*`)
2. Arquivo `.env` na raiz do projeto
3. Padrão do XAMPP local (`root`, sem senha, banco `calendario_estudantil`)

### Produção (Hostinger)

1. Publique os arquivos (por exemplo em `public_html`).
2. Confira o `.env` com `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASS`.
3. Acesse o site. Na primeira requisição as tabelas são criadas, as contas de teste antigas são removidas e o administrador padrão é garantido.

O `BASE_URL` é detectado sozinho; use `APP_BASE_URL` no `.env` apenas se precisar forçar um valor.

### Local (XAMPP)

1. Copie o projeto para `C:\xampp\htdocs\c-estudantil` e ative Apache e MySQL.
2. Renomeie/remova o `.env` (ou aponte-o para o MySQL local).
3. Acesse `http://localhost/c-estudantil`. O banco é criado na primeira execução.

## Acesso inicial

| E-mail | Senha | Perfil |
| --- | --- | --- |
| admin@admin.com | adminadmin | Administrador |

Altere a senha após o primeiro acesso. Alunos e representantes são cadastrados pelo administrador.

Ao cadastrar um usuário **sem senha**, ela fica como `ifam2026` e aparece um aviso para a pessoa criar a própria senha em **Meu perfil** (ícone no topo da tela). Lá também dá para enviar a foto de perfil.

## Notificações

O sino (aluno e representante) guarda estes avisos:

- atividade nova na turma
- publicação ou comentário no mural e nas salas de estudo (quem escreveu não recebe o próprio aviso)
- lembrete no dia anterior de cada atividade ainda pendente

O mesmo texto pode chegar no celular por Web Push. É preciso **HTTPS** (já ativo em produção). Em **Notificações**, toque em **Ativar avisos neste aparelho**; **Enviar aviso de teste** confirma o aparelho.

- Android e computador: Chrome, Edge ou Firefox, basta permitir.
- iPhone/iPad (iOS 16.4+): abrir no Safari, **Compartilhar → Adicionar à Tela de Início**, abrir pelo ícone e ativar os avisos.
- O QR Code da página inicial usa o `APP_URL` do `.env`. Deixe `APP_URL=https://academico.wararadev.com` para o link do aviso no celular apontar para o site certo, inclusive quando o lembrete sai pelo cron.

### Lembrete da véspera na Hostinger

O lembrete sai sozinho na primeira visita do dia. Para garantir o envio mesmo sem ninguém no site, crie um Cron Job no hPanel, tipo **PHP**, uma vez por dia (por exemplo 08:00, horário de Manaus):

```text
php /home/SEU_USUARIO/domains/academico.wararadev.com/public_html/cron/lembretes.php
```

Troque `SEU_USUARIO` e a pasta pelo caminho real (Gerenciador de arquivos do hPanel mostra o caminho). Se o painel só aceitar uma URL:

```text
https://academico.wararadev.com/cron/lembretes.php?token=O_TOKEN
```

O token é criado em `config/cron.php` na primeira execução do comando PHP acima. Cada atividade é avisada uma vez só.

## Funcionalidades

### Representante de turma

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
