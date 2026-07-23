<?php
/**
 * GET /notifications                    — List notifications (filterable)
 * GET /notifications/{id}               — Single notification
 * POST /notifications                   — Create a notification
 * PUT /notifications/{id}               — Mark as read
 * DELETE /notifications/{id}            — Delete a notification
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getNotification($conn, $id) : listNotifications($conn);
            break;
        case 'POST':
            createNotification($conn);
            break;
        case 'PUT':
            if (!$id) jsonResponse(400, ['error' => 'Notification ID required.']);
            updateNotification($conn, $id);
            break;
        case 'DELETE':
            if (!$id) jsonResponse(400, ['error' => 'Notification ID required.']);
            deleteNotification($conn, $id);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listNotifications(mysqli $conn): void
{
    $page      = max(1, (int) ($_GET['page'] ?? 1));
    $perPage   = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $studentId = $_GET['student_id'] ?? null;
    $type      = $_GET['type'] ?? null;
    $isRead    = $_GET['is_read'] ?? null;

    $sql    = "SELECT n.*, s.first_name, s.last_name
               FROM notifications n
               JOIN students s ON n.student_id = s.id
               WHERE 1=1";
    $params = [];
    $types  = '';

    if ($studentId) {
        $sql .= " AND n.student_id = ?";
        $params[] = (int) $studentId;
        $types   .= 'i';
    }
    if ($type) {
        $sql .= " AND n.type = ?";
        $params[] = $type;
        $types   .= 's';
    }
    if ($isRead !== null) {
        $sql .= " AND n.is_read = ?";
        $params[] = (int) $isRead;
        $types   .= 'i';
    }

    $sql .= " ORDER BY n.created_at DESC";
    $pagination = applyPagination($conn, $sql, $params, $types, $page, $perPage);
    $rows = fetchAll($conn, $sql, $params, $types);

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getNotification(mysqli $conn, string $id): void
{
    $row = fetchOne($conn,
        "SELECT n.*, s.first_name, s.last_name
         FROM notifications n
         JOIN students s ON n.student_id = s.id
         WHERE n.id = ? LIMIT 1",
        [(int) $id], 'i'
    );

    if (!$row) {
        jsonResponse(404, ['error' => 'Notification not found.']);
    }
    jsonResponse(200, ['data' => $row]);
}

function createNotification(mysqli $conn): void
{
    $input = getInput();

    $studentId = $input['student_id'] ?? null;
    $title     = $input['title'] ?? null;

    if (!$studentId || !$title) {
        jsonResponse(422, ['error' => 'student_id and title are required.']);
    }

    if (!fetchOne($conn, "SELECT id FROM students WHERE id = ?", [$studentId], 'i')) {
        jsonResponse(404, ['error' => 'Student not found.']);
    }

    $type    = $input['type'] ?? 'general';
    $message = $input['message'] ?? null;

    if (!in_array($type, ['lesson', 'assignment', 'deadline', 'completion', 'certificate', 'announcement', 'general'], true)) {
        jsonResponse(422, ['error' => 'Invalid notification type.']);
    }

    $result = execute($conn,
        "INSERT INTO notifications (student_id, type, title, message) VALUES (?, ?, ?, ?)",
        [$studentId, $type, $title, $message],
        'isss'
    );

    jsonResponse(201, ['message' => 'Notification created.', 'id' => $result['insert_id']]);
}

function updateNotification(mysqli $conn, string $id): void
{
    $input = getInput();

    $notification = fetchOne($conn, "SELECT * FROM notifications WHERE id = ?", [(int) $id], 'i');
    if (!$notification) {
        jsonResponse(404, ['error' => 'Notification not found.']);
    }

    $isRead = isset($input['is_read']) ? (int) $input['is_read'] : 1;

    execute($conn,
        "UPDATE notifications SET is_read = ? WHERE id = ?",
        [$isRead, (int) $id], 'ii'
    );

    jsonResponse(200, ['message' => 'Notification updated.']);
}

function deleteNotification(mysqli $conn, string $id): void
{
    $notification = fetchOne($conn, "SELECT * FROM notifications WHERE id = ?", [(int) $id], 'i');
    if (!$notification) {
        jsonResponse(404, ['error' => 'Notification not found.']);
    }

    execute($conn, "DELETE FROM notifications WHERE id = ?", [(int) $id], 'i');

    jsonResponse(200, ['message' => 'Notification deleted.']);
}
