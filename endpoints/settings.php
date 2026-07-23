<?php
/**
 * GET /settings              — List all settings
 * GET /settings/{key}        — Single setting by key
 * POST /settings             — Create/update settings
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getSetting($conn, $id) : listSettings($conn);
            break;
        case 'POST':
            createOrUpdateSettings($conn);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listSettings(mysqli $conn): void
{
    $keys = $_GET['keys'] ?? null;
    $sql = "SELECT setting_key, setting_value FROM settings";
    $params = [];
    $types = '';

    if ($keys) {
        $keyList = array_filter(explode(',', $keys));
        $placeholders = implode(',', array_fill(0, count($keyList), '?'));
        $sql .= " WHERE setting_key IN ($placeholders)";
        $params = array_values($keyList);
        $types = str_repeat('s', count($keyList));
    }

    $sql .= " ORDER BY setting_key ASC";
    $rows = fetchAll($conn, $sql, $params, $types);
    jsonResponse(200, ['data' => $rows]);
}

function getSetting(mysqli $conn, string $key): void
{
    $row = fetchOne($conn,
        "SELECT setting_key, setting_value FROM settings WHERE setting_key = ? LIMIT 1",
        [urldecode($key)], 's'
    );

    if (!$row) {
        jsonResponse(404, ['error' => 'Setting not found.']);
    }
    jsonResponse(200, ['data' => $row]);
}

function createOrUpdateSettings(mysqli $conn): void
{
    $input = getInput();

    if (isset($input['settings']) && is_array($input['settings'])) {
        foreach ($input['settings'] as $key => $value) {
            execute($conn,
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$key, $value], 'ss'
            );
        }
        jsonResponse(200, ['message' => 'Settings saved.']);
    }

    $key   = $input['setting_key'] ?? null;
    $value = $input['setting_value'] ?? null;

    if (!$key) {
        jsonResponse(422, ['error' => 'setting_key is required.']);
    }

    execute($conn,
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [$key, $value ?? ''], 'ss'
    );

    jsonResponse(200, ['message' => 'Setting saved.']);
}
