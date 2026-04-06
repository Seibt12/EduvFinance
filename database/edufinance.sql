-- ============================================================
-- SCHEMA DO BANCO DE DADOS — EduFinance
--
-- Como ler este arquivo:
--   • Cada CREATE TABLE define uma "tabela" (uma planilha no banco)
--   • Cada linha dentro é uma "coluna" (um campo da planilha)
--   • REFERENCES = relacionamento entre tabelas (chave estrangeira)
--   • ON DELETE CASCADE = se o pai for deletado, o filho também
-- ============================================================


-- ------------------------------------------------------------
-- USUÁRIOS — todos que usam o sistema (admin e alunos)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         SERIAL       PRIMARY KEY,                          -- ID único gerado automaticamente
    nome       VARCHAR(100) NOT NULL,                            -- nome completo do usuário
    email      VARCHAR(150) NOT NULL UNIQUE,                     -- email único (usado para login)
    senha      VARCHAR(255) NOT NULL,                            -- senha criptografada com bcrypt
    tipo       VARCHAR(10)  NOT NULL DEFAULT 'aluno'
                            CHECK (tipo IN ('admin', 'aluno')),  -- define o que o usuário pode fazer
    -- EXEMPLO: campo "idade" adicionado como demonstração
    -- Para adicionar um campo novo, basta incluir aqui e rodar o SQL:
    --   ALTER TABLE users ADD COLUMN IF NOT EXISTS idade SMALLINT;
    idade      SMALLINT     NULL,                                 -- idade do aluno (opcional)
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ------------------------------------------------------------
-- AULAS — conteúdo educativo criado pelo admin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lessons (
    id         SERIAL       PRIMARY KEY,
    titulo     VARCHAR(200) NOT NULL,
    descricao  TEXT         NOT NULL,
    nivel      VARCHAR(15)  NOT NULL
                            CHECK (nivel IN ('basico', 'intermediario', 'avancado')),
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ------------------------------------------------------------
-- PROGRESSO — registra quais aulas cada aluno completou
-- (UNIQUE garante um registro por aluno/aula, sem duplicatas)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS progress (
    id         SERIAL   PRIMARY KEY,
    user_id    INTEGER  NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
    lesson_id  INTEGER  NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
    concluido  SMALLINT NOT NULL DEFAULT 0 CHECK (concluido IN (0, 1)), -- 0 = não feito, 1 = feito
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, lesson_id)  -- cada aluno tem no máximo 1 registro por aula
);


-- ------------------------------------------------------------
-- CURSOS — agrupamentos de aulas (criados pelo admin)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id         SERIAL       PRIMARY KEY,
    nome       VARCHAR(200) NOT NULL,
    descricao  TEXT         NOT NULL,
    nivel      VARCHAR(15)  NOT NULL
                            CHECK (nivel IN ('basico', 'intermediario', 'avancado')),
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Relacionamento N:N — um curso tem várias aulas, uma aula pode estar em vários cursos
CREATE TABLE IF NOT EXISTS course_lessons (
    course_id  INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    lesson_id  INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
    PRIMARY KEY (course_id, lesson_id)
);

-- Matrículas — registra quais alunos estão inscritos em quais cursos
CREATE TABLE IF NOT EXISTS course_enrollments (
    id         SERIAL    PRIMARY KEY,
    user_id    INTEGER   NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
    course_id  INTEGER   NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, course_id)  -- aluno só pode se matricular uma vez por curso
);


-- ------------------------------------------------------------
-- PERFIL DE INVESTIDOR — resultado do quiz financeiro
-- (um por aluno — UNIQUE no user_id garante isso)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS investor_profile (
    id         SERIAL      PRIMARY KEY,
    user_id    INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE UNIQUE,
    perfil     VARCHAR(15) NOT NULL
                           CHECK (perfil IN ('conservador', 'moderado', 'agressivo')),
    respostas  TEXT        NOT NULL DEFAULT '{}',  -- respostas do quiz em formato JSON
    pontuacao  SMALLINT    NOT NULL DEFAULT 0,
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ============================================================
-- TRIGGER — atualiza updated_at automaticamente em todo UPDATE
--
-- Sem isso, teríamos que lembrar de atualizar updated_at
-- manualmente em cada query de UPDATE. O trigger faz isso sozinho.
-- ============================================================

CREATE OR REPLACE FUNCTION fn_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_users_updated_at') THEN
        CREATE TRIGGER trg_users_updated_at
            BEFORE UPDATE ON users
            FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_lessons_updated_at') THEN
        CREATE TRIGGER trg_lessons_updated_at
            BEFORE UPDATE ON lessons
            FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_progress_updated_at') THEN
        CREATE TRIGGER trg_progress_updated_at
            BEFORE UPDATE ON progress
            FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_courses_updated_at') THEN
        CREATE TRIGGER trg_courses_updated_at
            BEFORE UPDATE ON courses
            FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_investor_profile_updated_at') THEN
        CREATE TRIGGER trg_investor_profile_updated_at
            BEFORE UPDATE ON investor_profile
            FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
    END IF;
END $$;