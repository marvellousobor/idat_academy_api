<?php
/**
 * GET /testimonials              — List testimonials (filterable)
 * GET /testimonials/{id}         — Single testimonial
 * POST /testimonials             — Submit a testimonial
 * PUT /testimonials/{id}         — Approve/reject
 * DELETE /testimonials/{id}      — Delete
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getTestimonial($conn, $id) : listTestimonials($conn);
            break;
        case 'POST':
            createTestimonial($conn);
            break;
        case 'PUT':
            if (!$id) jsonResponse(400, ['error' => 'Testimonial ID required.']);
            updateTestimonial($conn, $id);
            break;
        case 'DELETE':
            if (!$id) jsonResponse(400, ['error' => 'Testimonial ID required.']);
            deleteTestimonial($conn, $id);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listTestimonials(mysqli $conn): void
{
    $status = $_GET['status'] ?? null;
    $sql = "SELECT * FROM testimonials WHERE 1=1";
    $params = [];
    $types = '';

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }

    $sql .= " ORDER BY created_at DESC";
    $rows = fetchAll($conn, $sql, $params, $types);
    jsonResponse(200, ['data' => $rows]);
}

function getTestimonial(mysqli $conn, string $id): void
{
    $row = fetchOne($conn, "SELECT * FROM testimonials WHERE id = ? LIMIT 1", [(int) $id], 'i');
    if (!$row) jsonResponse(404, ['error' => 'Testimonial not found.']);
    jsonResponse(200, ['data' => $row]);
}

function createTestimonial(mysqli $conn): void
{
    $input = getInput();
    $name    = $input['name'] ?? null;
    $message = $input['message'] ?? null;
    $rating  = (int) ($input['rating'] ?? 5);

    if (!$name || !$message) {
        jsonResponse(422, ['error' => 'name and message are required.']);
    }

    $result = execute($conn,
        "INSERT INTO testimonials (name, message, rating, status) VALUES (?, ?, ?, 'pending')",
        [$name, $message, $rating], 'ssi'
    );

    jsonResponse(201, ['message' => 'Testimonial submitted.', 'id' => $result['insert_id']]);
}

function updateTestimonial(mysqli $conn, string $id): void
{
    $input = getInput();
    $testimonial = fetchOne($conn, "SELECT * FROM testimonials WHERE id = ?", [(int) $id], 'i');
    if (!$testimonial) jsonResponse(404, ['error' => 'Testimonial not found.']);

    $status = $input['status'] ?? $testimonial['status'];
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        jsonResponse(422, ['error' => 'Status must be pending, approved, or rejected.']);
    }

    $approvedAt = $status === 'approved' ? 'NOW()' : 'NULL';
    execute($conn,
        "UPDATE testimonials SET status = ?, approved_at = IF('{$status}' = 'approved', NOW(), approved_at) WHERE id = ?",
        [$status, (int) $id], 'si'
    );

    jsonResponse(200, ['message' => 'Testimonial updated.']);
}

function deleteTestimonial(mysqli $conn, string $id): void
{
    $testimonial = fetchOne($conn, "SELECT * FROM testimonials WHERE id = ?", [(int) $id], 'i');
    if (!$testimonial) jsonResponse(404, ['error' => 'Testimonial not found.']);

    execute($conn, "DELETE FROM testimonials WHERE id = ?", [(int) $id], 'i');
    jsonResponse(200, ['message' => 'Testimonial deleted.']);
}
