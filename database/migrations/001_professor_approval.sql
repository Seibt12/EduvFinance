-- Migração para bancos já existentes (sem tabelas de professor ou colunas de anexos)
-- Execute: psql -U edufinance -d educacao_financeira -f database/migrations/001_professor_approval.sql
-- Ou rode: php backend/setup.php

ALTER TABLE lessons ADD COLUMN IF NOT EXISTS video_link VARCHAR(255) NULL;
ALTER TABLE lessons ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL;
ALTER TABLE lessons ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS professor_courses (
    id SERIAL PRIMARY KEY,
    professor_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT NOT NULL,
    nivel VARCHAR(15) NOT NULL CHECK (nivel IN ('basico', 'intermediario', 'avancado')),
    status VARCHAR(15) NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente', 'aprovado', 'rejeitado')),
    review_comment TEXT NULL,
    public_course_id INTEGER NULL REFERENCES courses(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS professor_lessons (
    id SERIAL PRIMARY KEY,
    professor_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT NOT NULL,
    nivel VARCHAR(15) NOT NULL CHECK (nivel IN ('basico', 'intermediario', 'avancado')),
    video_link VARCHAR(255) NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    status VARCHAR(15) NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente', 'aprovado', 'rejeitado')),
    review_comment TEXT NULL,
    public_lesson_id INTEGER NULL REFERENCES lessons(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS professor_course_lessons (
    professor_course_id INTEGER NOT NULL REFERENCES professor_courses(id) ON DELETE CASCADE,
    professor_lesson_id INTEGER NOT NULL REFERENCES professor_lessons(id) ON DELETE CASCADE,
    PRIMARY KEY (professor_course_id, professor_lesson_id)
);

CREATE INDEX IF NOT EXISTS idx_professor_courses_status ON professor_courses (status);
CREATE INDEX IF NOT EXISTS idx_professor_lessons_status ON professor_lessons (status);
