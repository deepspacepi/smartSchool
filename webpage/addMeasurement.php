<?php

/*
 * TTN Webhook Receiver
 *
 * Database: ttn_packets.db
 * Table: packets
 *
 * Stores:
 *   - device_id
 *   - complete JSON packet
 *   - timestamp
 */

header('Content-Type: application/json');

try {

    // Database file
    $dbFile = __DIR__ . '/smartSchool.db';

    // Connect (creates file automatically if missing)
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if needed
    $db->exec("
        CREATE TABLE IF NOT EXISTS packets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            device_id TEXT,
            packet TEXT NOT NULL
        )
    ");

    // Read incoming payload
    $rawJson = file_get_contents('php://input');

    if (empty($rawJson)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'No payload received'
        ]);
        exit;
    }

    // Validate JSON
    $data = json_decode($rawJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON'
        ]);
        exit;
    }

    // Extract TTN device ID if available
    $deviceId =
        $data['end_device_ids']['device_id']
        ?? 'unknown';

    // Store packet
    $stmt = $db->prepare("
        INSERT INTO packets (
            device_id,
            packet
        )
        VALUES (
            :device_id,
            :packet
        )
    ");

    $stmt->execute([
        ':device_id' => $deviceId,
        ':packet' => $rawJson
    ]);

    echo json_encode([
        'status'    => 'ok',
        'packet_id' => $db->lastInsertId(),
        'device_id' => $deviceId
    ]);

} catch (Exception $e) {

    // Log the actual error for debugging
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error occurred'
    ]);
}
