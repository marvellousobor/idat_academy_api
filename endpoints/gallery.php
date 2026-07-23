<?php
/**
 * GET /gallery              — List gallery images (filterable)
 * GET /gallery/{id}         — Single image
 * POST /gallery             — Upload image
 * DELETE /gallery/{id}      — Delete image
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getGalleryImage($conn, $id) : listGallery($conn);
            break;
        case 'POST':
            createGalleryImage($conn);
            break;
        case 'DELETE':
            if (!$id) jsonResponse(400, ['error' => 'Gallery ID required.']);
            deleteGalleryImage($conn, $id);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listGallery(mysqli $conn): void
{
    $category = $_GET['category'] ?? null;
    $sql = "SELECT g.*, a.name AS uploader_name FROM gallery g LEFT JOIN admins a ON a.id = g.uploaded_by WHERE 1=1";
    $params = [];
    $types = '';

    if ($category) {
        $sql .= " AND g.category = ?";
        $params[] = $category;
        $types .= 's';
    }

    $sql .= " ORDER BY g.created_at DESC";
    $rows = fetchAll($conn, $sql, $params, $types);
    jsonResponse(200, ['data' => $rows]);
}

function getGalleryImage(mysqli $conn, string $id): void
{
    $row = fetchOne($conn,
        "SELECT g.*, a.name AS uploader_name FROM gallery g LEFT JOIN admins a ON a.id = g.uploaded_by WHERE g.id = ? LIMIT 1",
        [(int) $id], 'i'
    );
    if (!$row) jsonResponse(404, ['error' => 'Image not found.']);
    jsonResponse(200, ['data' => $row]);
}

function createGalleryImage(mysqli $conn): void
{
    $input = getInput();
    $title    = $input['title'] ?? '';
    $category = $input['category'] ?? 'event';
    $imagePath = $input['image_path'] ?? null;
    $uploadedBy = $input['uploaded_by'] ?? null;

    if (!$imagePath) {
        jsonResponse(422, ['error' => 'image_path is required.']);
    }

    $result = execute($conn,
        "INSERT INTO gallery (title, image_path, category, uploaded_by) VALUES (?, ?, ?, ?)",
        [$title, $imagePath, $category, $uploadedBy], 'sssi'
    );

    jsonResponse(201, ['message' => 'Image uploaded.', 'id' => $result['insert_id']]);
}

function deleteGalleryImage(mysqli $conn, string $id): void
{
    $row = fetchOne($conn, "SELECT image_path FROM gallery WHERE id = ?", [(int) $id], 'i');
    if (!$row) jsonResponse(404, ['error' => 'Image not found.']);

    execute($conn, "DELETE FROM gallery WHERE id = ?", [(int) $id], 'i');
    jsonResponse(200, ['message' => 'Image deleted.']);
}
