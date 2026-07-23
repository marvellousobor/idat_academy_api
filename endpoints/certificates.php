<?php
/**
 * GET /certificates                  — List certificates (filterable)
 * GET /certificates/{id}             — Single certificate
 * POST /certificates                 — Issue a new certificate
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getCertificate($conn, $id) : listCertificates($conn);
            break;
        case 'POST':
            createCertificate($conn);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listCertificates(mysqli $conn): void
{
    $page      = max(1, (int) ($_GET['page'] ?? 1));
    $perPage   = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $studentId = $_GET['student_id'] ?? null;
    $courseId  = $_GET['course_id'] ?? null;

    $sql    = "SELECT cert.*, s.first_name, s.last_name, s.email,
                      c.title AS course_title, c.slug AS course_slug
               FROM certificates cert
               JOIN students s ON cert.student_id = s.id
               JOIN courses c ON cert.course_id = c.id
               WHERE 1=1";
    $params = [];
    $types  = '';

    if ($studentId) {
        $sql .= " AND cert.student_id = ?";
        $params[] = (int) $studentId;
        $types   .= 'i';
    }
    if ($courseId) {
        $sql .= " AND cert.course_id = ?";
        $params[] = (int) $courseId;
        $types   .= 'i';
    }

    $sql .= " ORDER BY cert.created_at DESC";
    $pagination = applyPagination($conn, $sql, $params, $types, $page, $perPage);
    $rows = fetchAll($conn, $sql, $params, $types);

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getCertificate(mysqli $conn, string $id): void
{
    $row = fetchOne($conn,
        "SELECT cert.*, s.first_name, s.last_name, s.email,
                c.title AS course_title, c.slug AS course_slug
         FROM certificates cert
         JOIN students s ON cert.student_id = s.id
         JOIN courses c ON cert.course_id = c.id
         WHERE cert.id = ? LIMIT 1",
        [(int) $id], 'i'
    );

    if (!$row) {
        jsonResponse(404, ['error' => 'Certificate not found.']);
    }
    jsonResponse(200, ['data' => $row]);
}

function createCertificate(mysqli $conn): void
{
    $input = getInput();

    $studentId = $input['student_id'] ?? null;
    $courseId  = $input['course_id'] ?? null;

    if (!$studentId || !$courseId) {
        jsonResponse(422, ['error' => 'student_id and course_id are required.']);
    }

    if (!fetchOne($conn, "SELECT id FROM students WHERE id = ?", [$studentId], 'i')) {
        jsonResponse(404, ['error' => 'Student not found.']);
    }
    if (!fetchOne($conn, "SELECT id FROM courses WHERE id = ?", [$courseId], 'i')) {
        jsonResponse(404, ['error' => 'Course not found.']);
    }

    $existing = fetchOne($conn,
        "SELECT id FROM certificates WHERE student_id = ? AND course_id = ?",
        [$studentId, $courseId], 'ii'
    );
    if ($existing) {
        jsonResponse(409, ['error' => 'Certificate already exists for this student and course.']);
    }

    $certificateNumber = $input['certificate_number'] ?? 'IDAT-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $filePath    = $input['file_path'] ?? null;
    $issueDate   = $input['issue_date'] ?? date('Y-m-d');

    $result = execute($conn,
        "INSERT INTO certificates (student_id, course_id, certificate_number, file_path, issue_date)
         VALUES (?, ?, ?, ?, ?)",
        [$studentId, $courseId, $certificateNumber, $filePath, $issueDate],
        'iisss'
    );

    jsonResponse(201, ['message' => 'Certificate issued.', 'id' => $result['insert_id']]);
}
