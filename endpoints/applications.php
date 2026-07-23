<?php
/**
 * GET /applications              — List applications (filterable)
 * GET /applications/{id}         — Single application
 * POST /applications             — Submit a new application
 * PUT /applications/{id}         — Approve/reject an application
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getApplication($conn, $id) : listApplications($conn);
            break;
        case 'POST':
            createApplication($conn);
            break;
        case 'PUT':
            if (!$id) jsonResponse(400, ['error' => 'Application ID required.']);
            updateApplication($conn, $id);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listApplications(mysqli $conn): void
{
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $status  = $_GET['status'] ?? null;
    $search  = $_GET['search'] ?? null;

    $sql    = "SELECT id, first_name, last_name, other_name, email, phone, gender,
                      preferred_courses, preferred_mode, status, created_at
               FROM applications WHERE 1=1";
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

function getApplication(mysqli $conn, string $id): void
{
    $row = fetchOne($conn,
        "SELECT a.*, ad.name AS reviewer_name
         FROM applications a
         LEFT JOIN admins ad ON a.reviewed_by = ad.id
         WHERE a.id = ? LIMIT 1",
        [(int) $id], 'i'
    );

    if (!$row) {
        jsonResponse(404, ['error' => 'Application not found.']);
    }
    jsonResponse(200, ['data' => $row]);
}

function createApplication(mysqli $conn): void
{
    $input = getInput();

    $firstName = $input['first_name'] ?? null;
    $lastName  = $input['last_name'] ?? null;
    $email     = $input['email'] ?? null;
    $phone     = $input['phone'] ?? null;

    if (!$firstName || !$lastName || !$email || !$phone) {
        jsonResponse(422, ['error' => 'first_name, last_name, email, and phone are required.']);
    }

    $existing = fetchOne($conn,
        "SELECT id FROM applications WHERE email = ? AND status = 'pending'",
        [$email], 's'
    );
    if ($existing) {
        jsonResponse(409, ['error' => 'You already have a pending application.']);
    }

    $otherName      = $input['other_name'] ?? null;
    $gender         = $input['gender'] ?? null;
    $dateOfBirth    = $input['date_of_birth'] ?? null;
    $state          = $input['state'] ?? null;
    $lga            = $input['lga'] ?? null;
    $address        = $input['address'] ?? null;
    $educationLevel = $input['education_level'] ?? null;
    $occupation     = $input['occupation'] ?? null;
    $referralSource = $input['referral_source'] ?? null;
    $preferredMode  = $input['preferred_mode'] ?? 'physical';
    $paymentProof   = $input['payment_proof'] ?? null;
    $termsAgreed    = isset($input['terms_agreed']) ? (int) $input['terms_agreed'] : 0;

    $preferredCourses = null;
    if (isset($input['preferred_courses'])) {
        $preferredCourses = is_array($input['preferred_courses'])
            ? json_encode($input['preferred_courses'])
            : $input['preferred_courses'];
    }

    $result = execute($conn,
        "INSERT INTO applications
            (first_name, last_name, other_name, email, phone, gender, date_of_birth,
             state, lga, address, education_level, occupation, referral_source,
             preferred_courses, preferred_mode, payment_proof, terms_agreed)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$firstName, $lastName, $otherName, $email, $phone, $gender, $dateOfBirth,
         $state, $lga, $address, $educationLevel, $occupation, $referralSource,
         $preferredCourses, $preferredMode, $paymentProof, $termsAgreed],
        'sssssssssssssssiss'
    );

    jsonResponse(201, ['message' => 'Application submitted.', 'id' => $result['insert_id']]);
}

function updateApplication(mysqli $conn, string $id): void
{
    $input = getInput();

    $application = fetchOne($conn, "SELECT * FROM applications WHERE id = ?", [(int) $id], 'i');
    if (!$application) {
        jsonResponse(404, ['error' => 'Application not found.']);
    }

    $status = $input['status'] ?? $application['status'];
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        jsonResponse(422, ['error' => 'Status must be pending, approved, or rejected.']);
    }

    $reviewedBy = $input['reviewed_by'] ?? null;

    execute($conn,
        "UPDATE applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?",
        [$status, $reviewedBy, (int) $id], 'sii'
    );

    jsonResponse(200, ['message' => 'Application updated.']);
}
