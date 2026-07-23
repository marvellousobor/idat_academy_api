<?php
/**
 * GET /contact_messages              — List contact messages
 * GET /contact_messages/{id}         — Single message
 * POST /contact_messages             — Submit a contact message
 * PUT /contact_messages/{id}         — Mark as read
 * DELETE /contact_messages/{id}      — Delete
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getContactMessage($conn, $id) : listContactMessages($conn);
            break;
        case 'POST':
            createContactMessage($conn);
            break;
        case 'PUT':
            if (!$id) jsonResponse(400, ['error' => 'Message ID required.']);
            updateContactMessage($conn, $id);
            break;
        case 'DELETE':
            if (!$id) jsonResponse(400, ['error' => 'Message ID required.']);
            deleteContactMessage($conn, $id);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listContactMessages(mysqli $conn): void
{
    $isRead = $_GET['is_read'] ?? null;
    $sql = "SELECT * FROM contact_messages WHERE 1=1";
    $params = [];
    $types = '';

    if ($isRead !== null) {
        $sql .= " AND is_read = ?";
        $params[] = (int) $isRead;
        $types .= 'i';
    }

    $sql .= " ORDER BY created_at DESC";
    $rows = fetchAll($conn, $sql, $params, $types);
    jsonResponse(200, ['data' => $rows]);
}

function getContactMessage(mysqli $conn, string $id): void
{
    $row = fetchOne($conn, "SELECT * FROM contact_messages WHERE id = ? LIMIT 1", [(int) $id], 'i');
    if (!$row) jsonResponse(404, ['error' => 'Message not found.']);
    jsonResponse(200, ['data' => $row]);
}

function createContactMessage(mysqli $conn): void
{
    $input = getInput();
    $name    = $input['name'] ?? null;
    $email   = $input['email'] ?? null;
    $message = $input['message'] ?? null;

    if (!$name || !$email || !$message) {
        jsonResponse(422, ['error' => 'name, email, and message are required.']);
    }

    $phone   = $input['phone'] ?? null;
    $subject = $input['subject'] ?? null;

    $result = execute($conn,
        "INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)",
        [$name, $email, $phone, $subject, $message], 'sssss'
    );

    jsonResponse(201, ['message' => 'Message sent.', 'id' => $result['insert_id']]);
}

function updateContactMessage(mysqli $conn, string $id): void
{
    $input = getInput();
    $isRead = isset($input['is_read']) ? (int) $input['is_read'] : 1;

    execute($conn, "UPDATE contact_messages SET is_read = ? WHERE id = ?", [$isRead, (int) $id], 'ii');
    jsonResponse(200, ['message' => 'Message updated.']);
}

function deleteContactMessage(mysqli $conn, string $id): void
{
    $row = fetchOne($conn, "SELECT * FROM contact_messages WHERE id = ?", [(int) $id], 'i');
    if (!$row) jsonResponse(404, ['error' => 'Message not found.']);

    execute($conn, "DELETE FROM contact_messages WHERE id = ?", [(int) $id], 'i');
    jsonResponse(200, ['message' => 'Message deleted.']);
}
