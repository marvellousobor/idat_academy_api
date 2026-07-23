<?php
/**
 * GET /announcements              — List announcements (filterable)
 * GET /announcements/{id}         — Single announcement
 * POST /announcements             — Create an announcement
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getAnnouncement($conn, $id) : listAnnouncements($conn);
            break;
        case 'POST':
            createAnnouncement($conn);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listAnnouncements(mysqli $conn): void
{
    $tutorId  = $_GET['tutor_id'] ?? null;
    $courseId = $_GET['course_id'] ?? null;

    $sql = "SELECT ann.*, t.first_name AS tutor_first, t.last_name AS tutor_last,
                   c.title AS course_title
            FROM announcements ann
            JOIN tutors t ON ann.tutor_id = t.id
            LEFT JOIN courses c ON ann.course_id = c.id
            WHERE 1=1";
    $params = [];
    $types = '';

    if ($tutorId) {
        $sql .= " AND ann.tutor_id = ?";
        $params[] = (int) $tutorId;
        $types .= 'i';
    }
    if ($courseId) {
        $sql .= " AND ann.course_id = ?";
        $params[] = (int) $courseId;
        $types .= 'i';
    }

    $sql .= " ORDER BY ann.created_at DESC";
    $rows = fetchAll($conn, $sql, $params, $types);

    foreach ($rows as &$row) {
        $row['tutor_name'] = trim(($row['tutor_first'] ?? '') . ' ' . ($row['tutor_last'] ?? ''));
        unset($row['tutor_first'], $row['tutor_last']);
    }

    jsonResponse(200, ['data' => $rows]);
}

function getAnnouncement(mysqli $conn, string $id): void
{
    $row = fetchOne($conn,
        "SELECT ann.*, t.first_name AS tutor_first, t.last_name AS tutor_last,
                c.title AS course_title
         FROM announcements ann
         JOIN tutors t ON ann.tutor_id = t.id
         LEFT JOIN courses c ON ann.course_id = c.id
         WHERE ann.id = ? LIMIT 1",
        [(int) $id], 'i'
    );
    if (!$row) jsonResponse(404, ['error' => 'Announcement not found.']);
    jsonResponse(200, ['data' => $row]);
}

function createAnnouncement(mysqli $conn): void
{
    $input = getInput();
    $tutorId  = $input['tutor_id'] ?? null;
    $title    = $input['title'] ?? null;
    $message  = $input['message'] ?? null;

    if (!$tutorId || !$title || !$message) {
        jsonResponse(422, ['error' => 'tutor_id, title, and message are required.']);
    }

    $courseId       = $input['course_id'] ?? null;
    $recipientCount = (int) ($input['recipient_count'] ?? 0);

    $result = execute($conn,
        "INSERT INTO announcements (tutor_id, course_id, title, message, recipient_count) VALUES (?, ?, ?, ?, ?)",
        [$tutorId, $courseId, $title, $message, $recipientCount], 'iissi'
    );

    jsonResponse(201, ['message' => 'Announcement created.', 'id' => $result['insert_id']]);
}
