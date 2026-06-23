-- ============================================================================
-- Seed de cursos de demonstração — EduvFinance
-- ----------------------------------------------------------------------------
-- Cria 6 cursos de exemplo (com aulas e vídeos reais do YouTube) replicando
-- EXATAMENTE o fluxo real do sistema:
--
--   • 4 cursos APROVADOS  -> existem em professor_courses (status 'aprovado')
--     E publicados em courses/lessons/course_lessons, então aparecem no
--     catálogo do aluno para matrícula (como se o admin já tivesse aprovado).
--
--   • 2 cursos PENDENTES  -> existem só em professor_courses (status 'pendente'),
--     aparecem no painel do professor e na fila de aprovação do admin, mas
--     NÃO no catálogo do aluno (sem public_course_id), igual ao comportamento real.
--
-- Distribuídos entre os professores já existentes: dante e fabio.
--
-- Uso:  docker exec -i edufinance-db psql -U edufinance -d educacao_financeira \
--          -f /caminho/seed_demo_courses.sql
--       (ou cole o conteúdo no DBeaver). Roda tudo em uma transação.
--
-- Idempotência: aborta se os cursos demo já existirem (evita duplicar).
-- Para reverter, veja o bloco "ROLLBACK / LIMPEZA" comentado no final.
-- ============================================================================

BEGIN;

-- ── Funções auxiliares (temporárias, somem ao fim da sessão) ────────────────

-- Aula de curso APROVADO: cria a aula pública, a aula do professor (aprovada,
-- já ligada à pública) e o vínculo ordenado no curso público.
CREATE OR REPLACE FUNCTION pg_temp.seed_aula_aprovada(
    p_pc          INT,   -- professor_courses.id
    p_pub_course  INT,   -- courses.id (público)
    p_prof        INT,   -- users.id do professor
    p_titulo      TEXT,
    p_desc        TEXT,
    p_nivel       TEXT,
    p_video       TEXT,
    p_ord         INT,
    p_dur         INT
) RETURNS VOID AS $f$
DECLARE
    v_pub INT;
BEGIN
    INSERT INTO lessons (titulo, descricao, nivel, video_link)
    VALUES (p_titulo, p_desc, p_nivel, p_video)
    RETURNING id INTO v_pub;

    INSERT INTO professor_lessons
        (professor_id, professor_course_id, titulo, descricao, nivel,
         video_link, order_index, duracao, status, public_lesson_id)
    VALUES
        (p_prof, p_pc, p_titulo, p_desc, p_nivel,
         p_video, p_ord, p_dur, 'aprovado', v_pub);

    INSERT INTO course_lessons (course_id, lesson_id, order_index)
    VALUES (p_pub_course, v_pub, p_ord);
END;
$f$ LANGUAGE plpgsql;

-- Aula de curso PENDENTE: cria só a aula do professor (status pendente),
-- sem nada publicado.
CREATE OR REPLACE FUNCTION pg_temp.seed_aula_pendente(
    p_pc      INT,
    p_prof    INT,
    p_titulo  TEXT,
    p_desc    TEXT,
    p_nivel   TEXT,
    p_video   TEXT,
    p_ord     INT,
    p_dur     INT
) RETURNS VOID AS $f$
BEGIN
    INSERT INTO professor_lessons
        (professor_id, professor_course_id, titulo, descricao, nivel,
         video_link, order_index, duracao, status)
    VALUES
        (p_prof, p_pc, p_titulo, p_desc, p_nivel,
         p_video, p_ord, p_dur, 'pendente');
END;
$f$ LANGUAGE plpgsql;


DO $$
DECLARE
    dante INT;
    fabio INT;
    pc    INT;   -- professor_courses.id corrente
    pub   INT;   -- courses.id público corrente
