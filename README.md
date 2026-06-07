# EduvFinance

EduvFinance é uma aplicação web de educação financeira desenvolvida em PHP com PostgreSQL. O sistema permite que alunos se cadastrem, acessem cursos e aulas, acompanhem trilhas de aprendizagem, registrem progresso, avaliem cursos e usem um simulador de investimentos. Também possui áreas específicas para professores criarem cursos com um editor visual em etapas e para administradores aprovarem conteúdos e gerenciarem usuários.

## Sumário

- [Visão geral](#visão-geral)
- [Tecnologias](#tecnologias)
- [Funcionalidades](#funcionalidades)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Requisitos](#requisitos)
- [Como executar com Docker](#como-executar-com-docker)
- [Acessos iniciais](#acessos-iniciais)
- [Configuração de ambiente](#configuração-de-ambiente)
- [Banco de dados](#banco-de-dados)
- [Páginas principais](#páginas-principais)
- [Endpoints principais](#endpoints-principais)
- [Fluxos do sistema](#fluxos-do-sistema)
- [Uploads](#uploads)
- [Comandos úteis](#comandos-úteis)
- [Observações](#observações)

## Visão geral

O projeto é organizado como uma aplicação web tradicional:

- Frontend em HTML, CSS e JavaScript puro.
- Backend em PHP, usando PDO para conexão com PostgreSQL.
- Banco de dados PostgreSQL com schema versionado em SQL.
- Execução local simplificada com Docker Compose.
- Autenticação baseada em sessão PHP.
- Separação de acesso por perfil: administrador, aluno e professor.

## Tecnologias

- PHP 8.2
- Apache HTTP Server
- PostgreSQL 16
- PDO PostgreSQL (`pdo_pgsql`)
- HTML5
- CSS3 (modular: variables, layout, components, animations, responsive)
- JavaScript
- Chart.js via CDN
- Lucide Icons via CDN
- Docker
- Docker Compose

## Funcionalidades

### Aluno

- Cadastro e login.
- Dashboard com resumo de progresso.
- Visualização de cursos disponíveis.
- Matrícula em cursos.
- Listagem de aulas por curso (na ordem definida pelo professor).
- Marcação de aulas como concluídas.
- Trilha de aprendizagem por curso.
- Avaliação de cursos com nota (1–5) e comentário.
- Questionário de perfil de investidor.
- Simulador de investimentos.
- Histórico de simulações.

### Professor

- Acesso a uma área própria de professor.
- Editor de curso em etapas (Course Builder):
  - Passo 1: informações gerais (título, subtítulo, categoria, tags, preço, thumbnail).
  - Passo 2: criação e ordenação de aulas dentro do curso.
  - Passo 3: revisão e envio para aprovação.
- Cursos ficam em estado `draft` até serem submetidos.
- Envio de materiais de apoio.
- Acompanhamento do status de aprovação: pendente, aprovado ou rejeitado.
- Visualização das avaliações recebidas nos cursos aprovados.

### Administrador

- Dashboard com métricas gerais.
- Gestão de alunos.
- Gestão de professores.
- Aprovação ou rejeição de cursos enviados por professores.
- Aprovação ou rejeição de aulas enviadas por professores.
- Publicação de conteúdos aprovados para os alunos.

## Estrutura do projeto

```text
EduvFinance/
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   ├── variables.css
│   │   ├── layout.css
│   │   ├── components.css
│   │   ├── animations.css
│   │   └── responsive.css
│   └── js/
│       ├── admin.js
│       ├── admin_aprovacoes.js
│       ├── admin_professores.js
│       ├── aluno.js
│       ├── auth.js
│       ├── professor.js
│       ├── professor_reviews.js
│       ├── reviews.js
│       ├── trilha.js
│       └── utils.js
├── backend/
│   └── setup.php
├── database/
│   ├── edufinance.sql
│   └── migrations/
│       ├── 001_professor_approval.sql
│       ├── 002_course_reviews.sql
│       └── 003_course_builder.sql
├── docker/
│   ├── apache.conf
│   └── entrypoint.sh
├── home/
│   ├── index.html
│   ├── student.html
│   ├── professor.html
│   ├── professor_builder.html
│   ├── admin_alunos.html
│   ├── admin_professores.html
│   ├── admin_cursos.html
│   ├── admin_aulas.html
│   ├── aluno_cursos.html
│   ├── aluno_trilha.html
│   └── aluno_simulador.html
├── login/
│   ├── index.html
│   └── cadastro.html
├── php/
│   ├── conexao.php
│   ├── login.php
│   ├── logout.php
│   ├── cadastro.php
│   └── demais endpoints da aplicação
├── uploads/
│   └── .gitkeep
├── .env
├── Dockerfile
├── docker-compose.yml
└── index.html
```

## Requisitos

Para executar com Docker:

- Docker
- Docker Compose

Para executar sem Docker:

- PHP 8.2 ou superior
- Apache ou outro servidor HTTP compatível
- PostgreSQL
- Extensão PHP `pdo_pgsql`

O caminho recomendado para desenvolvimento local é com Docker Compose.

## Como executar com Docker

Na raiz do projeto, execute:

```bash
docker compose up --build
```

Depois acesse:

```text
http://localhost:8080
```

O Docker Compose sobe dois serviços:

- `postgres`: banco PostgreSQL.
- `app`: aplicação PHP com Apache.

O container da aplicação aguarda o PostgreSQL ficar disponível, executa o setup inicial e inicia o Apache.

## Acessos iniciais

Durante a inicialização, o arquivo `backend/setup.php` cria um usuário administrador caso ele ainda não exista:

```text
E-mail: admin@email.com
Senha: 123
```

O cadastro público cria usuários do tipo aluno. Usuários professores podem ser gerenciados pela área administrativa.

## Configuração de ambiente

As configurações de banco são lidas a partir do arquivo `.env` e das variáveis de ambiente do container.

Variáveis usadas:

```env
DB_HOST=postgres
DB_NAME=educacao_financeira
DB_USER=edufinance
DB_PASS=edufinance123
DB_PORT=5432
```

No Docker, o serviço `app` usa `DB_HOST=postgres`, que é o nome do serviço do banco dentro da rede do Docker Compose.

Para acessar o banco por uma ferramenta como DBeaver ou pgAdmin, use:

```text
Host: localhost
Porta: 5432
Database: educacao_financeira
Usuário: edufinance
Senha: edufinance123
```

## Banco de dados

O schema principal fica em:

```text
database/edufinance.sql
```

As migrações incrementais ficam em:

```text
database/migrations/001_professor_approval.sql   — fluxo de professor e aprovação
database/migrations/002_course_reviews.sql       — avaliações de cursos por alunos
database/migrations/003_course_builder.sql       — editor em etapas (draft, campos extras, ordenação de aulas)
```

Principais tabelas:

- `users`: usuários do sistema e seus papéis.
- `courses`: cursos públicos aprovados (com `subtitulo`, `thumbnail_path`, `categoria`, `preco`, `published_at`).
- `lessons`: aulas públicas aprovadas.
- `course_lessons`: vínculo entre cursos e aulas (com `order_index`).
- `course_enrollments`: matrículas dos alunos.
- `progress`: progresso dos alunos nas aulas.
- `course_reviews`: avaliações (nota 1–5 e comentário) de alunos por curso.
- `professor_courses`: ofertas de cursos criadas por professores (status: `draft` → `pendente` → `aprovado`/`rejeitado`).
- `professor_lessons`: aulas vinculadas a um curso do professor (com `professor_course_id` e `order_index`).
- `professor_course_lessons`: vínculo legado entre ofertas de cursos e aulas.
- `investor_profile`: perfil de investidor do aluno.
- `investment_simulations`: histórico de simulações de investimento.

O `docker-compose.yml` também monta `database/edufinance.sql` em `/docker-entrypoint-initdb.d/01-schema.sql`, permitindo que o PostgreSQL aplique o schema na primeira criação do volume. As migrações devem ser aplicadas manualmente após a criação inicial.

## Páginas principais

### Entrada e autenticação

- `/index.html`: redireciona o usuário conforme a sessão.
- `/login/index.html`: tela de login.
- `/login/cadastro.html`: tela de cadastro de aluno.

### Administração

- `/home/index.html`: dashboard administrativo.
- `/home/admin_alunos.html`: gestão de alunos.
- `/home/admin_professores.html`: gestão de professores.
- `/home/admin_cursos.html`: aprovação de cursos.
- `/home/admin_aulas.html`: aprovação de aulas.

### Aluno

- `/home/student.html`: dashboard do aluno.
- `/home/aluno_cursos.html`: cursos e aulas.
- `/home/aluno_trilha.html`: trilhas de aprendizagem.
- `/home/aluno_simulador.html`: simulador e perfil de investidor.

### Professor

- `/home/professor.html`: área de gerenciamento de cursos e aulas.
- `/home/professor_builder.html`: editor de curso em etapas (Course Builder).

## Endpoints principais

### Autenticação e sessão

- `php/login.php`
- `php/logout.php`
- `php/cadastro.php`
- `php/sessao_check.php`
- `php/valida_sessao.php`
- `php/valida_admin.php`
- `php/valida_professor.php`

### Usuários

- `php/users_listar.php`
- `php/users_buscar.php`
- `php/users_inserir.php`
- `php/users_atualizar.php`
- `php/users_excluir.php`

### Cursos e aulas públicas

- `php/courses_listar.php`
- `php/courses_buscar.php`
- `php/courses_matricular.php`
- `php/courses_inserir.php`
- `php/courses_atualizar.php`
- `php/courses_excluir.php`
- `php/lessons_listar.php`
- `php/lessons_buscar.php`
- `php/lessons_inserir.php`
- `php/lessons_atualizar.php`
- `php/lessons_excluir.php`

### Avaliações de cursos

- `php/reviews.php`
- `php/professor_reviews_listar.php`

### Professor — Course Builder

- `php/professor_course_draft_criar.php`
- `php/professor_course_buscar.php`
- `php/professor_course_draft_atualizar.php`
- `php/professor_course_lessons_inserir.php`
- `php/professor_course_lessons_atualizar.php`
- `php/professor_course_lessons_excluir.php`
- `php/professor_course_lessons_ordenar.php`
- `php/professor_course_submeter.php`
- `php/professor_course_excluir.php`

### Progresso e trilhas

- `php/progress_stats.php`
- `php/progress_listar.php`
- `php/progress_marcar.php`
- `php/trilha_listar.php`
- `php/trilha_progresso.php`

### Professor

- `php/professor_courses_listar.php`
- `php/professor_courses_buscar.php`
- `php/professor_courses_inserir.php`
- `php/professor_courses_atualizar.php`
- `php/professor_courses_excluir.php`
- `php/professor_lessons_listar.php`
- `php/professor_lessons_buscar.php`
- `php/professor_lessons_inserir.php`
- `php/professor_lessons_atualizar.php`
- `php/professor_lessons_excluir.php`

### Aprovação administrativa

- `php/admin_professor_courses_listar.php`
- `php/admin_professor_courses_buscar.php`
- `php/admin_professor_courses_aprovar.php`
- `php/admin_professor_courses_rejeitar.php`
- `php/admin_professor_lessons_listar.php`
- `php/admin_professor_lessons_buscar.php`
- `php/admin_professor_lessons_aprovar.php`
- `php/admin_professor_lessons_rejeitar.php`
- `php/aprovacao_utils.php`

### Investimentos

- `php/investor_salvar.php`
- `php/simulator_calcular.php`
- `php/simulator_historico.php`

## Fluxos do sistema

### Cadastro e login

1. O aluno acessa `/login/cadastro.html`.
2. O cadastro envia os dados para `php/cadastro.php`.
3. O login é feito por `/login/index.html`, enviando os dados para `php/login.php`.
4. Após autenticação, o sistema redireciona conforme o tipo do usuário:
   - `admin`: `/home/index.html`
   - `professor`: `/home/professor.html`
   - `aluno`: `/home/student.html`

### Criação de curso pelo professor (Course Builder)

1. O professor acessa `/home/professor_builder.html`.
2. O curso é criado como rascunho (`draft`) via `professor_course_draft_criar.php`.
3. No Passo 1, o professor preenche informações gerais (título, subtítulo, categoria, tags, preço, thumbnail).
4. No Passo 2, o professor adiciona e ordena as aulas dentro do curso.
5. No Passo 3, o professor revisa e submete o curso (`professor_course_submeter.php`), alterando o status para `pendente`.
6. O administrador analisa a oferta em `/home/admin_cursos.html`.
7. Ao aprovar, o conteúdo é publicado nas tabelas públicas `courses` e `lessons`.
8. Ao rejeitar, o professor pode consultar o status e o comentário de revisão.

### Avaliação de curso pelo aluno

1. Após acessar as aulas de um curso, o aluno pode registrar uma avaliação (nota 1–5 e comentário opcional).
2. Cada aluno pode enviar uma única avaliação por curso.
3. O professor pode consultar as avaliações dos seus cursos aprovados.

### Jornada do aluno

1. O aluno acessa a área de cursos.
2. Matricula-se em um curso.
3. Visualiza as aulas vinculadas na ordem definida pelo professor.
4. Marca aulas como concluídas.
5. Acompanha o avanço na trilha de aprendizagem.
6. Avalia o curso ao final.

### Simulador de investimentos

1. O aluno informa capital inicial, aporte mensal, período e tipo de investimento.
2. O sistema calcula rendimento estimado com taxas mensais fixas.
3. O resultado é exibido com resumo e gráfico.
4. A simulação é salva no histórico do aluno.

Tipos disponíveis:

- Poupança: 0,5% ao mês.
- CDB: 0,8% ao mês.
- Ações: 1,2% ao mês.
- Cripto: 2,0% ao mês.

## Uploads

Materiais de apoio enviados por professores são armazenados em:

```text
uploads/
```

Tipos aceitos:

- PDF
- DOC
- DOCX
- PPT
- PPTX
- ZIP

O limite implementado para upload é de 10 MB.

## Comandos úteis

Subir a aplicação:

```bash
docker compose up --build
```

Subir em segundo plano:

```bash
docker compose up -d --build
```

Parar os containers:

```bash
docker compose down
```

Parar e remover o volume do banco:

```bash
docker compose down -v
```

Executar o setup manualmente dentro do container:

```bash
docker compose exec app php backend/setup.php
```

Ver logs da aplicação:

```bash
docker compose logs -f app
```

Ver logs do banco:

```bash
docker compose logs -f postgres
```

## Comandos para aplicar migrações

Após subir o banco pela primeira vez, aplique as migrações manualmente:

```bash
docker compose exec app psql -U edufinance -d educacao_financeira -f /var/www/html/database/migrations/002_course_reviews.sql
docker compose exec app psql -U edufinance -d educacao_financeira -f /var/www/html/database/migrations/003_course_builder.sql
```

## Observações

- O volume `edufinance_pgdata` mantém os dados do PostgreSQL entre reinicializações.
- Se quiser recriar o banco do zero, use `docker compose down -v` antes de subir novamente.
- As migrações `002` e `003` não são aplicadas automaticamente pelo Docker — execute-as manualmente após a criação do banco.
- O fluxo atual de criação de cursos usa o Course Builder (`professor_builder.html`); os endpoints legados de professor permanecem para compatibilidade.
- A aplicação usa sessão PHP e cookies HTTP-only com `SameSite=Lax`.
- O simulador usa taxas fixas para fins educacionais; ele não representa recomendação financeira.
- O projeto não possui scripts npm, Composer ou suíte automatizada de testes configurada.

