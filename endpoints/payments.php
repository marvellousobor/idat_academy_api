<?php
/**
 * GET /payments              — List payments (filterable)
 * GET /payments/{id}         — Single payment
 * POST /payments             — Record a new payment
 * PUT /payments/{id}         — Verify/reject a payment
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getPayment($conn, $id) : listPayments($conn);
            break;
        case 'POST':
            createPayment($conn);
            break;
        case 'PUT':
            if (!$id) jsonResponse(400, ['error' => 'Payment ID required.']);
            updatePayment($conn, $id);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listPayments(mysqli $conn): void
{
    $page      = max(1, (int) ($_GET['page'] ?? 1));
    $perPage   = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $studentId = $_GET['student_id'] ?? null;
    $status    = $_GET['status'] ?? null;

    $sql    = "SELECT p.*, s.first_name, s.last_name, s.email
               FROM payments p
               LEFT JOIN students s ON p.student_id = s.id
               WHERE 1=1";
    $params = [];
    $types  = '';

    if ($studentId) {
        $sql .= " AND p.student_id = ?";
        $params[] = (int) $studentId;
        $types   .= 'i';
    }
    if ($status) {
        $sql .= " AND p.status = ?";
        $params[] = $status;
        $types   .= 's';
    }

    $sql .= " ORDER BY p.created_at DESC";
    $pagination = applyPagination($conn, $sql, $params, $types, $page, $perPage);
    $rows = fetchAll($conn, $sql, $params, $types);

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getPayment(mysqli $conn, string $id): void
{
    $row = fetchOne($conn,
        "SELECT p.*, s.first_name, s.last_name, s.email
         FROM payments p
         LEFT JOIN students s ON p.student_id = s.id
         WHERE p.id = ? LIMIT 1",
        [(int) $id], 'i'
    );

    if (!$row) {
        jsonResponse(404, ['error' => 'Payment not found.']);
    }
    jsonResponse(200, ['data' => $row]);
}

function createPayment(mysqli $conn): void
{
    $input = getInput();

    $studentId     = $input['student_id'] ?? null;
    $applicationId = $input['application_id'] ?? null;
    $amount        = $input['amount'] ?? null;
    $proofFile     = $input['proof_file'] ?? null;

    if (!$amount || $amount <= 0) {
        jsonResponse(422, ['error' => 'A valid amount is required.']);
    }
    if (!$studentId && !$applicationId) {
        jsonResponse(422, ['error' => 'student_id or application_id is required.']);
    }

    $result = execute($conn,
        "INSERT INTO payments (student_id, application_id, amount, proof_file, status) VALUES (?, ?, ?, ?, 'pending')",
        [$studentId, $applicationId, $amount, $proofFile], 'iids'
    );

    jsonResponse(201, ['message' => 'Payment recorded.', 'id' => $result['insert_id']]);
}

function updatePayment(mysqli $conn, string $id): void
{
    $input = getInput();

    $payment = fetchOne($conn, "SELECT * FROM payments WHERE id = ?", [(int) $id], 'i');
    if (!$payment) {
        jsonResponse(404, ['error' => 'Payment not found.']);
    }

    $status = $input['status'] ?? $payment['status'];
    if (!in_array($status, ['pending', 'confirmed', 'rejected'], true)) {
        jsonResponse(422, ['error' => 'Status must be pending, confirmed, or rejected.']);
    }

    $verifiedBy = $input['verified_by'] ?? null;

    execute($conn,
        "UPDATE payments SET status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?",
        [$status, $verifiedBy, (int) $id], 'sii'
    );

    jsonResponse(200, ['message' => 'Payment updated.']);
}
