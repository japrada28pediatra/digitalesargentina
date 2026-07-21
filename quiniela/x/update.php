<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

if (PHP_SAPI !== 'cli') header('Content-Type: text/plain; charset=utf-8');

try {
    $page = request(SOURCE_URL);
    $cookie = null;
    foreach ($page['headers'] as $header) {
        if (preg_match('/^(PHPSESSID=[^;]+)/', $header, $match)) $cookie = $match[1];
    }
    if ($cookie === null) throw new RuntimeException('Notitimba no inició una sesión');

    $live = request(RESULTS_URL, $cookie)['body'];
    if (str_contains($live, 'ERROR - No synchronization')) throw new RuntimeException('Notitimba está sin sincronización');
    $parts = explode('|', $live);
    $currentHtml = isset($parts[1]) && str_contains($parts[1], 'qCnt') ? $parts[1] : $live;

    $now = date(DATE_ATOM);
    save_json(TODAY_FILE, parse_today($currentHtml) + ['updated_at' => $now, 'source' => SOURCE_URL]);
    save_json(HISTORY_FILE, ['updated_at' => $now, 'source' => SOURCE_URL, 'days' => parse_history($page['body'])]);
    echo "Actualización completa: $now\n";
} catch (Throwable $error) {
    if (PHP_SAPI === 'cli') fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n");
    else {
        http_response_code(500);
        echo 'Error: ' . $error->getMessage() . "\n";
    }
    exit(1);
}
