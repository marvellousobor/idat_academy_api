<?php
/**
 * GET /courses          — List all active courses (paginated, filterable)
 * GET /courses/{id}     — Single course by ID or slug
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    if ($method !== 'GET') {
        jsonResponse(405, ['error' => 'Method not allowed.']);
    }

    if ($id !== null) {
        getCourse($conn, $id);
    }

    listCourses($conn);
}

function listCourses(mysqli $conn): void
{
    $page        = max(1, (int) ($_GET['page'] ?? 1));
    $perPage     = max(0, min(100, (int) ($_GET['per_page'] ?? 20)));
    $fetchAll    = $perPage === 0 || (($_GET['all'] ?? '') === '1');
    $category    = $_GET['category'] ?? null;
    $status      = $_GET['status'] ?? 'active';
    $search      = $_GET['search'] ?? null;
    $learningMode = $_GET['learning_mode'] ?? null;
    $sort        = $_GET['sort'] ?? null;

    $sql    = "SELECT c.*, t.first_name AS instructor_first, t.last_name AS instructor_last
               FROM courses c
               LEFT JOIN tutors t ON c.instructor_id = t.id
               WHERE 1=1";
    $params = [];
    $types  = '';

    if ($status) {
        $sql .= " AND c.status = ?";
        $params[] = $status;
        $types   .= 's';
    }
    if ($category) {
        $sql .= " AND c.category = ?";
        $params[] = $category;
        $types   .= 's';
    }
    if ($search) {
        $sql .= " AND (c.title LIKE ? OR c.description LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $types   .= 'ss';
    }
    if ($learningMode) {
        $sql .= " AND c.learning_mode = ?";
        $params[] = $learningMode;
        $types   .= 's';
    }

    $sortColumn = match($sort) {
        'price'   => 'c.price',
        'title'   => 'c.title',
        'rating'  => 'c.rating',
        'default' => 'c.created_at',
        default   => 'c.created_at',
    };
    $sql .= " ORDER BY {$sortColumn} DESC";

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

    foreach ($rows as &$row) {
        $row['instructor_name'] = trim(($row['instructor_first'] ?? '') . ' ' . ($row['instructor_last'] ?? ''));
        unset($row['instructor_first'], $row['instructor_last']);
    }

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getCourse(mysqli $conn, string $id): void
{
    $course = fetchOne($conn,
        "SELECT c.*, t.first_name AS instructor_first, t.last_name AS instructor_last
         FROM courses c LEFT JOIN tutors t ON c.instructor_id = t.id
         WHERE c.id = ? OR c.slug = ? LIMIT 1",
        [$id, $id], 'ss'
    );

    if (!$course) {
        jsonResponse(404, ['error' => 'Course not found.']);
    }

    $course['instructor_name'] = trim(($course['instructor_first'] ?? '') . ' ' . ($course['instructor_last'] ?? ''));
    unset($course['instructor_first'], $course['instructor_last']);

    $course['modules'] = fetchAll($conn,
        "SELECT * FROM course_modules WHERE course_id = ? ORDER BY order_index",
        [$course['id']], 'i'
    );

    $course['stats'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM student_courses WHERE course_id = ?) AS enrolled_students,
            (SELECT COUNT(*) FROM lessons WHERE course_id = ?) AS lesson_count,
            (SELECT COUNT(*) FROM assignments WHERE course_id = ?) AS assignment_count",
        [$course['id'], $course['id'], $course['id']], 'iii'
    );

    jsonResponse(200, ['data' => $course]);
}
