<?php
/**
 * GET /enrollments                    — List all enrollments (paginated)
 * GET /enrollments/{id}               — Single enrollment
 * GET /enrollments?student_id=X       — Enrollments for a student
 * GET /enrollments?course_id=X        — Enrollments for a course
 * POST /enrollments                   — Enroll a student in a course
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getEnrollment($conn, $id) : listEnrollments($conn);
            break;
        case 'POST':
            createEnrollment($conn);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listEnrollments(mysqli $conn): void
{
    $page      = max(1, (int) ($_GET['page'] ?? 1));
    $perPage   = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $studentId = $_GET['student_id'] ?? null;
    $courseId  = $_GET['course_id'] ?? null;
    $status    = $_GET['status'] ?? null;

    $sql    = "SELECT sc.*, s.first_name, s.last_name, s.email,
                      c.title AS course_title, c.slug AS course_slug
               FROM student_courses sc
               JOIN students s ON sc.student_id = s.id
               JOIN courses c ON sc.course_id = c.id
               WHERE 1=1";
    $params = [];
    $types  = '';

    if ($studentId) {
        $sql .= " AND sc.student_id = ?";
        $params[] = (int) $studentId;
        $types   .= 'i';
    }
    if ($courseId) {
        $sql .= " AND sc.course_id = ?";
        $params[] = (int) $courseId;
        $types   .= 'i';
    }
    if ($status) {
        $sql .= " AND sc.status = ?";
        $params[] = $status;
        $types   .= 's';
    }

    $sql .= " ORDER BY sc.enrolled_at DESC";
    $pagination = applyPagination($conn, $sql, $params, $types, $page, $perPage);
    $rows = fetchAll($conn, $sql, $params, $types);

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getEnrollment(mysqli $conn, string $id): void
{
    $row = fetchOne($conn,
        "SELECT sc.*, s.first_name, s.last_name, s.email,
                c.title AS course_title, c.slug AS course_slug
         FROM student_courses sc
         JOIN students s ON sc.student_id = s.id
         JOIN courses c ON sc.course_id = c.id
         WHERE sc.id = ? LIMIT 1",
        [(int) $id], 'i'
    );

    if (!$row) {
        jsonResponse(404, ['error' => 'Enrollment not found.']);
    }
    jsonResponse(200, ['data' => $row]);
}

function createEnrollment(mysqli $conn): void
{
    $input = getInput();
    $studentId = $input['student_id'] ?? null;
    $courseId  = $input['course_id'] ?? null;

    if (!$studentId || !$courseId) {
        jsonResponse(422, ['error' => 'student_id and course_id are required.']);
    }

    // Check student exists
    if (!fetchOne($conn, "SELECT id FROM students WHERE id = ?", [$studentId], 'i')) {
        jsonResponse(404, ['error' => 'Student not found.']);
    }
    // Check course exists
    if (!fetchOne($conn, "SELECT id FROM courses WHERE id = ?", [$courseId], 'i')) {
        jsonResponse(404, ['error' => 'Course not found.']);
    }

    // Check not already enrolled
    $existing = fetchOne($conn,
        "SELECT id FROM student_courses WHERE student_id = ? AND course_id = ?",
        [$studentId, $courseId], 'ii'
    );
    if ($existing) {
        jsonResponse(409, ['error' => 'Student is already enrolled in this course.']);
    }

    $result = execute($conn,
        "INSERT INTO student_courses (student_id, course_id, progress, status) VALUES (?, ?, 0, 'enrolled')",
        [$studentId, $courseId], 'ii'
    );

    jsonResponse(201, ['message' => 'Enrolled successfully.', 'id' => $result['insert_id']]);
}
