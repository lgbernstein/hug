<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$phrase    = trim($_POST['phrase'] ?? '');
$pass      = ($_POST['pass'] ?? '0') === '1';
$who       = in_array($_POST['who'] ?? '', ['Maria','Larry','All']) ? $_POST['who'] : 'All';
$item_type = in_array($_POST['item_type'] ?? '', ['phrase','grammar','knowledge','flashcard']) ? $_POST['item_type'] : 'phrase';
$item_id   = isset($_POST['item_id']) && $_POST['item_id'] !== '' ? (int)$_POST['item_id'] : null;

if ($phrase === '' && $item_id === null) { echo json_encode(['ok' => false, 'reason' => 'empty']); exit; }

$env  = parse_ini_file(__DIR__ . '/.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { echo json_encode(['ok' => false, 'reason' => 'db']); exit; }

// Create/upgrade table
$conn->query("CREATE TABLE IF NOT EXISTS study_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    phrase      TEXT NOT NULL,
    who         VARCHAR(10) NOT NULL DEFAULT 'All',
    item_type   VARCHAR(20) NOT NULL DEFAULT 'phrase',
    item_id     INT DEFAULT NULL,
    pass_count  INT NOT NULL DEFAULT 0,
    fail_count  INT NOT NULL DEFAULT 0,
    consecutive_fails INT NOT NULL DEFAULT 0,
    recall_count INT NOT NULL DEFAULT 0,
    difficulty_mult FLOAT NOT NULL DEFAULT 1.0,
    is_leech    TINYINT(1) NOT NULL DEFAULT 0,
    last_seen   DATETIME,
    next_review DATETIME,
    skill_tags  TEXT DEFAULT NULL,
    INDEX idx_who (who),
    INDEX idx_review (next_review),
    INDEX idx_srs_due (who, item_type, next_review),
    INDEX idx_item_lookup (item_type, item_id, who)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Add new columns if missing (safe migration)
$conn->query("ALTER TABLE study_history ADD COLUMN IF NOT EXISTS consecutive_fails INT NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE study_history ADD COLUMN IF NOT EXISTS recall_count INT NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE study_history ADD COLUMN IF NOT EXISTS difficulty_mult FLOAT NOT NULL DEFAULT 1.0");
$conn->query("ALTER TABLE study_history ADD COLUMN IF NOT EXISTS is_leech TINYINT(1) NOT NULL DEFAULT 0");

// Look up existing row
$row = null;
if ($item_id !== null) {
    $stmt = $conn->prepare("SELECT id, pass_count, fail_count, consecutive_fails, recall_count, difficulty_mult FROM study_history WHERE item_type=? AND item_id=? AND who=? LIMIT 1");
    $stmt->bind_param('sis', $item_type, $item_id, $who);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}
if (!$row && $phrase !== '') {
    $p_safe = $conn->real_escape_string($phrase);
    $w_safe = $conn->real_escape_string($who);
    $res = $conn->query("SELECT id, pass_count, fail_count, consecutive_fails, recall_count, difficulty_mult FROM study_history WHERE phrase='$p_safe' AND who='$w_safe' LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
}

// Evidence-based SRS intervals (days): 0=same-day, then escalating
// Based on SM-2 + Pimsleur graduated intervals + Karpicke (2011) same-day re-review
$intervals = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30, 6 => 60, 7 => 90];

$prev_pass    = (int)($row['pass_count'] ?? 0);
$prev_fail    = (int)($row['fail_count'] ?? 0);
$prev_consec  = (int)($row['consecutive_fails'] ?? 0);
$prev_recall  = (int)($row['recall_count'] ?? 0);
$prev_diff    = (float)($row['difficulty_mult'] ?? 1.0);
$is_leech     = 0;

if ($pass) {
    $pass_count      = $prev_pass + 1;
    $fail_count      = $prev_fail;
    $consecutive_fails = 0;
    $recall_count    = $prev_recall + 1;
    $difficulty_mult = $prev_diff;

    // First pass: schedule same-day re-review (4-8 hrs from now)
    if ($pass_count === 1) {
        $next_review = date('Y-m-d H:i:s', strtotime('+5 hours'));
        $days = 0;
    } else {
        $base_days = $intervals[min($pass_count, 7)] ?? 90;
        $days = max(1, round($base_days * $difficulty_mult));
        $next_review = date('Y-m-d H:i:s', strtotime("+$days days"));
    }

    // Easy item: 3+ consecutive passes → increase multiplier (learn faster)
    if ($pass_count >= 3 && $difficulty_mult < 2.5) {
        $difficulty_mult = min(2.5, $difficulty_mult * 1.15);
    }

    // Clear leech flag after 2 consecutive passes
    $is_leech = ($prev_consec >= 4 && $pass_count < 2) ? 1 : 0;
} else {
    $pass_count      = 0;
    $fail_count      = $prev_fail + 1;
    $consecutive_fails = $prev_consec + 1;
    $recall_count    = $prev_recall;
    $difficulty_mult = max(0.5, $prev_diff * 0.8);

    // Leech detection: 4+ consecutive fails
    $is_leech = ($consecutive_fails >= 4) ? 1 : 0;

    // Failed: review again in 4 hours (same day) or tomorrow
    $hours_left = 23 - (int)date('G');
    if ($hours_left >= 4) {
        $next_review = date('Y-m-d H:i:s', strtotime('+4 hours'));
        $days = 0;
    } else {
        $next_review = date('Y-m-d H:i:s', strtotime('+1 day'));
        $days = 1;
    }
}

if ($row) {
    $stmt = $conn->prepare("UPDATE study_history SET pass_count=?, fail_count=?, consecutive_fails=?, recall_count=?, difficulty_mult=?, is_leech=?, last_seen=NOW(), next_review=?, item_type=?, item_id=? WHERE id=?");
    $stmt->bind_param('iiiiidissii', $pass_count, $fail_count, $consecutive_fails, $recall_count, $difficulty_mult, $is_leech, $next_review, $item_type, $item_id, $row['id']);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO study_history (phrase, who, item_type, item_id, pass_count, fail_count, consecutive_fails, recall_count, difficulty_mult, is_leech, last_seen, next_review) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param('sssiiiiiids', $phrase, $who, $item_type, $item_id, $pass_count, $fail_count, $consecutive_fails, $recall_count, $difficulty_mult, $is_leech, $next_review);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
echo json_encode([
    'ok' => true, 'days' => $days, 'next_review' => $next_review,
    'item_type' => $item_type, 'pass_count' => $pass_count,
    'recall_count' => $recall_count, 'is_leech' => $is_leech,
    'difficulty_mult' => round($difficulty_mult, 2)
]);
