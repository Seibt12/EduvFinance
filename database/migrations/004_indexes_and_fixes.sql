-- Migration 004: Indexes, constraint fixes and type corrections
-- Safe to run multiple times (IF NOT EXISTS / ON CONFLICT DO NOTHING).
-- Run after 003_course_builder.sql.

-- ── 1. Add missing FK indexes ─────────────────────────────────────────────

CREATE INDEX IF NOT EXISTS idx_course_lessons_lesson_id
    ON course_lessons (lesson_id);

CREATE INDEX IF NOT EXISTS idx_course_enrollments_course_id
    ON course_enrollments (course_id);

CREATE INDEX IF NOT EXISTS idx_progress_lesson_id
    ON progress (lesson_id);

CREATE INDEX IF NOT EXISTS idx_pcl_lesson_id
    ON professor_course_lessons (professor_lesson_id);

CREATE INDEX IF NOT EXISTS idx_courses_published_at
    ON courses (published_at);

-- ── 2. Fix professor_lessons.status constraint to allow 'draft' ───────────
-- Lessons inside the new Course Builder start as 'pendente' when the course
-- is submitted, but need 'draft' while the course itself is in draft status.

ALTER TABLE professor_lessons
    DROP CONSTRAINT IF EXISTS professor_lessons_status_check;

ALTER TABLE professor_lessons
    ADD CONSTRAINT professor_lessons_status_check
    CHECK (status IN ('draft', 'pendente', 'aprovado', 'rejeitado'));

-- ── 3. Convert investor_profile.respostas from TEXT to JSONB ─────────────
-- Validate first: rows with invalid JSON will cause the cast to fail.
-- The SELECT below can be run manually to check:
--   SELECT id FROM investor_profile WHERE respostas !~ '^(\{|\[)';

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'investor_profile'
          AND column_name = 'respostas'
          AND data_type = 'text'
    ) THEN
        ALTER TABLE investor_profile
            ALTER COLUMN respostas TYPE JSONB
            USING respostas::JSONB;
    END IF;
END
$$;
