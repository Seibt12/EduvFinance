# EduvFinance — Roadmap de Implementação por Sprints
# Prompts para Claude Code

---

# ═══════════════════════════════════════════════
# SPRINT 1 — SEGURANÇA (fazer ANTES de qualquer deploy)
# ═══════════════════════════════════════════════

## Contexto do projeto
PHP clássico, PostgreSQL via PDO, sessões PHP nativas, Docker.
Estrutura: `php/` (lógica), `home/` (HTML), `login/` (HTML), `assets/js/`.
Autenticação via `$_SESSION['user_id']`, `$_SESSION['user_tipo']`.

## O que implementar neste sprint

### 1. Proteção CSRF em todos os formulários POST

Crie `php/csrf.php`:
- `gerarTokenCSRF()` — gera token aleatório com `bin2hex(random_bytes(32))`, salva em `$_SESSION['csrf_token']` e retorna o token
- `validarTokenCSRF($token)` — compara com hash_equals, lança exceção se inválido, regenera token após validação

Em TODOS os arquivos `php/*_inserir.php`, `php/*_atualizar.php`, `php/*_excluir.php`, `php/login.php`, `php/cadastro.php`:
- Adicione `require_once 'csrf.php'; validarTokenCSRF($_POST['csrf_token'] ?? '');` no topo
- Redirecione com `?erro=Requisição inválida.` se falhar

Em TODOS os arquivos HTML com formulários POST (`login/index.html`, `login/cadastro.html`, `home/admin_*.html`, `home/aluno_*.html`):
- Cada formulário `<form method="POST">` deve incluir um campo oculto CSRF
- Como os HTMLs são estáticos, o token deve ser injetado via JS ao carregar a página
- Crie função `injetarCSRF()` no `assets/js/auth.js`:
  ```javascript
  async function injetarCSRF() {
    const resp = await fetch('../php/csrf_token.php', { credentials: 'include' });
    const { token } = await resp.json();
    document.querySelectorAll('form[method="POST"]').forEach(form => {
      let input = form.querySelector('input[name="csrf_token"]');
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        form.appendChild(input);
      }
      input.value = token;
    });
  }
  ```
- Crie `php/csrf_token.php` que retorna `{ "token": "..." }` via JSON (apenas GET, requer sessão ativa)
- Chame `injetarCSRF()` em toda página que tenha formulário POST

### 2. Rate limiting no login

Em `php/login.php`, antes de verificar a senha:
- Use `$_SESSION['login_tentativas']` e `$_SESSION['login_bloqueio_ate']`
- Máximo 5 tentativas em 15 minutos
- Se bloqueado, redirecionar com `?erro=Muitas tentativas. Tente novamente em X minutos.`
- Resetar contador após login bem-sucedido

### 3. Validação de força de senha no cadastro

Em `php/cadastro.php`, validar antes de inserir:
- Mínimo 8 caracteres
- Pelo menos 1 letra maiúscula
- Pelo menos 1 número
- Retornar erro específico se não atender

Em `login/cadastro.html`, adicionar feedback visual em tempo real via JS (sem bloquear o submit — a validação real é no servidor).

### 4. Headers de segurança HTTP

