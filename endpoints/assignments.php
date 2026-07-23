<?php
/**
 * GET /assignments              — List assignments (filterable by course_id)
 * GET /assignments/{id}         — Single assignment with submission stats
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    if ($method !== 'GET') {
        jsonResponse(405, ['error' => 'Method not allowed.']);
    }

    if ($id !== null) {
        getAssignment($conn, $id);
    }

    listAssignments($conn);
}

function listAssignments(mysqli $conn): void
{
    $page      = max(1, (int) ($_GET['page'] ?? 1));
    $perPage   = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $courseId  = $_GET['course_id'] ?? null;
    $search    = $_GET['search'] ?? null;
    $tutorId   = $_GET['tutor_id'] ?? null;
    $moduleId  = $_GET['module_id'] ?? null;

    $sql    = "SELECT a.*, c.title AS course_title, c.slug AS course_slug,
                      t.first_name AS tutor_first, t.last_name AS tutor_last
               FROM assignments a
               JOIN courses c ON a.course_id = c.id
               JOIN tutors t ON a.tutor_id = t.id
               WHERE 1=1";
    $params = [];
    $types  = '';

    if ($courseId) {
        $sql .= " AND a.course_id = ?";
        $params[] = (int) $courseId;
        $types   .= 'i';
    }
    if ($search) {
        $sql .= " AND a.title LIKE ?";
        $params[] = "%{$search}%";
        $types   .= 's';
    }
    if ($tutorId) {
        $sql .= " AND a.tutor_id = ?";
        $params[] = (int) $tutorId;
        $types   .= 'i';
    }
    if ($moduleId) {
        $sql .= " AND a.module_id = ?";
        $params[] = (int) $moduleId;
        $types   .= 'i';
    }

    $sql .= " ORDER BY a.created_at DESC";
    $pagination = applyPagination($conn, $sql, $params, $types, $page, $perPage);
    $rows = fetchAll($conn, $sql, $params, $types);

    foreach ($rows as &$row) {
        $row['tutor_name'] = trim(($row['tutor_first'] ?? '') . ' ' . ($row['tutor_last'] ?? ''));
        unset($row['tutor_first'], $row['tutor_last']);
    }

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getAssignment(mysqli $conn, string $id): void
{
    $assignment = fetchOne($conn,
        "SELECT a.*, c.title AS course_title, c.slug AS course_slug,
                t.first_name AS tutor_first, t.last_name AS tutor_last
         FROM assignments a
         JOIN courses c ON a.course_id = c.id
         JOIN tutors t ON a.tutor_id = t.id
         WHERE a.id = ? LIMIT 1",
        [(int) $id], 'i'
    );

    if (!$assignment) {
        jsonResponse(404, ['error' => 'Assignment not found.']);
    }

    $assignment['tutor_name'] = trim(($assignment['tutor_first'] ?? '') . ' ' . ($assignment['tutor_last'] ?? ''));
    unset($assignment['tutor_first'], $assignment['tutor_last']);

    $assignment['stats'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = ?) AS total_submissions,
            (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = ? AND score IS NOT NULL) AS graded,
            (SELECT COALESCE(AVG(score / NULLIF(max_score, 0) * 100), 0) FROM assignment_submissions WHERE assignment_id = ? AND score IS NOT NULL) AS avg_score",
        [$assignment['id'], $assignment['id'], $assignment['id']], 'iii'
    );

    jsonResponse(200, ['data' => $assignment]);
}