BEGIN
    SELECT id INTO dante FROM users WHERE email = 'dante@email.com';
    SELECT id INTO fabio FROM users WHERE email = 'fabio@email.com';

    IF dante IS NULL OR fabio IS NULL THEN
        RAISE EXCEPTION 'Professores demo (dante/fabio) não encontrados em users.';
    END IF;

    -- Guarda de idempotência: não duplica se já foi rodado.
    IF EXISTS (SELECT 1 FROM professor_courses
               WHERE nome = 'Primeiros Passos no Mundo dos Investimentos') THEN
        RAISE EXCEPTION 'Seed já aplicado (curso demo encontrado). Abortando para evitar duplicatas.';
    END IF;

    -- =========================================================================
    -- APROVADO 1 — dante — Investimentos (básico, conservador, gratuito)
    -- =========================================================================
    INSERT INTO professor_courses
        (professor_id, nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, status)
    VALUES
        (dante,
         'Primeiros Passos no Mundo dos Investimentos',
         'Saia da poupança e comece a investir com segurança',
         'Curso introdutório para quem nunca investiu. Você vai entender a diferença entre poupar e investir, conhecer a renda fixa e dar os primeiros passos no Tesouro Direto com total segurança.',
         'basico', 'Investimentos', 0, 'conservador', 'aprovado')
    RETURNING id INTO pc;

    INSERT INTO courses
        (nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, published_at)
    VALUES
        ('Primeiros Passos no Mundo dos Investimentos',
         'Saia da poupança e comece a investir com segurança',
         'Curso introdutório para quem nunca investiu. Você vai entender a diferença entre poupar e investir, conhecer a renda fixa e dar os primeiros passos no Tesouro Direto com total segurança.',
         'basico', 'Investimentos', 0, 'conservador', CURRENT_TIMESTAMP)
    RETURNING id INTO pub;
    UPDATE professor_courses SET public_course_id = pub WHERE id = pc;

    PERFORM pg_temp.seed_aula_aprovada(pc, pub, dante,
        'Poupança x Investimentos: por onde começar',
        'Entenda por que a poupança rende pouco e o que realmente muda quando você começa a investir.',
        'basico', 'https://www.youtube.com/watch?v=piCLfWcA1JU', 1, 12);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, dante,
        'Entendendo a Renda Fixa: CDB, Selic e LCI/LCA',
        'Os principais títulos de renda fixa explicados de forma simples, com foco em segurança e liquidez.',
        'basico', 'https://www.youtube.com/watch?v=iFE0LENHpPQ', 2, 18);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, dante,
        'Tesouro Direto na prática',
        'Passo a passo para escolher e comprar seu primeiro título público no Tesouro Direto.',
        'basico', 'https://www.youtube.com/watch?v=FFKGBZc6G_A', 3, 25);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, dante,
        'Montando sua Reserva de Emergência',
        'Quanto guardar, onde deixar e como usar a reserva que protege todo o seu plano financeiro.',
        'basico', 'https://www.youtube.com/watch?v=EgVeWLDami4', 4, 15);

    -- =========================================================================
    -- APROVADO 2 — fabio — Orçamento Pessoal (básico, todos, gratuito)
    -- =========================================================================
    INSERT INTO professor_courses
        (professor_id, nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, status)
    VALUES
        (fabio,
         'Organize Suas Finanças do Zero',
         'Assuma o controle do seu dinheiro em 4 aulas',
         'Aprenda a enxergar para onde vai o seu dinheiro, montar um orçamento que cabe na sua realidade e construir uma base financeira sólida antes de investir.',
         'basico', 'Orçamento Pessoal', 0, 'todos', 'aprovado')
    RETURNING id INTO pc;

    INSERT INTO courses
        (nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, published_at)
    VALUES
        ('Organize Suas Finanças do Zero',
         'Assuma o controle do seu dinheiro em 4 aulas',
         'Aprenda a enxergar para onde vai o seu dinheiro, montar um orçamento que cabe na sua realidade e construir uma base financeira sólida antes de investir.',
         'basico', 'Orçamento Pessoal', 0, 'todos', CURRENT_TIMESTAMP)
    RETURNING id INTO pub;
    UPDATE professor_courses SET public_course_id = pub WHERE id = pc;

    PERFORM pg_temp.seed_aula_aprovada(pc, pub, fabio,
        'Diagnóstico: para onde vai o seu dinheiro',
        'Mapeie suas receitas e despesas com uma planilha simples e descubra seus vazamentos financeiros.',
        'basico', 'https://www.youtube.com/watch?v=kW-UMpGUhx8', 1, 14);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, fabio,
        'Quitando dívidas com inteligência',
        'Como priorizar e negociar dívidas para parar de perder dinheiro com juros todos os meses.',
        'basico', 'https://www.youtube.com/watch?v=1Bmxri8umYE', 2, 16);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, fabio,
        'Construindo sua reserva de emergência',
        'O alicerce de qualquer vida financeira saudável: quanto guardar e por que ela vem antes de investir.',
        'basico', 'https://www.youtube.com/watch?v=o3_U-nzRW1c', 3, 13);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, fabio,
        'Onde deixar o dinheiro da reserva',
        'As melhores opções de baixo risco e liquidez diária para a sua reserva render sem sustos.',
        'basico', 'https://www.youtube.com/watch?v=m9tKdU1Vh-g', 4, 12);

    -- =========================================================================
    -- APROVADO 3 — dante — Investimentos (intermediário, moderado, R$149,90)
    -- =========================================================================
    INSERT INTO professor_courses
        (professor_id, nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, status)
    VALUES
        (dante,
         'Renda Variável: Ações e Fundos Imobiliários',
         'Dê o próximo passo e invista na bolsa de valores',
         'Para quem já domina a renda fixa e quer buscar retornos maiores. Entenda como funciona a bolsa, como comprar sua primeira ação e como gerar renda passiva com fundos imobiliários.',
         'intermediario', 'Investimentos', 149.90, 'moderado', 'aprovado')
    RETURNING id INTO pc;

    INSERT INTO courses
        (nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, published_at)
    VALUES
        ('Renda Variável: Ações e Fundos Imobiliários',
         'Dê o próximo passo e invista na bolsa de valores',
         'Para quem já domina a renda fixa e quer buscar retornos maiores. Entenda como funciona a bolsa, como comprar sua primeira ação e como gerar renda passiva com fundos imobiliários.',
         'intermediario', 'Investimentos', 149.90, 'moderado', CURRENT_TIMESTAMP)
    RETURNING id INTO pub;
    UPDATE professor_courses SET public_course_id = pub WHERE id = pc;

    PERFORM pg_temp.seed_aula_aprovada(pc, pub, dante,
        'Como funciona a Bolsa de Valores (B3)',
        'O que é a B3, como as ações são negociadas e o que você precisa para começar a investir.',
        'intermediario', 'https://www.youtube.com/watch?v=uYY3G9Sy1Gw', 1, 20);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, dante,
        'Comprando sua primeira ação',
        'Aula do zero para iniciantes: como se tornar sócio de grandes empresas com pouco dinheiro.',
        'intermediario', 'https://www.youtube.com/watch?v=khToouRsNts', 2, 22);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, dante,
        'Fundos Imobiliários (FIIs): renda passiva mensal',
        'Como os FIIs funcionam e por que pagam dividendos isentos de imposto todos os meses.',
        'intermediario', 'https://www.youtube.com/watch?v=IpgxIu-neuI', 3, 24);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, dante,
        'Vivendo de dividendos com FIIs',
        'Quanto investir para construir uma renda passiva consistente com fundos imobiliários.',
        'intermediario', 'https://www.youtube.com/watch?v=VN1xrZFJ6bk', 4, 19);

    -- =========================================================================
    -- APROVADO 4 — fabio — Criptomoedas (intermediário, agressivo, R$99,90)
    -- =========================================================================
    INSERT INTO professor_courses
        (professor_id, nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, status)
    VALUES
        (fabio,
         'Introdução ao Mundo das Criptomoedas',
         'Entenda Bitcoin e blockchain sem complicação',
         'Desmistifique o universo cripto. Você vai entender o que é Bitcoin, como a tecnologia blockchain funciona e quais cuidados ter antes de investir em ativos digitais.',
         'intermediario', 'Criptomoedas', 99.90, 'agressivo', 'aprovado')
    RETURNING id INTO pc;

    INSERT INTO courses
        (nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, published_at)
    VALUES
        ('Introdução ao Mundo das Criptomoedas',
         'Entenda Bitcoin e blockchain sem complicação',
         'Desmistifique o universo cripto. Você vai entender o que é Bitcoin, como a tecnologia blockchain funciona e quais cuidados ter antes de investir em ativos digitais.',
         'intermediario', 'Criptomoedas', 99.90, 'agressivo', CURRENT_TIMESTAMP)
    RETURNING id INTO pub;
    UPDATE professor_courses SET public_course_id = pub WHERE id = pc;

    PERFORM pg_temp.seed_aula_aprovada(pc, pub, fabio,
        'O que é Bitcoin? O guia básico',
        'O que é, como funciona e quais as vantagens da primeira e maior criptomoeda do mundo.',
        'intermediario', 'https://www.youtube.com/watch?v=N1NJbhxSr8E', 1, 17);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, fabio,
        'Blockchain: a tecnologia por trás das criptos',
        'Entenda a tecnologia que garante a segurança e a transparência das criptomoedas.',
        'intermediario', 'https://www.youtube.com/watch?v=1oEbli9j6rg', 2, 16);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, fabio,
        'Bitcoin para leigos: o básico 100% explicado',
        'Uma explicação completa e acessível para quem está chegando agora no mundo cripto.',
        'intermediario', 'https://www.youtube.com/watch?v=HpfCXch-pno', 3, 21);
    PERFORM pg_temp.seed_aula_aprovada(pc, pub, fabio,
        'Descomplicando a blockchain',
        'Aprofunde seu entendimento sobre como a rede blockchain registra e protege as transações.',
        'intermediario', 'https://www.youtube.com/watch?v=TolnPKzcmxc', 4, 18);

    -- =========================================================================
    -- PENDENTE 1 — fabio — Orçamento Pessoal (básico, todos) — aguarda aprovação
    -- =========================================================================
    INSERT INTO professor_courses
        (professor_id, nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, status)
    VALUES
        (fabio,
         'Saia das Dívidas e Recomece',
         'Um plano realista para limpar seu nome',
         'Método passo a passo para encarar suas dívidas, negociar com inteligência e voltar a ter saúde financeira, começando hoje.',
         'basico', 'Orçamento Pessoal', 0, 'todos', 'pendente')
    RETURNING id INTO pc;

    PERFORM pg_temp.seed_aula_pendente(pc, fabio,
        'Encare suas dívidas de frente',
        'O primeiro passo para sair do vermelho: listar tudo e entender o tamanho real do problema.',
        'basico', 'https://www.youtube.com/watch?v=EahzFpCcTr8', 1, 11);
    PERFORM pg_temp.seed_aula_pendente(pc, fabio,
        'Estratégias para quitar e negociar',
        'Como priorizar dívidas, negociar descontos e escapar da bola de neve dos juros.',
        'basico', 'https://www.youtube.com/watch?v=CX_DQKthhig', 2, 15);
    PERFORM pg_temp.seed_aula_pendente(pc, fabio,
        'Plano prático para sair do vermelho',
        'Um plano de ação que você consegue começar hoje para retomar o controle das finanças.',
        'basico', 'https://www.youtube.com/watch?v=JoYfOKEHIng', 3, 14);

    -- =========================================================================
    -- PENDENTE 2 — dante — Investimentos (avançado, moderado) — aguarda aprovação
    -- =========================================================================
    INSERT INTO professor_courses
        (professor_id, nome, subtitulo, descricao, nivel, categoria, preco, perfil_recomendado, status)
    VALUES
        (dante,
         'Montando uma Carteira de Investimentos Diversificada',
         'Equilibre risco e retorno como um investidor experiente',
         'Para investidores que já têm uma base sólida. Aprenda a diversificar entre classes de ativos, equilibrar renda fixa e variável e acompanhar sua carteira no longo prazo.',
         'avancado', 'Investimentos', 199.90, 'moderado', 'pendente')
    RETURNING id INTO pc;

    PERFORM pg_temp.seed_aula_pendente(pc, dante,
        'Diversificação: não coloque tudo num lugar só',
        'Por que espalhar seus investimentos entre diferentes ativos reduz risco sem matar o retorno.',
        'avancado', 'https://www.youtube.com/watch?v=Acgd5fkXDmM', 1, 20);
    PERFORM pg_temp.seed_aula_pendente(pc, dante,
        'Equilibrando renda fixa e variável',
        'Como definir a proporção entre segurança e crescimento de acordo com o seu perfil.',
        'avancado', 'https://www.youtube.com/watch?v=iFE0LENHpPQ', 2, 18);
    PERFORM pg_temp.seed_aula_pendente(pc, dante,
        'Acompanhando sua carteira no longo prazo',
        'Lições de quem investiu por 10 anos: rebalanceamento, disciplina e o poder dos juros compostos.',
        'avancado', 'https://www.youtube.com/watch?v=xOWMQloIlGM', 3, 21);

    RAISE NOTICE 'Seed concluído: 4 cursos aprovados + 2 pendentes criados.';
