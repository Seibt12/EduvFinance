CREATE TABLE IF NOT EXISTS users (
    id         SERIAL       PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    senha      VARCHAR(255) NOT NULL,
    tipo       VARCHAR(10)  NOT NULL DEFAULT 'aluno'
                            CHECK (tipo IN ('admin', 'aluno', 'professor')),
    idade      SMALLINT     NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS lessons (
    id              SERIAL       PRIMARY KEY,
    titulo          VARCHAR(200) NOT NULL,
    descricao       TEXT         NOT NULL,
    nivel           VARCHAR(15)  NOT NULL
                                  CHECK (nivel IN ('basico', 'intermediario', 'avancado')),
    video_link      VARCHAR(255) NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS courses (
    id         SERIAL       PRIMARY KEY,
    nome       VARCHAR(200) NOT NULL,
    descricao  TEXT         NOT NULL,
    nivel      VARCHAR(15)  NOT NULL
                            CHECK (nivel IN ('basico', 'intermediario', 'avancado')),
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS course_lessons (
    course_id  INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    lesson_id  INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
    PRIMARY KEY (course_id, lesson_id)
);


CREATE TABLE IF NOT EXISTS progress (
    id         SERIAL   PRIMARY KEY,
    user_id    INTEGER  NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
    lesson_id  INTEGER  NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
    concluido  SMALLINT NOT NULL DEFAULT 0 CHECK (concluido IN (0, 1)),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, lesson_id)
);

CREATE TABLE IF NOT EXISTS course_enrollments (
    id         SERIAL    PRIMARY KEY,
    user_id    INTEGER   NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
    course_id  INTEGER   NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, course_id)
);


CREATE TABLE IF NOT EXISTS professor_courses (
    id              SERIAL       PRIMARY KEY,
    professor_id    INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    nome            VARCHAR(200) NOT NULL,
    descricao       TEXT         NOT NULL,
    nivel           VARCHAR(15)  NOT NULL CHECK (nivel IN ('basico', 'intermediario', 'avancado')),
    status          VARCHAR(15)  NOT NULL DEFAULT 'pendente'
                                   CHECK (status IN ('pendente', 'aprovado', 'rejeitado')),
    review_comment  TEXT         NULL,
    public_course_id INTEGER     NULL REFERENCES courses(id) ON DELETE SET NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS professor_lessons (
    id              SERIAL       PRIMARY KEY,
    professor_id    INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    titulo          VARCHAR(200) NOT NULL,
    descricao       TEXT         NOT NULL,
    nivel           VARCHAR(15)  NOT NULL CHECK (nivel IN ('basico', 'intermediario', 'avancado')),
    video_link      VARCHAR(255) NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    status          VARCHAR(15)  NOT NULL DEFAULT 'pendente'
                                   CHECK (status IN ('pendente', 'aprovado', 'rejeitado')),
    review_comment  TEXT         NULL,
    public_lesson_id INTEGER     NULL REFERENCES lessons(id) ON DELETE SET NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS professor_course_lessons (
    professor_course_id INTEGER NOT NULL REFERENCES professor_courses(id) ON DELETE CASCADE,
    professor_lesson_id INTEGER NOT NULL REFERENCES professor_lessons(id) ON DELETE CASCADE,
    PRIMARY KEY (professor_course_id, professor_lesson_id)
);


CREATE TABLE IF NOT EXISTS investor_profile (
    id         SERIAL      PRIMARY KEY,
    user_id    INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE UNIQUE,
    perfil     VARCHAR(15) NOT NULL
                           CHECK (perfil IN ('conservador', 'moderado', 'agressivo')),
    respostas  TEXT        NOT NULL DEFAULT '{}',
    pontuacao  SMALLINT    NOT NULL DEFAULT 0,
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS investment_simulations (
    id                   SERIAL         PRIMARY KEY,
    user_id              INTEGER        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    investment_type      VARCHAR(20)    NOT NULL
                                        CHECK (investment_type IN ('savings', 'cdb', 'stocks', 'crypto')),
    initial_capital      NUMERIC(15, 2) NOT NULL,
    monthly_contribution NUMERIC(15, 2) NOT NULL DEFAULT 0,
    period_months        SMALLINT       NOT NULL CHECK (period_months BETWEEN 1 AND 600),
    monthly_rate         NUMERIC(8, 6)  NOT NULL,
    final_amount         NUMERIC(15, 2) NOT NULL,
    total_invested       NUMERIC(15, 2) NOT NULL,
    total_profit         NUMERIC(15, 2) NOT NULL,
    created_at           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_investment_simulations_user_id
    ON investment_simulations (user_id);

CREATE INDEX IF NOT EXISTS idx_professor_courses_status
    ON professor_courses (status);

CREATE INDEX IF NOT EXISTS idx_professor_lessons_status
    ON professor_lessons (status);
