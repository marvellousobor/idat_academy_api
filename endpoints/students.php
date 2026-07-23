<?php
/**
 * GET /students          — List students (paginated, searchable)
 * GET /students/{id}     — Single student with enrollments + stats
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    if ($method !== 'GET') {
        jsonResponse(405, ['error' => 'Method not allowed.']);
    }

    if ($id !== null) {
        getStudent($conn, $id);
    }

    listStudents($conn);
}

function listStudents(mysqli $conn): void
{
    $page         = max(1, (int) ($_GET['page'] ?? 1));
    $perPage      = max(0, min(100, (int) ($_GET['per_page'] ?? 20)));
    $fetchAll     = $perPage === 0 || (($_GET['all'] ?? '') === '1');
    $status       = $_GET['status'] ?? null;
    $search       = $_GET['search'] ?? null;
    $applicationId = $_GET['application_id'] ?? null;

    $sql    = "SELECT id, first_name, last_name, other_name, email, phone, gender, status, created_at
               FROM students WHERE 1=1";
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
    if ($applicationId) {
        $sql .= " AND id = (SELECT student_id FROM applications WHERE id = ?)";
        $params[] = (int) $applicationId;
        $types   .= 'i';
    }

    $sql .= " ORDER BY created_at DESC";

    $pagination = null;
    if ($fetchAll) {
        $countSql = preg_replace('/SELECT .+? FROM/iS', 'SELECT COUNT(*) as total FROM', $sql, 1);
        $countSql = preg_replace('/ORDER BY .+$/iS', '', $countSql);
        $countRow = fetchOne($conn, $countSql, $params, $types);
        $total = (int) ($countRow['total'] ?? 0);
        $rows = fetchAll($conn, $sql, $params, $types);
        $pagination = ['current_page' => 1, 'per_page' => $total, 'total' => $total, 'total_pages' => 1];
    } else {
        $pagination = applyPagination($conn, $sql, $params, $types, $page, $perPage);
        $rows = fetchAll($conn, $sql, $params, $types);
    }

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getStudent(mysqli $conn, string $id): void
{
    $student = fetchOne($conn,
        "SELECT id, first_name, last_name, other_name, email, phone, gender,
                date_of_birth, state, lga, address, photo, status, created_at
         FROM students WHERE id = ? LIMIT 1",
        [$id], 'i'
    );

    if (!$student) {
        jsonResponse(404, ['error' => 'Student not found.']);
    }

    $student['enrollments'] = fetchAll($conn,
        "SELECT sc.*, c.title AS course_title, c.slug AS course_slug, c.icon, c.duration
         FROM student_courses sc
         JOIN courses c ON sc.course_id = c.id
         WHERE sc.student_id = ?
         ORDER BY sc.enrolled_at DESC",
        [$student['id']], 'i'
    );

    $student['stats'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM student_courses WHERE student_id = ?) AS total_courses,
            (SELECT COUNT(*) FROM student_courses WHERE student_id = ? AND status = 'completed') AS completed_courses,
            (SELECT COALESCE(AVG(progress), 0) FROM student_courses WHERE student_id = ?) AS avg_progress,
            (SELECT COUNT(*) FROM assignment_submissions WHERE student_id = ?) AS total_submissions,
            (SELECT COUNT(*) FROM certificates WHERE student_id = ?) AS certificates,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = ? AND status = 'confirmed') AS total_paid",
        [$student['id'], $student['id'], $student['id'], $student['id'], $student['id'], $student['id']], 'iiiiii'
    );

    jsonResponse(200, ['data' => $student]);
}
