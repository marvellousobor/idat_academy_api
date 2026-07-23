<?php
/**
 * GET /lessons              — List lessons (filterable by course_id or course slug)
 * GET /lessons/{id}         — Single lesson with download info
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    if ($method !== 'GET') {
        jsonResponse(405, ['error' => 'Method not allowed.']);
    }

    if ($id !== null) {
        getLesson($conn, $id);
    }

    listLessons($conn);
}

function listLessons(mysqli $conn): void
{
    $page      = max(1, (int) ($_GET['page'] ?? 1));
    $perPage   = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $courseId  = $_GET['course_id'] ?? null;
    $search    = $_GET['search'] ?? null;
    $tutorId   = $_GET['tutor_id'] ?? null;
    $moduleId  = $_GET['module_id'] ?? null;

    $sql    = "SELECT l.*, c.title AS course_title, c.slug AS course_slug,
                      t.first_name AS tutor_first, t.last_name AS tutor_last
               FROM lessons l
               JOIN courses c ON l.course_id = c.id
               JOIN tutors t ON l.tutor_id = t.id
               WHERE 1=1";
    $params = [];
    $types  = '';

    if ($courseId) {
        $sql .= " AND l.course_id = ?";
        $params[] = (int) $courseId;
        $types   .= 'i';
    }
    if ($search) {
        $sql .= " AND l.title LIKE ?";
        $params[] = "%{$search}%";
        $types   .= 's';
    }
    if ($tutorId) {
        $sql .= " AND l.tutor_id = ?";
        $params[] = (int) $tutorId;
        $types   .= 'i';
    }
    if ($moduleId) {
        $sql .= " AND l.module_id = ?";
        $params[] = (int) $moduleId;
        $types   .= 'i';
    }

    $sql .= " ORDER BY l.created_at DESC";
    $pagination = applyPagination($conn, $sql, $params, $types, $page, $perPage);
    $rows = fetchAll($conn, $sql, $params, $types);

    foreach ($rows as &$row) {
        $row['tutor_name'] = trim(($row['tutor_first'] ?? '') . ' ' . ($row['tutor_last'] ?? ''));
        unset($row['tutor_first'], $row['tutor_last']);
    }

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getLesson(mysqli $conn, string $id): void
{
    $lesson = fetchOne($conn,
        "SELECT l.*, c.title AS course_title, c.slug AS course_slug,
                t.first_name AS tutor_first, t.last_name AS tutor_last
         FROM lessons l
         JOIN courses c ON l.course_id = c.id
         JOIN tutors t ON l.tutor_id = t.id
         WHERE l.id = ? LIMIT 1",
        [(int) $id], 'i'
    );

    if (!$lesson) {
        jsonResponse(404, ['error' => 'Lesson not found.']);
    }

    $lesson['tutor_name'] = trim(($lesson['tutor_first'] ?? '') . ' ' . ($lesson['tutor_last'] ?? ''));
    unset($lesson['tutor_first'], $lesson['tutor_last']);

    jsonResponse(200, ['data' => $lesson]);
}
