<?php
// Google Sheets CSV Import — fetches published sheet as CSV, maps columns, imports into hungarian_prep
header('Content-Type: application/json');

$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { echo json_encode(['error' => 'DB connection failed']); exit; }

$action = $_GET['action'] ?? '';

// Normalize any Google Sheets URL to a CSV export URL
function normalizeGSheetsUrl($url) {
    if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $url, $m)) {
        $sheetId = $m[1];
        $gid = '0';
        if (preg_match('/gid=(\d+)/', $url, $gm)) $gid = $gm[1];
        return "https://docs.google.com/spreadsheets/d/$sheetId/export?format=csv&gid=$gid";
    }
    return $url;
}

function fetchCsv($url) {
    $csvUrl = normalizeGSheetsUrl($url);
    $ch = curl_init($csvUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($httpCode !== 200 || !$resp) {
        return ['error' => 'Failed to fetch sheet: ' . ($err ?: "HTTP $httpCode")];
    }
    return ['csv' => $resp];
}

function parseCsv($csvText) {
    $rows = [];
    $lines = str_getcsv($csvText, "\n");
    foreach ($lines as $line) {
        $rows[] = str_getcsv($line);
    }
    return $rows;
}

if ($action === 'fetch') {
    $url = trim($_POST['url'] ?? '');
    if (!$url) { echo json_encode(['error' => 'No URL provided']); exit; }

    $result = fetchCsv($url);
    if (isset($result['error'])) { echo json_encode($result); exit; }

    $rows = parseCsv($result['csv']);
    if (count($rows) < 2) { echo json_encode(['error' => 'Sheet appears empty or has only headers']); exit; }

    $headers = $rows[0];
    $preview = array_slice($rows, 1, 5);
    echo json_encode([
        'headers' => $headers,
        'preview' => $preview,
        'total_rows' => count($rows) - 1
    ]);
    exit;
}

if ($action === 'import') {
    $url = trim($_POST['url'] ?? '');
    if (!$url) { echo json_encode(['error' => 'No URL provided']); exit; }

    $colQ  = (int)($_POST['col_question_hu'] ?? -1);
    $colAH = (int)($_POST['col_answer_hu'] ?? -1);
    $colAE = (int)($_POST['col_answer_en'] ?? -1);
    $colCat = (int)($_POST['col_category'] ?? -1);
    $who = $_POST['who'] ?? 'All';
    $who = in_array($who, ['Maria', 'Larry', 'All']) ? $who : 'All';

    if ($colQ < 0) { echo json_encode(['error' => 'Must map question_hu column']); exit; }

    $result = fetchCsv($url);
    if (isset($result['error'])) { echo json_encode($result); exit; }

    $rows = parseCsv($result['csv']);
    if (count($rows) < 2) { echo json_encode(['error' => 'No data rows found']); exit; }

    $batch = 'sheets_' . date('Y-m-d_His');
    $stmt = $conn->prepare("INSERT IGNORE INTO hungarian_prep (question_hu, answer_hu, answer_en, category, `who`, import_batch) VALUES (?, ?, ?, ?, ?, ?)");

    $imported = 0;
    $skipped = 0;
    $duplicates = 0;

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $qHu = isset($row[$colQ]) ? trim($row[$colQ]) : '';
        if (!$qHu) { $skipped++; continue; }

        $aHu = ($colAH >= 0 && isset($row[$colAH])) ? trim($row[$colAH]) : '';
        $aEn = ($colAE >= 0 && isset($row[$colAE])) ? trim($row[$colAE]) : '';
        $cat = ($colCat >= 0 && isset($row[$colCat])) ? trim($row[$colCat]) : 'Imported';

        $stmt->bind_param('ssssss', $qHu, $aHu, $aEn, $cat, $who, $batch);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $imported++;
        } else {
            $duplicates++;
        }
    }
    $stmt->close();

    echo json_encode([
        'imported' => $imported,
        'skipped' => $skipped,
        'duplicates' => $duplicates,
        'batch' => $batch
    ]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
