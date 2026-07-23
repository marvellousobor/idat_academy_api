<?php
/**
 * API Helper Functions
 */

function jsonResponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function connectDB(array $config): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = mysqli_connect($config['host'], $config['user'], $config['password'], $config['database']);
    if (!$conn) {
        jsonResponse(500, ['error' => 'Database connection failed.']);
    }
    mysqli_set_charset($conn, $config['charset'] ?? 'utf8mb4');
    return $conn;
}

function fetchAll(mysqli $conn, string $sql, array $params = [], string $types = ''): array
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function fetchOne(mysqli $conn, string $sql, array $params = [], string $types = ''): ?array
{
    $rows = fetchAll($conn, $sql, $params, $types);
    return $rows[0] ?? null;
}

function execute(mysqli $conn, string $sql, array $params = [], string $types = ''): array
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        jsonResponse(500, ['error' => 'Query preparation failed.']);
    }
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    $ok = mysqli_stmt_execute($stmt);
    $insertId = mysqli_insert_id($conn);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return ['ok' => $ok, 'insert_id' => $insertId, 'affected_rows' => $affected];
}

function getInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function paginate(array $data, int $page, int $perPage): array
{
    $total = count($data);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $sliced = array_slice($data, $offset, $perPage);

    return [
        'data'         => $sliced,
        'pagination'   => [
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'total_pages'  => $totalPages,
        ],
    ];
}

function rateLimit(int $maxRequests): void
{
    $dir = sys_get_temp_dir() . '/idat_api_rate';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key  = md5($ip);
    $file = $dir . '/' . $key;
    $now  = time();

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && ($now - $data['window_start']) < 60) {
            if ($data['count'] >= $maxRequests) {
                jsonResponse(429, ['error' => 'Rate limit exceeded. Try again in ' . (60 - ($now - $data['window_start'])) . 's.']);
            }
            $data['count']++;
        } else {
            $data = ['window_start' => $now, 'count' => 1];
        }
    } else {
        $data = ['window_start' => $now, 'count' => 1];
    }

    file_put_contents($file, json_encode($data));
}

function applyPagination(mysqli $conn, string &$sql, array &$params, string &$types, int $page, int $perPage): array
{
    // Count total
    $countSql = preg_replace('/SELECT .+? FROM/iS', 'SELECT COUNT(*) as total FROM', $sql, 1);
    $countSql = preg_replace('/ORDER BY .+$/iS', '', $countSql);
    $countRow = fetchOne($conn, $countSql, $params, $types);
    $total = (int) ($countRow['total'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';

    return [
        'current_page' => $page,
        'per_page'     => $perPage,
        'total'        => $total,
        'total_pages'  => $totalPages,
    ];
}
