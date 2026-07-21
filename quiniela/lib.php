<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function request(string $url, ?string $cookie = null): array
{
    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; QuinielaResultados/1.0)',
        CURLOPT_ENCODING => '',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (str_contains(strtolower($line), 'set-cookie:')) {
                $headers[] = trim(substr($line, strlen('set-cookie:')));
            }
            return strlen($line);
        },
    ]);
    if ($cookie !== null) {
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_COOKIE => $cookie,
            CURLOPT_REFERER => SOURCE_URL,
            CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest'],
        ]);
    }
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException($error ?: "La fuente respondió HTTP $status");
    }
    return ['body' => mb_convert_encoding($body, 'UTF-8', 'Windows-1252'), 'headers' => $headers];
}

function clean(string $value): string
{
    return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function parse_today(string $html): array
{
    $start = strpos($html, '<div class="qCnt">');
    if ($start === false) throw new RuntimeException('No se encontró la tabla actual');
    $section = substr($html, $start, 40000);
    preg_match('/Resultados del día\s+([^<]+)/iu', $section, $date);
    preg_match_all('/<tr[^>]*>\s*<th[^>]*>([^<]*)([\s\S]*?)(?=<tr[^>]*>\s*<th|<\/table>)/iu', $section, $rows, PREG_SET_ORDER);
    $results = [];
    foreach ($rows as $row) {
        $jurisdiction = clean($row[1]);
        if ($jurisdiction === '') continue;
        preg_match_all('/<td[^>]*>([\s\S]*?)(?=<td|<tr|<\/table>)/iu', $row[2], $cells);
        $values = [];
        foreach (array_slice($cells[1] ?? [], 0, 5) as $cell) {
            preg_match('/\b\d{4,5}\b/', clean($cell), $number);
            $values[] = isset($number[0]) ? substr($number[0], -4) : null;
        }
        $values = array_pad($values, 5, null);
        if ($values !== [null, null, null, null, null]) $results[] = compact('jurisdiction', 'values');
    }
    if ($results === []) throw new RuntimeException('Notitimba todavía no publicó resultados');
    return ['date_label' => isset($date[1]) ? clean($date[1]) : date('d/m/Y'), 'results' => $results];
}

function parse_history(string $html): array
{
    $draws = ['Uprev', 'Uprim', 'Umat', 'Uvesp', 'Unoct'];
    $dates = [];
    $days = [];
    foreach ($draws as $drawIndex => $draw) {
        $start = strpos($html, '<table id="' . $draw . '"');
        if ($start === false) continue;
        $end = strpos($html, '</table>', $start);
        $table = substr($html, $start, $end - $start);
        if ($dates === []) {
            preg_match_all('/class="thFch">([^<]+)<br>(\d{2})\/(\d{2})/iu', $table, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) $dates[] = clean($match[1]) . ' ' . $match[2] . '/' . $match[3];
        }
        preg_match_all('/<tr><th>(?!<)([^<]+)([\s\S]*?)(?=<tr><th>|$)/iu', $table, $rows, PREG_SET_ORDER);
        foreach ($rows as $row) {
            $province = clean($row[1]);
            preg_match_all('/<td[^>]*>([^<]*)(?=<td|<tr|<\/table>|$)/iu', $row[2], $cells);
            foreach ($dates as $dayIndex => $_) {
                preg_match('/\d{4,5}/', $cells[1][$dayIndex] ?? '', $number);
                $days[$dayIndex][$province] ??= array_fill(0, 5, null);
                $days[$dayIndex][$province][$drawIndex] = isset($number[0]) ? substr($number[0], -4) : null;
            }
        }
    }
    $history = [];
    foreach ($dates as $index => $label) {
        $results = [];
        foreach ($days[$index] ?? [] as $jurisdiction => $values) $results[] = compact('jurisdiction', 'values');
        $history[] = ['date_label' => $label, 'results' => $results];
    }
    if ($history === []) throw new RuntimeException('No se pudo leer el historial');
    return $history;
}

function save_json(string $path, array $data): void
{
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    $temporary = $path . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
        throw new RuntimeException("No se pudo escribir $path");
    }
}

