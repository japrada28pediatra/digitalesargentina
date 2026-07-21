<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $day = $_GET['day'] ?? 'today';
    if ($day === 'dates') {
        $history = json_decode((string) file_get_contents(HISTORY_FILE), true, 512, JSON_THROW_ON_ERROR);
        echo json_encode(['dates' => array_column($history['days'], 'date_label')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($day === 'history') {
        $history = json_decode((string) file_get_contents(HISTORY_FILE), true, 512, JSON_THROW_ON_ERROR);
        $index = max(0, (int) ($_GET['index'] ?? 0));
        if (!isset($history['days'][$index])) throw new RuntimeException('Fecha no disponible');
        echo json_encode($history['days'][$index] + ['updated_at' => $history['updated_at'], 'is_history' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!is_file(TODAY_FILE)) throw new RuntimeException('Todavía no se ejecutó update.php');
    readfile(TODAY_FILE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
}