Crie `php/security_headers.php` e inclua em `php/conexao.php` (assim todos os PHPs herdam):
```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

### 5. Índices no banco de dados

Crie o arquivo `database/migrations/001_indices.sql`:
```sql
CREATE INDEX IF NOT EXISTS idx_progress_user_id ON progress(user_id);
CREATE INDEX IF NOT EXISTS idx_progress_lesson_id ON progress(lesson_id);
CREATE INDEX IF NOT EXISTS idx_course_enrollments_user_id ON course_enrollments(user_id);
CREATE INDEX IF NOT EXISTS idx_course_enrollments_course_id ON course_enrollments(course_id);
CREATE INDEX IF NOT EXISTS idx_course_lessons_course_id ON course_lessons(course_id);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_tipo ON users(tipo);
CREATE INDEX IF NOT EXISTS idx_simulator_history_user_id ON simulator_history(user_id);
```

### 6. Paginação nas listagens do admin

Em `php/users_listar.php`, `php/courses_listar.php`, `php/lessons_listar.php`:
- Aceitar `?page=1&per_page=20` via GET
- Retornar no JSON: `{ success, data: [...], total, page, per_page, total_pages }`
- Limitar sempre com `LIMIT $per_page OFFSET (($page-1) * $per_page)`

Nos HTMLs admin correspondentes, adicionar paginação simples:
- Botões "Anterior" / "Próxima" + indicador "Página X de Y"
- Atualiza a tabela via fetch ao mudar de página

### 7. Log de erros

Crie `php/logger.php`:
```php
function logErro(string $contexto, \Throwable $e): void {
    $linha = date('Y-m-d H:i:s') . " | $contexto | " . $e->getMessage() . " | " . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    file_put_contents(__DIR__ . '/../logs/app.log', $linha, FILE_APPEND);
}
```
- Crie o diretório `logs/` com `.gitignore` excluindo `*.log`
- Envolva todos os blocos de banco em try/catch nos PHPs e chame `logErro()` no catch
- Nunca exibir a mensagem real do PDO para o usuário — sempre redirecionar com mensagem genérica

---

# ═══════════════════════════════════════════════
# SPRINT 2 — RECUPERAÇÃO DE SENHA
# ═══════════════════════════════════════════════

## Contexto
Mesmo stack do sprint anterior. O projeto já tem tabela `users` com colunas `id`, `email`, `senha`, `nome`.
Usar PHPMailer via Composer para envio de e-mail, ou fallback com `mail()` nativa se Composer não disponível.

## O que implementar

### Banco de dados

Crie `database/migrations/002_password_reset.sql`:
```sql
CREATE TABLE IF NOT EXISTS password_resets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    usado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);
```

### Fluxo completo

**`login/esqueci-senha.html`** — formulário com campo de e-mail + botão "Enviar link"

**`php/esqueci_senha.php`** (processa POST):
- Busca o usuário pelo e-mail
- Se não encontrar, redirecionar com mensagem genérica (não revelar se e-mail existe)
- Gerar token: `bin2hex(random_bytes(32))`
- Inserir em `password_resets` com `expires_at = NOW() + INTERVAL '1 hour'`
- Enviar e-mail com link: `https://SEU_DOMINIO/login/redefinir-senha.html?token=TOKEN`
- Usar `MAIL_FROM`, `MAIL_HOST`, `MAIL_USER`, `MAIL_PASS`, `MAIL_PORT` do `.env`

**`login/redefinir-senha.html`** — formulário com dois campos de senha + campo oculto com token (lido da URL via JS)

**`php/redefinir_senha.php`** (processa POST):
- Buscar token na tabela, verificar se não expirou e não foi usado
- Validar força da nova senha (mesmas regras do sprint 1)
- Atualizar `users.senha` com `password_hash()`
- Marcar token como `usado = TRUE`
- Redirecionar para login com mensagem de sucesso

**`login/index.html`** — adicionar link "Esqueci minha senha" abaixo do botão de login

---

# ═══════════════════════════════════════════════
# SPRINT 3 — CERTIFICADO DE CONCLUSÃO EM PDF
# ═══════════════════════════════════════════════

## Contexto
O aluno conclui um curso quando todas as aulas vinculadas a ele têm `progress.concluido = 1`.
Usar a lib TCPDF ou FPDF (puras PHP, sem Composer obrigatório) para gerar o PDF.

## O que implementar

### Banco de dados

Crie `database/migrations/003_certificates.sql`:
```sql
CREATE TABLE IF NOT EXISTS certificates (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    course_id INTEGER NOT NULL REFERENCES courses(id),
    codigo VARCHAR(32) NOT NULL UNIQUE,
    emitido_em TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, course_id)
);
```

### Lógica de emissão automática

Em `php/progress_marcar.php`, após marcar uma aula como concluída:
- Verificar se TODAS as aulas do curso foram concluídas pelo aluno
- Se sim, verificar se já existe certificado em `certificates`
- Se não existe, gerar `codigo = strtoupper(bin2hex(random_bytes(8)))` e inserir
- Retornar no JSON: `{ ..., certificado_emitido: true, codigo: 'XXXX' }`
- O JS no frontend exibe um toast/modal de parabéns quando `certificado_emitido: true`

