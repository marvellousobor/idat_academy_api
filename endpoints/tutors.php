<?php
/**
 * GET /tutors          — List tutors (paginated, searchable)
 * GET /tutors/{id}     — Single tutor with courses + stats
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    if ($method !== 'GET') {
        jsonResponse(405, ['error' => 'Method not allowed.']);
    }

    if ($id !== null) {
        getTutor($conn, $id);
    }

    listTutors($conn);
}

function listTutors(mysqli $conn): void
{
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $status  = $_GET['status'] ?? null;
    $search  = $_GET['search'] ?? null;

    $sql    = "SELECT id, first_name, last_name, email, phone, bio, photo, status, created_at
               FROM tutors WHERE 1=1";
    $params = [];
    $types  = '';

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types   .= 's';
    }
    if ($search) {
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $types   .= 'sss';
    }

    $sql .= " ORDER BY created_at DESC";
    $pagination = applyPagination($conn, $sql, $params, $types, $page, $perPage);
    $rows = fetchAll($conn, $sql, $params, $types);

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getTutor(mysqli $conn, string $id): void
{
    $tutor = fetchOne($conn,
        "SELECT id, first_name, last_name, email, phone, bio, photo, status, created_at
         FROM tutors WHERE id = ? LIMIT 1",
        [(int) $id], 'i'
    );

    if (!$tutor) {
        jsonResponse(404, ['error' => 'Tutor not found.']);
    }

    $tutor['courses'] = fetchAll($conn,
        "SELECT id, title, slug, icon, duration, category, status
         FROM courses WHERE instructor_id = ? ORDER BY created_at DESC",
        [$tutor['id']], 'i'
    );

    $tutor['stats'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM courses WHERE instructor_id = ?) AS total_courses,
            (SELECT COUNT(*) FROM lessons WHERE tutor_id = ?) AS total_lessons,
            (SELECT COUNT(*) FROM assignments WHERE tutor_id = ?) AS total_assignments",
        [$tutor['id'], $tutor['id'], $tutor['id']], 'iii'
    );

    jsonResponse(200, ['data' => $tutor]);
}
