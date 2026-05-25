-- Migration 003: Course Builder — Lessons must belong to a course
-- Run after 002_course_reviews.sql

-- ── 1. Extend professor_courses status enum (add 'draft') ──────────────────
ALTER TABLE professor_courses DROP CONSTRAINT IF EXISTS professor_courses_status_check;
ALTER TABLE professor_courses
    ADD CONSTRAINT professor_courses_status_check
    CHECK (status IN ('draft', 'pendente', 'aprovado', 'rejeitado'));

-- Set new default to 'draft'
ALTER TABLE professor_courses ALTER COLUMN status SET DEFAULT 'draft';

-- ── 2. New fields on professor_courses ────────────────────────────────────
ALTER TABLE professor_courses
    ADD COLUMN IF NOT EXISTS subtitulo      VARCHAR(300) NULL,
    ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS categoria      VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS tags           TEXT         NULL,
    ADD COLUMN IF NOT EXISTS preco          NUMERIC(10,2) NOT NULL DEFAULT 0;

-- ── 3. New fields on professor_lessons (course ownership + ordering) ──────
ALTER TABLE professor_lessons
    ADD COLUMN IF NOT EXISTS professor_course_id INTEGER NULL
        REFERENCES professor_courses(id) ON DELETE CASCADE,
    ADD COLUMN IF NOT EXISTS order_index         SMALLINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS content             TEXT     NULL,
    ADD COLUMN IF NOT EXISTS duracao             SMALLINT NULL;

-- ── 4. Migrate existing professor_course_lessons data ─────────────────────
-- For each professor_lesson that belongs to exactly one course, set professor_course_id.
-- For lessons linked to multiple courses, take the one with the lowest professor_course_id.
UPDATE professor_lessons pl
SET professor_course_id = sub.professor_course_id
FROM (
    SELECT professor_lesson_id, MIN(professor_course_id) AS professor_course_id
    FROM professor_course_lessons
    GROUP BY professor_lesson_id
) sub
WHERE pl.id = sub.professor_lesson_id
  AND pl.professor_course_id IS NULL;

-- ── 5. Update order_index for migrated lessons ────────────────────────────
UPDATE professor_lessons pl
SET order_index = sub.rn
FROM (
    SELECT id, ROW_NUMBER() OVER (PARTITION BY professor_course_id ORDER BY id) AS rn
    FROM professor_lessons
    WHERE professor_course_id IS NOT NULL
) sub
WHERE pl.id = sub.id;

-- ── 6. New fields on public courses table ─────────────────────────────────
ALTER TABLE courses
    ADD COLUMN IF NOT EXISTS subtitulo      VARCHAR(300) NULL,
    ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS categoria      VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS preco          NUMERIC(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS published_at   TIMESTAMP    NULL;

-- ── 7. Add order_index to public course_lessons ───────────────────────────
ALTER TABLE course_lessons
    ADD COLUMN IF NOT EXISTS order_index SMALLINT NOT NULL DEFAULT 0;

-- ── 8. Indexes ────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_professor_lessons_course_id
    ON professor_lessons (professor_course_id);

CREATE INDEX IF NOT EXISTS idx_professor_lessons_order
    ON professor_lessons (professor_course_id, order_index);
