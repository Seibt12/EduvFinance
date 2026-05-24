-- Migration 002: Course Reviews & Ratings
-- Creates the course_reviews table with all necessary constraints and indexes.

CREATE TABLE IF NOT EXISTS course_reviews (
    id         SERIAL       PRIMARY KEY,
    course_id  INTEGER      NOT NULL REFERENCES courses(id)  ON DELETE CASCADE,
    student_id INTEGER      NOT NULL REFERENCES users(id)    ON DELETE CASCADE,
    rating     SMALLINT     NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT         NULL,
    is_hidden  BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (course_id, student_id)
);

CREATE INDEX IF NOT EXISTS idx_course_reviews_course_id   ON course_reviews (course_id);
CREATE INDEX IF NOT EXISTS idx_course_reviews_student_id  ON course_reviews (student_id);
CREATE INDEX IF NOT EXISTS idx_course_reviews_rating      ON course_reviews (course_id, rating);
