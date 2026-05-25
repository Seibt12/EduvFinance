<?php

function syncPublicLesson(PDO $conn, array $oferta): int {
    $publicId = !empty($oferta['public_lesson_id']) ? (int)$oferta['public_lesson_id'] : 0;

    if ($publicId > 0) {
        $stmt = $conn->prepare("
            UPDATE lessons
            SET titulo=?, descricao=?, nivel=?, video_link=?, attachment_path=?, attachment_name=?, updated_at=CURRENT_TIMESTAMP
            WHERE id=?
        ");
        $stmt->execute([
            $oferta['titulo'], $oferta['descricao'], $oferta['nivel'],
            $oferta['video_link'] ?: null, $oferta['attachment_path'] ?: null, $oferta['attachment_name'] ?: null,
            $publicId,
        ]);
        return $publicId;
    }

    $stmt = $conn->prepare("
        INSERT INTO lessons (titulo, descricao, nivel, video_link, attachment_path, attachment_name)
        VALUES (?, ?, ?, ?, ?, ?) RETURNING id
    ");
    $stmt->execute([
        $oferta['titulo'], $oferta['descricao'], $oferta['nivel'],
        $oferta['video_link'] ?: null, $oferta['attachment_path'] ?: null, $oferta['attachment_name'] ?: null,
    ]);
    return (int)$stmt->fetchColumn();
}

function syncPublicCourse(PDO $conn, array $oferta): int {
    $publicId = !empty($oferta['public_course_id']) ? (int)$oferta['public_course_id'] : 0;

    if ($publicId > 0) {
        $stmt = $conn->prepare("
            UPDATE courses
            SET nome=?, subtitulo=?, descricao=?, nivel=?, categoria=?, thumbnail_path=?, updated_at=CURRENT_TIMESTAMP
            WHERE id=?
        ");
        $stmt->execute([
            $oferta['nome'], $oferta['subtitulo'] ?? null, $oferta['descricao'],
            $oferta['nivel'], $oferta['categoria'] ?? null, $oferta['thumbnail_path'] ?? null,
            $publicId
        ]);
        return $publicId;
    }

    $stmt = $conn->prepare("
        INSERT INTO courses (nome, subtitulo, descricao, nivel, categoria, thumbnail_path, published_at)
        VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP) RETURNING id
    ");
    $stmt->execute([
        $oferta['nome'], $oferta['subtitulo'] ?? null, $oferta['descricao'],
        $oferta['nivel'], $oferta['categoria'] ?? null, $oferta['thumbnail_path'] ?? null,
    ]);
    return (int)$stmt->fetchColumn();
}

/**
 * Syncs all lessons belonging to a professor_course (new schema: lessons have professor_course_id).
 * Returns array mapping professor_lesson_id => public_lesson_id.
 */
function syncAllCourseLessons(PDO $conn, int $professorCourseId, int $publicCourseId): array {
    // Remove old public course_lessons links
    $conn->prepare("DELETE FROM course_lessons WHERE course_id = ?")->execute([$publicCourseId]);

    // Fetch all lessons for this professor course ordered by order_index
    $stmt = $conn->prepare("
        SELECT * FROM professor_lessons
        WHERE professor_course_id = ?
        ORDER BY order_index ASC, id ASC
    ");
    $stmt->execute([$professorCourseId]);
    $lessons = $stmt->fetchAll();

    $map = [];
    $insertCL = $conn->prepare("
        INSERT INTO course_lessons (course_id, lesson_id, order_index)
        VALUES (?, ?, ?) ON CONFLICT (course_id, lesson_id) DO UPDATE SET order_index=EXCLUDED.order_index
    ");
    $updateRef = $conn->prepare("
        UPDATE professor_lessons SET status='aprovado', public_lesson_id=? WHERE id=?
    ");

    foreach ($lessons as $i => $lesson) {
        $publicLessonId = syncPublicLesson($conn, $lesson);
        $insertCL->execute([$publicCourseId, $publicLessonId, $i + 1]);
        $updateRef->execute([$publicLessonId, (int)$lesson['id']]);
        $map[(int)$lesson['id']] = $publicLessonId;
    }

    return $map;
}

/**
 * Legacy: sync lessons from professor_course_lessons junction table (old schema).
 * Kept for backwards compatibility with pre-migration data.
 */
function syncCourseLessonsFromProfessor(PDO $conn, int $professorCourseId, int $publicCourseId): void {
    $conn->prepare("DELETE FROM course_lessons WHERE course_id = ?")->execute([$publicCourseId]);

    $stmt = $conn->prepare("
        SELECT pl.public_lesson_id
        FROM professor_course_lessons pcl
        JOIN professor_lessons pl ON pl.id = pcl.professor_lesson_id
        WHERE pcl.professor_course_id = ?
          AND pl.status = 'aprovado'
          AND pl.public_lesson_id IS NOT NULL
    ");
    $stmt->execute([$professorCourseId]);
    $lessonIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $insert = $conn->prepare("INSERT INTO course_lessons (course_id, lesson_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
    foreach ($lessonIds as $lessonId) {
        if ((int)$lessonId > 0) {
            $insert->execute([$publicCourseId, (int)$lessonId]);
        }
    }
}

function linkApprovedLessonToCourses(PDO $conn, int $professorLessonId, int $publicLessonId): void {
    $stmt = $conn->prepare("
        SELECT pc.public_course_id
        FROM professor_course_lessons pcl
        JOIN professor_courses pc ON pc.id = pcl.professor_course_id
        WHERE pcl.professor_lesson_id = ?
          AND pc.status = 'aprovado'
          AND pc.public_course_id IS NOT NULL
    ");
    $stmt->execute([$professorLessonId]);
    $courseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $insert = $conn->prepare("INSERT INTO course_lessons (course_id, lesson_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
    foreach ($courseIds as $courseId) {
        if ((int)$courseId > 0) {
            $insert->execute([(int)$courseId, $publicLessonId]);
        }
    }
}

function removePublicLesson(PDO $conn, ?int $publicLessonId): void {
    if ($publicLessonId && $publicLessonId > 0) {
        $conn->prepare("DELETE FROM lessons WHERE id = ?")->execute([$publicLessonId]);
    }
}

function removePublicCourse(PDO $conn, ?int $publicCourseId): void {
    if ($publicCourseId && $publicCourseId > 0) {
        $conn->prepare("DELETE FROM courses WHERE id = ?")->execute([$publicCourseId]);
    }
}
