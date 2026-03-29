<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$phrase    = trim($_POST['phrase'] ?? '');
$pass      = ($_POST['pass'] ?? '0') === '1';
$who       = in_array($_POST['who'] ?? '', ['Maria','Larry','All']) ? $_POST['who'] : 'All';
$item_type = in_array($_POST['item_type'] ?? '', ['phrase','grammar','knowledge']) ? $_POST['item_type'] : 'phrase';
$item_id   = isset($_POST['item_id']) && $_POST['item_id'] !== '' ? (int)$_POST['item_id'] : null;

if ($phrase === '' && $item_id === null) { echo json_encode(['ok' => false, 'reason' => 'empty']); exit; }

$env  = parse_ini_file(__DIR__ . '/.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { echo json_encode(['ok' => false, 'reason' => 'db']); exit; }

// Create table if it doesn't exist yet
$conn->query("CREATE TABLE IF NOT EXISTS study_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    phrase      TEXT NOT NULL,
    who         VARCHAR(10) NOT NULL DEFAULT 'All',
    item_type   ENUM('phrase','grammar','knowledge') NOT NULL DEFAULT 'phrase',
    item_id     INT DEFAULT NULL,
    pass_count  INT NOT NULL DEFAULT 0,
    fail_count  INT NOT NULL DEFAULT 0,
    last_seen   DATETIME,
    next_review DATETIME,
    skill_tags  TEXT DEFAULT NULL,
    INDEX idx_who (who),
    INDEX idx_review (next_review),
    INDEX idx_srs_due (who, item_type, next_review),
    INDEX idx_item_lookup (item_type, item_id, who)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Look up existing row — prefer item_type+item_id if available, fall back to phrase text
$row = null;
if ($item_id !== null) {
    $stmt = $conn->prepare("SELECT id, pass_count, fail_count FROM study_history WHERE item_type=? AND item_id=? AND who=? LIMIT 1");
    $stmt->bind_param('sis', $item_type, $item_id, $who);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}
if (!$row && $phrase !== '') {
    $p_safe = $conn->real_escape_string($phrase);
    $w_safe = $conn->real_escape_string($who);
    $res = $conn->query("SELECT id, pass_count, fail_count FROM study_history WHERE phrase='$p_safe' AND who='$w_safe' LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
}

// Type-specific SRS intervals
$intervals = [
    'phrase'    => [1 => 3, 2 => 7, 3 => 14, 4 => 21],
    'grammar'   => [1 => 2, 2 => 5, 3 => 10, 4 => 21],
    'knowledge' => [1 => 3, 2 => 7, 3 => 14, 4 => 30],
];
$days_map = $intervals[$item_type] ?? $intervals['phrase'];

if ($pass) {
    $pass_count = ($row['pass_count'] ?? 0) + 1;
    $fail_count = $row['fail_count'] ?? 0;
    $days = $days_map[min($pass_count, 4)];
} else {
    $pass_count = 0;
    $fail_count = ($row['fail_count'] ?? 0) + 1;
    $days = 1;
}

$next_review = date('Y-m-d H:i:s', strtotime("+$days days"));

if ($row) {
    $stmt = $conn->prepare("UPDATE study_history SET pass_count=?, fail_count=?, last_seen=NOW(), next_review=?, item_type=?, item_id=? WHERE id=?");
    $stmt->bind_param('iissii', $pass_count, $fail_count, $next_review, $item_type, $item_id, $row['id']);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO study_history (phrase, who, item_type, item_id, pass_count, fail_count, last_seen, next_review) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param('sssiiss', $phrase, $who, $item_type, $item_id, $pass_count, $fail_count, $next_review);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
echo json_encode(['ok' => true, 'days' => $days, 'next_review' => $next_review, 'item_type' => $item_type]);