END $$;

COMMIT;

-- ============================================================================
-- ROLLBACK / LIMPEZA (descomente e rode para remover TODO o conteúdo demo):
--
-- BEGIN;
--   -- Apaga cursos públicos demo (cascata remove course_lessons, matrículas,
--   -- avaliações e progresso ligados a eles).
--   DELETE FROM courses WHERE nome IN (
--       'Primeiros Passos no Mundo dos Investimentos',
--       'Organize Suas Finanças do Zero',
--       'Renda Variável: Ações e Fundos Imobiliários',
--       'Introdução ao Mundo das Criptomoedas');
--   -- Apaga as aulas públicas órfãs criadas pelo seed.
--   DELETE FROM lessons WHERE id NOT IN (SELECT lesson_id FROM course_lessons)
--       AND id IN (SELECT public_lesson_id FROM professor_lessons WHERE public_lesson_id IS NOT NULL);
--   -- Apaga os cursos do professor demo (cascata remove professor_lessons).
--   DELETE FROM professor_courses WHERE nome IN (
--       'Primeiros Passos no Mundo dos Investimentos',
--       'Organize Suas Finanças do Zero',
--       'Renda Variável: Ações e Fundos Imobiliários',
--       'Introdução ao Mundo das Criptomoedas',
--       'Saia das Dívidas e Recomece',
--       'Montando uma Carteira de Investimentos Diversificada');
-- COMMIT;
-- ============================================================================
