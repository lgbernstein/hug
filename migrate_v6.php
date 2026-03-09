<?php
// v6 Schema Migration — Adaptive Learning
// Run once to add new tables and columns. Safe to re-run.

$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }

$results = [];

// 1. Add tags column to hungarian_prep (comma-separated skill tags)
$col = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'tags'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE hungarian_prep ADD COLUMN tags TEXT DEFAULT NULL AFTER `who`");
    $results[] = "Added 'tags' column to hungarian_prep";
} else {
    $results[] = "'tags' column already exists";
}

// 2. Add drill_group column to hungarian_prep
$col = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'drill_group'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE hungarian_prep ADD COLUMN drill_group VARCHAR(100) DEFAULT NULL AFTER tags");
    $results[] = "Added 'drill_group' column to hungarian_prep";
} else {
    $results[] = "'drill_group' column already exists";
}

// 3. Create drill_groups table
$conn->query("CREATE TABLE IF NOT EXISTS drill_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    source VARCHAR(50) DEFAULT 'manual',
    notion_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$results[] = "drill_groups table ready";

// 4. Create skill_proficiency table
$conn->query("CREATE TABLE IF NOT EXISTS skill_proficiency (
    id INT AUTO_INCREMENT PRIMARY KEY,
    who VARCHAR(20) NOT NULL,
    skill VARCHAR(100) NOT NULL,
    pass_count INT DEFAULT 0,
    fail_count INT DEFAULT 0,
    level TINYINT DEFAULT 0,
    last_seen DATETIME,
    UNIQUE KEY unique_who_skill (who, skill)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$results[] = "skill_proficiency table ready";

// 5. Create grammar_patterns table (Notion import target)
$conn->query("CREATE TABLE IF NOT EXISTS grammar_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern VARCHAR(300) NOT NULL,
    suffix_words TEXT,
    explanation TEXT,
    part_of_speech VARCHAR(50),
    tags TEXT,
    notion_url TEXT,
    page_content TEXT,
    drill_group_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pattern (pattern)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$results[] = "grammar_patterns table ready";

// 6. Add skill_tags column to study_history for per-attempt skill tracking
$col = $conn->query("SHOW COLUMNS FROM study_history LIKE 'skill_tags'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE study_history ADD COLUMN skill_tags TEXT DEFAULT NULL");
    $results[] = "Added 'skill_tags' column to study_history";
} else {
    $results[] = "'skill_tags' column already exists in study_history";
}

echo "<h2>v6 Migration Results</h2><ul>";
foreach ($results as $r) echo "<li>$r</li>";
echo "</ul><p>Done. <a href='admin.php'>Back to Admin</a></p>";

$conn->close();
