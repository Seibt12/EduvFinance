-- Migration 005: cleanup — column defaults, unused column, missing indexes, audit field
-- Idempotent: safe to run multiple times.

-- ─── 1. Drop professor_courses.tags ──────────────────────────────────────────
-- Column has never been written or read by any PHP/JS endpoint (confirmed 2026-06-11).
-- Zero rows contain data. No feature planned.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name   = 'professor_courses'
          AND column_name  = 'tags'
    ) THEN
        ALTER TABLE professor_courses DROP COLUMN tags;
    END IF;
END $$;

-- ─── 2. Fix professor_lessons.status DEFAULT ─────────────────────────────────
-- Code (Fix 17) already writes 'draft', but the column DEFAULT was never updated
-- from the original 'pendente'. Rows inserted without explicit status would get
-- the wrong value.
ALTER TABLE professor_lessons
    ALTER COLUMN status SET DEFAULT 'draft';

-- ─── 3. Add progress.created_at for audit trail ──────────────────────────────
-- progress only has updated_at; created_at helps audit when a lesson was first
-- marked and detect suspicious retroactive changes.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name   = 'progress'
          AND column_name  = 'created_at'
    ) THEN
        ALTER TABLE progress
            ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
    END IF;
END $$;

-- ─── 4. Index professor_courses(professor_id) ────────────────────────────────
-- Every professor listing page filters by professor_id; the only existing index
-- on this table is on status. This index covers the common WHERE professor_id = ?
-- query pattern.
CREATE INDEX IF NOT EXISTS idx_professor_courses_professor_id
    ON professor_courses (professor_id);

-- ─── 5. Index professor_lessons(professor_id) ────────────────────────────────
-- Professors list and filter their own lessons frequently. The existing indexes
-- are on (professor_course_id, order_index) and status; none cover professor_id.
CREATE INDEX IF NOT EXISTS idx_professor_lessons_professor_id
    ON professor_lessons (professor_id);
