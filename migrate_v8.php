<?php
// v8 Schema Migration — Study Command Center
// Run once. Safe to re-run.

$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }

$results = [];

// 1. Add item_type column to study_history
$col = $conn->query("SHOW COLUMNS FROM study_history LIKE 'item_type'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE study_history ADD COLUMN item_type ENUM('phrase','grammar','knowledge') NOT NULL DEFAULT 'phrase' AFTER who");
    $results[] = "Added 'item_type' column to study_history";
} else {
    $results[] = "'item_type' column already exists";
}

// 2. Add item_id column to study_history
$col = $conn->query("SHOW COLUMNS FROM study_history LIKE 'item_id'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE study_history ADD COLUMN item_id INT DEFAULT NULL AFTER item_type");
    $results[] = "Added 'item_id' column to study_history";
} else {
    $results[] = "'item_id' column already exists";
}

// 3. Add index for SRS due-item queries
$idx = $conn->query("SHOW INDEX FROM study_history WHERE Key_name = 'idx_srs_due'");
if ($idx && $idx->num_rows === 0) {
    $conn->query("ALTER TABLE study_history ADD INDEX idx_srs_due (who, item_type, next_review)");
    $results[] = "Added idx_srs_due index";
} else {
    $results[] = "idx_srs_due index already exists";
}

// 4. Add index for item lookup
$idx = $conn->query("SHOW INDEX FROM study_history WHERE Key_name = 'idx_item_lookup'");
if ($idx && $idx->num_rows === 0) {
    $conn->query("ALTER TABLE study_history ADD INDEX idx_item_lookup (item_type, item_id, who)");
    $results[] = "Added idx_item_lookup index";
} else {
    $results[] = "idx_item_lookup index already exists";
}

// 5. Create study_log table
$conn->query("CREATE TABLE IF NOT EXISTS study_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    who VARCHAR(20) NOT NULL,
    block_type VARCHAR(50) NOT NULL,
    block_title VARCHAR(200),
    duration_min INT DEFAULT 0,
    items_completed INT DEFAULT 0,
    items_passed INT DEFAULT 0,
    started_at DATETIME,
    completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    INDEX idx_who_date (who, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$results[] = "study_log table ready";

echo "<h2>v8 Migration Results</h2><ul>";
foreach ($results as $r) echo "<li>" . htmlspecialchars($r) . "</li>";
echo "</ul><p>Done. <a href='index.php'>Back to App</a></p>";

$conn->close();