### Geração do PDF

Crie `php/certificado_download.php` (GET com `?codigo=XXXX`):
- Verificar sessão ativa
- Buscar certificado pelo código, validar que pertence ao usuário logado (ou qualquer um pode validar pelo código — decidir)
- Gerar PDF com TCPDF/FPDF contendo:
  - Nome do aluno (`$_SESSION['user_nome']`)
  - Nome do curso
  - Data de conclusão formatada
  - Código único de validação
  - Layout visual com as cores da plataforma (#121212, #4a6cf7)
- Retornar com `header('Content-Type: application/pdf')` e `Content-Disposition: attachment`

### Página de validação pública

Crie `validar-certificado.html` na raiz (página pública, sem login):
- Campo de texto para digitar o código
- Chama `php/certificado_validar.php?codigo=XXXX` via fetch
- Exibe nome do aluno, curso e data se válido
- Exibe erro se inválido

### Interface do aluno

Em `home/aluno_cursos.html`, quando o curso estiver 100% concluído:
- Exibir badge "Certificado disponível" no course card
- Botão "Baixar certificado" que abre `php/certificado_download.php?codigo=XXXX`

---

# ═══════════════════════════════════════════════
# SPRINT 4 — SIMULADOR COM TAXAS REAIS
# ═══════════════════════════════════════════════

## Contexto
O simulador atual usa taxas fixas hardcoded (poupança 0,5%, CDB 0,8%, etc.).
A API do Banco Central (BCB) é pública e não requer autenticação.

## O que implementar

### Cache de taxas reais

Crie `php/taxas_cache.php`:
- Função `buscarTaxasBCB()` que faz `file_get_contents()` para:
  - SELIC: `https://api.bcb.gov.br/dados/serie/bcdata.sgs.11/dados/ultimos/1?formato=json`
  - CDI: `https://api.bcb.gov.br/dados/serie/bcdata.sgs.12/dados/ultimos/1?formato=json`
  - IPCA (inflação): `https://api.bcb.gov.br/dados/serie/bcdata.sgs.433/dados/ultimos/1?formato=json`
  - Poupança: `https://api.bcb.gov.br/dados/serie/bcdata.sgs.195/dados/ultimos/1?formato=json`
- Salvar resultado em `cache/taxas.json` com timestamp
- Só buscar da API se o cache tiver mais de 24 horas
- Converter taxa anual para mensal: `taxa_mensal = (1 + taxa_anual/100)^(1/12) - 1`
- Se a API falhar, usar as taxas fixas como fallback

### Atualizar o simulador

Em `php/simulator_calcular.php`:
- Importar `taxas_cache.php`
- Substituir as taxas fixas pelas taxas reais retornadas
- Adicionar tipo `ipca` (IPCA + 4% ao ano, taxa real mais comum no Tesouro IPCA+)
- Adicionar no retorno JSON: `taxas_usadas: { selic, cdi, poupanca, ipca }` e `fonte: 'BCB'` ou `fonte: 'fallback'`

### Atualizar a interface

Em `home/aluno_simulador.html`:
- Exibir abaixo dos cards de tipo: "Taxas atualizadas em DD/MM/YYYY — Fonte: Banco Central do Brasil"
- Adicionar opção "Tesouro IPCA+" nos tipos de investimento
- No resultado, exibir comparativo: "Vs. inflação (IPCA): seu investimento rendeu X% acima da inflação"
- Adicionar linha tracejada no gráfico representando a inflação acumulada no período

---

# ═══════════════════════════════════════════════
# SPRINT 5 — QUIZ POR AULA
# ═══════════════════════════════════════════════

## Contexto
Hoje o aluno marca uma aula como concluída com um clique. Vamos exigir um quiz simples antes.
O admin cria perguntas para cada aula. O aluno só avança se acertar a maioria.

## Banco de dados

Crie `database/migrations/004_quiz.sql`:
```sql
CREATE TABLE IF NOT EXISTS quiz_questions (
    id SERIAL PRIMARY KEY,
    lesson_id INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
    pergunta TEXT NOT NULL,
    opcao_a VARCHAR(255) NOT NULL,
    opcao_b VARCHAR(255) NOT NULL,
    opcao_c VARCHAR(255) NOT NULL,
    opcao_d VARCHAR(255) NOT NULL,
    resposta_correta CHAR(1) NOT NULL CHECK (resposta_correta IN ('a','b','c','d')),
    ordem INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS quiz_results (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    lesson_id INTEGER NOT NULL REFERENCES lessons(id),
    acertos INTEGER NOT NULL,
    total INTEGER NOT NULL,
    aprovado BOOLEAN NOT NULL,
    tentativa INTEGER NOT NULL DEFAULT 1,
    feito_em TIMESTAMP DEFAULT NOW()
);
```

## PHPs necessários

**`php/quiz_listar.php`** (GET `?lesson_id=X`) — retorna as perguntas SEM a resposta correta

**`php/quiz_submeter.php`** (POST `{ lesson_id, respostas: { 1: 'a', 2: 'c', ... } }`):
- Buscar respostas corretas do banco
- Calcular acertos
- Aprovado se acertos >= 70% do total
- Inserir em `quiz_results` (incrementar `tentativa` se já tentou antes)
- Se aprovado, chamar a lógica de `progress_marcar.php` internamente para marcar a aula
- Retornar `{ aprovado, acertos, total, mensagem }`

**`php/quiz_admin_listar.php`** e **`php/quiz_admin_salvar.php`** — CRUD de perguntas para o admin

## Interface do aluno

Em `home/aluno_cursos.html`, ao clicar "Marcar concluída":
- Verificar se a aula tem quiz (`php/quiz_listar.php`)
- Se tiver quiz, abrir modal com as perguntas em vez de marcar direto
- Modal com perguntas em sequência (uma por vez) ou todas de uma vez
- Exibir resultado: "Você acertou X de Y. Aprovado! ✓" ou "Você precisa de X% para passar. Tente novamente."
- Só marcar como concluída após aprovação

## Interface do admin

Em `home/admin_aulas.html`, adicionar na linha de cada aula:
- Botão "Quiz" que abre painel lateral ou modal
- Formulário para adicionar/editar/excluir perguntas daquela aula
- Indicador "X perguntas cadastradas" na lista de aulas

---

# ═══════════════════════════════════════════════
# SPRINT 6 — METAS FINANCEIRAS PESSOAIS
# ═══════════════════════════════════════════════

## Contexto
O aluno define objetivos financeiros. O sistema calcula quanto precisa investir por mês
e conecta a meta com os cursos recomendados.

## Banco de dados

Crie `database/migrations/005_metas.sql`:
```sql
CREATE TABLE IF NOT EXISTS metas (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    titulo VARCHAR(100) NOT NULL,
    valor_objetivo NUMERIC(12,2) NOT NULL,
    valor_atual NUMERIC(12,2) DEFAULT 0,
    aporte_mensal NUMERIC(10,2),
    tipo_investimento VARCHAR(20) DEFAULT 'cdb',
    prazo_meses INTEGER NOT NULL,
    ativa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS meta_aportes (
    id SERIAL PRIMARY KEY,
    meta_id INTEGER NOT NULL REFERENCES metas(id) ON DELETE CASCADE,
    valor NUMERIC(10,2) NOT NULL,
    data_aporte DATE NOT NULL DEFAULT CURRENT_DATE,
    observacao TEXT
);
```

## PHPs necessários

**`php/metas_listar.php`** — retorna metas do usuário logado com progresso calculado

**`php/metas_inserir.php`** — cria nova meta, calcula `aporte_mensal` sugerido via juros compostos

**`php/metas_atualizar.php`** — edita meta

**`php/metas_excluir.php`** — desativa meta (soft delete)

**`php/meta_aporte_registrar.php`** — registra um aporte realizado, atualiza `valor_atual` na meta

Cálculo do aporte sugerido (em PHP):
```php
// PMT = PV * (i * (1+i)^n) / ((1+i)^n - 1)  — onde PV = valor_objetivo
function calcularAporteSugerido(float $objetivo, float $taxaMensal, int $meses): float {
    if ($taxaMensal == 0) return $objetivo / $meses;
    return $objetivo * ($taxaMensal * pow(1 + $taxaMensal, $meses)) / (pow(1 + $taxaMensal, $meses) - 1);
}
```

## Interface do aluno

Crie `home/aluno_metas.html` — nova página no menu do aluno:

**Criar meta:**
- Campos: Título (ex: "Reserva de emergência"), Valor objetivo, Prazo em meses, Tipo de investimento
- Ao preencher os campos, mostrar em tempo real (via JS) o aporte mensal sugerido
- Botão "Criar meta"

**Listagem de metas:**
- Card por meta com: título, barra de progresso (valor_atual / valor_objetivo), percentual, prazo restante em meses
- Botão "Registrar aporte" — abre modal com campo de valor e data
- Botão "Ver projeção" — abre o simulador pré-preenchido com os dados da meta

**Dashboard do aluno (`home/student.html`):**
- Adicionar seção "Minhas Metas" com resumo das metas ativas
- Indicador de quantas metas estão no prazo vs. atrasadas

**Sidebar do aluno:**
- Adicionar item "Metas" no menu de navegação

---

# ═══════════════════════════════════════════════
# SPRINT 7 — PERFIL DE INVESTIDOR INTEGRADO
# ═══════════════════════════════════════════════

## Contexto
Hoje o perfil de investidor é salvo mas não afeta nada no sistema.
Vamos conectar o perfil com recomendações reais de cursos e configuração do simulador.

## O que implementar

### Recomendações de cursos baseadas no perfil

Em `php/courses_listar.php`:
- Buscar perfil do investidor do aluno logado
- Adicionar campo `recomendado: true/false` no retorno de cada curso baseado no nível:
  - Conservador → priorizar cursos básicos de renda fixa
  - Moderado → priorizar cursos intermediários
  - Arrojado → incluir cursos avançados de renda variável
- Ordenar cursos recomendados primeiro na listagem

### Simulador personalizado

Em `php/simulator_calcular.php`:
- Se o aluno tem perfil, sugerir automaticamente o tipo de investimento mais adequado ao perfil
- Retornar campo `tipo_recomendado_para_perfil` no JSON

### Interface

Em `home/aluno_cursos.html`:
- Exibir badge "Recomendado para você" nos cursos que batem com o perfil
- Seção destacada no topo "Baseado no seu perfil: {perfil}" com os 2-3 cursos mais recomendados

Em `home/aluno_simulador.html`:
- Banner no topo: "Seu perfil é {perfil} — recomendamos {tipo_investimento}"
- Pré-selecionar automaticamente o tipo de investimento recomendado para o perfil

Em `home/student.html` (dashboard):
- Se o aluno não tem perfil, exibir card de CTA: "Descubra seu perfil de investidor →"
- Se tem perfil, exibir o perfil com badge colorido e link para refazer

---

# ORDEM RECOMENDADA DE EXECUÇÃO

Sprint 1 → obrigatório antes de qualquer deploy
Sprint 2 → obrigatório para uso real (sem recuperação de senha ninguém usa)
Sprint 3 → maior percepção de valor, fazer logo
Sprint 4 → diferencial competitivo real
Sprint 5 → transforma o aprendizado em verificável
Sprint 6 → engajamento de longo prazo (faz o usuário voltar)
Sprint 7 → personalização (faz o sistema parecer inteligente)

# INSTRUÇÕES GERAIS PARA O CLAUDE CODE

- Antes de criar qualquer arquivo, liste o que existe em `php/`, `home/`, `assets/js/`
- Crie os arquivos SQL de migration em `database/migrations/` — não altere `edufinance.sql` diretamente
- Mantenha o padrão de retorno JSON existente: `{ success: true/false, message: '...', dados... }`
- Nunca exibir erros do PDO diretamente — sempre capturar e logar
- Ao criar novos itens de menu na sidebar, adicionar em TODOS os HTMLs do mesmo tipo de usuário
- Testar cada sprint de forma isolada antes de prosseguir para o próximo
