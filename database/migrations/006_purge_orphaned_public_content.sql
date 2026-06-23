-- ── 1. Delete orphaned public courses ─────────────────────────────────────
-- Cascades remove course_lessons, course_enrollments and course_reviews.
DELETE FROM courses c
WHERE NOT EXISTS (
    SELECT 1 FROM professor_courses pc
    WHERE pc.public_course_id = c.id
);

-- ── 2. Delete orphaned public lessons ─────────────────────────────────────
-- Cascades remove course_lessons and progress (student progress).
DELETE FROM lessons l
WHERE NOT EXISTS (
    SELECT 1 FROM professor_lessons pl
    WHERE pl.public_lesson_id = l.id
);
