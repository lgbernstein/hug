<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$phrase = trim($_POST['phrase'] ?? '');
$pass   = ($_POST['pass'] ?? '0') === '1';
$who    = in_array($_POST['who'] ?? '', ['Maria','Larry','All']) ? $_POST['who'] : 'All';

if ($phrase === '') { echo json_encode(['ok' => false, 'reason' => 'empty phrase']); exit; }

$env  = parse_ini_file(__DIR__ . '/.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { echo json_encode(['ok' => false, 'reason' => 'db']); exit; }

// Create table if it doesn't exist yet
$conn->query("CREATE TABLE IF NOT EXISTS study_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    phrase      TEXT NOT NULL,
    who         VARCHAR(10) NOT NULL DEFAULT 'All',
    pass_count  INT NOT NULL DEFAULT 0,
    fail_count  INT NOT NULL DEFAULT 0,
    last_seen   DATETIME,
    next_review DATETIME,
    INDEX idx_who (who),
    INDEX idx_review (next_review)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$p_safe = $conn->real_escape_string($phrase);
$w_safe = $conn->real_escape_string($who);

// Fetch existing row
$res = $conn->query("SELECT id, pass_count, fail_count FROM study_history WHERE phrase='$p_safe' AND who='$w_safe' LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;

// Calculate next review interval (simplified SRS)
if ($pass) {
    $pass_count = ($row['pass_count'] ?? 0) + 1;
    $fail_count = $row['fail_count'] ?? 0;
    // Interval doubles with consecutive passes, capped at 21 days
    $days_map = [1 => 3, 2 => 7, 3 => 14, 4 => 21];
    $days = $days_map[min($pass_count, 4)];
} else {
    $pass_count = 0;   // reset streak on fail
    $fail_count = ($row['fail_count'] ?? 0) + 1;
    $days = 1;
}

$next_review = date('Y-m-d H:i:s', strtotime("+$days days"));

if ($row) {
    $conn->query("UPDATE study_history
                  SET pass_count=$pass_count, fail_count=$fail_count,
                      last_seen=NOW(), next_review='$next_review'
                  WHERE id={$row['id']}");
} else {
    $conn->query("INSERT INTO study_history (phrase, who, pass_count, fail_count, last_seen, next_review)
                  VALUES ('$p_safe', '$w_safe', $pass_count, $fail_count, NOW(), '$next_review')");
}

$conn->close();
echo json_encode(['ok' => true, 'days' => $days, 'next_review' => $next_review]);
