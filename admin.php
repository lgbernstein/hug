<?php
// Simple auth — change this password
$ADMIN_PASS = 'hug2026';
session_start();
if (isset($_POST['admin_pass'])) {
    if ($_POST['admin_pass'] === $ADMIN_PASS) { $_SESSION['hug_admin'] = true; }
    else { $_SESSION['hug_admin_error'] = 'Wrong password'; }
}
if (isset($_GET['logout'])) { unset($_SESSION['hug_admin']); }
if (empty($_SESSION['hug_admin'])) {
    $err = $_SESSION['hug_admin_error'] ?? '';
    unset($_SESSION['hug_admin_error']);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login</title><script src="https://cdn.tailwindcss.com"></script></head>';
    echo '<body class="min-h-screen flex items-center justify-center" style="background:#060b18;color:#e2e8f0">';
    echo '<form method="POST" class="p-8 rounded-2xl text-center space-y-4" style="background:#111a2e;border:1px solid rgba(255,255,255,0.05)">';
    echo '<h1 class="text-lg font-bold">HUG COACH Admin</h1>';
    if ($err) echo '<p class="text-red-400 text-sm">' . htmlspecialchars($err) . '</p>';
    echo '<input type="password" name="admin_pass" placeholder="Password" autofocus class="w-full bg-black/30 border border-white/10 rounded-lg px-4 py-2 text-white text-center focus:outline-none focus:border-indigo-500">';
    echo '<button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 py-2 rounded-lg text-sm font-semibold">Login</button>';
    echo '</form></body></html>';
    exit;
}

$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed'); }

// Ensure answer_hu column exists (compatible with MySQL 5.7)
$colCheck = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'answer_hu'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE hungarian_prep ADD COLUMN answer_hu TEXT DEFAULT NULL AFTER answer_en");
}

$message = '';
$preview = [];

// Handle import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'preview') {
        $raw = trim($_POST['tsv'] ?? '');
        $format = $_POST['format'] ?? '3col';
        $defaultCat = trim($_POST['default_cat'] ?? 'General');
        if ($raw) {
            $lines = array_filter(explode("\n", $raw), 'trim');
            foreach ($lines as $line) {
                $cols = explode("\t", $line);
                $cols = array_map('trim', $cols);
                if (count($cols) < 2) continue;
                $row = ['question_hu' => $cols[0], 'answer_en' => '', 'answer_hu' => '', 'category' => $defaultCat];
                if ($format === '2col') {
                    $row['answer_en'] = $cols[1];
                } elseif ($format === '3col') {
                    $row['answer_hu'] = $cols[1] ?? '';
                    $row['answer_en'] = $cols[2] ?? '';
                } elseif ($format === '4col') {
                    $row['answer_hu'] = $cols[1] ?? '';
                    $row['answer_en'] = $cols[2] ?? '';
                    $row['category']  = $cols[3] ?? $defaultCat;
                }
                $preview[] = $row;
            }
        }
    }

    if ($_POST['action'] === 'import') {
        $rows = json_decode($_POST['rows_json'], true);
        $imported = 0;
        $skipped = 0;
        if ($rows) {
            $stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_hu, answer_en, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_hu=VALUES(answer_hu), answer_en=VALUES(answer_en), category=VALUES(category)");
            foreach ($rows as $r) {
                $q = trim($r['question_hu'] ?? '');
                if (!$q) { $skipped++; continue; }
                $ah = trim($r['answer_hu'] ?? '') ?: null;
                $ae = trim($r['answer_en'] ?? '');
                $cat = trim($r['category'] ?? 'General');
                $stmt->bind_param('ssss', $q, $ah, $ae, $cat);
                if ($stmt->execute()) { $imported++; } else { $skipped++; }
            }
            $stmt->close();
        }
        $message = "Imported $imported phrases" . ($skipped ? ", skipped $skipped" : '') . '.';
    }

    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM hungarian_prep WHERE id = $id");
        $message = "Deleted phrase #$id.";
    }

    if ($_POST['action'] === 'update_answer' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $answer_hu = $conn->real_escape_string(trim($_POST['answer_hu'] ?? ''));
        $conn->query("UPDATE hungarian_prep SET answer_hu = " . ($answer_hu ? "'$answer_hu'" : "NULL") . " WHERE id = $id");
        $message = "Updated Hungarian answer for phrase #$id.";
    }
}

// Fetch all phrases for management
$search = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$catFilter = $conn->real_escape_string(trim($_GET['cat'] ?? ''));
$sql = "SELECT * FROM hungarian_prep WHERE 1=1";
if ($search) $sql .= " AND (question_hu LIKE '%$search%' OR answer_en LIKE '%$search%' OR answer_hu LIKE '%$search%')";
if ($catFilter) $sql .= " AND category = '$catFilter'";
$sql .= " ORDER BY category, question_hu";
$phrases = $conn->query($sql);

// Get categories for filter
$cats = $conn->query("SELECT DISTINCT category FROM hungarian_prep ORDER BY category");
$catList = [];
if ($cats) { while ($c = $cats->fetch_assoc()) $catList[] = $c['category']; }

$totalCount = $conn->query("SELECT COUNT(*) AS c FROM hungarian_prep")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HUG COACH Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { font-family: 'Inter', system-ui, sans-serif; }
body { background: #060b18; color: #e2e8f0; }
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
</style>
</head>
<body class="min-h-screen p-4 md:p-8">
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-xl">&#x1f1ed;&#x1f1fa;</span>
            <div>
                <h1 class="text-lg font-bold text-white">HUG COACH Admin</h1>
                <p class="text-xs text-slate-500"><?php echo $totalCount; ?> phrases in database</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="?logout=1" class="text-xs text-slate-500 hover:text-red-400 font-semibold">Logout</a>
            <a href="index.php" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">&larr; Back to App</a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-500/10 border border-green-500/20 rounded-xl px-4 py-3 text-sm text-green-400"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- IMPORT SECTION -->
    <div class="bg-[#111a2e] border border-white/5 rounded-2xl p-6">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Import Phrases</h2>
        <p class="text-xs text-slate-500 mb-4">Paste tab-separated data from Google Sheets. Select format to match your columns.</p>

        <form method="POST">
            <input type="hidden" name="action" value="preview">
            <div class="flex flex-wrap gap-3 mb-3">
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                    <input type="radio" name="format" value="2col" class="accent-indigo-500"> 2-col: Hungarian | English
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                    <input type="radio" name="format" value="3col" checked class="accent-indigo-500"> 3-col: Hungarian | Answer HU | English
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                    <input type="radio" name="format" value="4col" class="accent-indigo-500"> 4-col: Hungarian | Answer HU | English | Category
                </label>
            </div>
            <div class="flex gap-3 mb-3">
                <div class="flex-1">
                    <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Default Category</label>
                    <input type="text" name="default_cat" value="General" class="w-full mt-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>
            <textarea name="tsv" rows="8" placeholder="Paste TSV here — one phrase per line, tabs between columns&#10;&#10;Example (3-col):&#10;Hogy hívják?&#9;A nevem Bernstein Lawrence.&#9;What is your name?&#10;Hol lakik?&#9;Budapesten lakom.&#9;Where do you live?"
                class="w-full bg-[#0c1222] border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-mono focus:outline-none focus:border-indigo-500 resize-y mb-3"><?php echo htmlspecialchars($_POST['tsv'] ?? ''); ?></textarea>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 px-5 py-2 rounded-lg text-sm font-semibold transition-all">Preview Import</button>
        </form>

        <?php if ($preview): ?>
        <div class="mt-4 border-t border-white/5 pt-4">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Preview (<?php echo count($preview); ?> rows)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-slate-500 uppercase tracking-wider">
                        <th class="text-left py-2 px-2">Question (HU)</th>
                        <th class="text-left py-2 px-2">Answer (HU)</th>
                        <th class="text-left py-2 px-2">English</th>
                        <th class="text-left py-2 px-2">Category</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($preview as $p): ?>
                    <tr class="border-t border-white/5">
                        <td class="py-2 px-2 text-white"><?php echo htmlspecialchars($p['question_hu']); ?></td>
                        <td class="py-2 px-2 text-green-400"><?php echo htmlspecialchars($p['answer_hu']); ?></td>
                        <td class="py-2 px-2 text-slate-400"><?php echo htmlspecialchars($p['answer_en']); ?></td>
                        <td class="py-2 px-2 text-slate-500"><?php echo htmlspecialchars($p['category']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="POST" class="mt-3">
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="rows_json" value="<?php echo htmlspecialchars(json_encode($preview)); ?>">
                <button type="submit" class="bg-green-600 hover:bg-green-500 px-5 py-2 rounded-lg text-sm font-semibold transition-all">Confirm Import (<?php echo count($preview); ?> phrases)</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- MANAGE SECTION -->
    <div class="bg-[#111a2e] border border-white/5 rounded-2xl p-6">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Manage Phrases</h2>

        <!-- Search + Filter -->
        <form method="GET" class="flex flex-wrap gap-2 mb-4">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search..."
                class="flex-1 min-w-[200px] bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
            <select name="cat" class="bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                <option value="">All Categories</option>
                <?php foreach ($catList as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $catFilter === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm font-semibold transition-all">Filter</button>
        </form>

        <!-- Phrases Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-slate-500 uppercase tracking-wider text-[10px]">
                    <th class="text-left py-2 px-2 w-8">#</th>
                    <th class="text-left py-2 px-2">Question (HU)</th>
                    <th class="text-left py-2 px-2">Answer (HU)</th>
                    <th class="text-left py-2 px-2">English</th>
                    <th class="text-left py-2 px-2">Category</th>
                    <th class="text-left py-2 px-2 w-20">Actions</th>
                </tr></thead>
                <tbody>
                <?php if ($phrases): while ($p = $phrases->fetch_assoc()): ?>
                <tr class="border-t border-white/5 hover:bg-white/[0.02]" id="row-<?php echo $p['id']; ?>">
                    <td class="py-2 px-2 text-slate-600"><?php echo $p['id']; ?></td>
                    <td class="py-2 px-2 text-white font-medium"><?php echo htmlspecialchars($p['question_hu']); ?></td>
                    <td class="py-2 px-2">
                        <form method="POST" class="flex gap-1 items-center">
                            <input type="hidden" name="action" value="update_answer">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <input type="text" name="answer_hu" value="<?php echo htmlspecialchars($p['answer_hu'] ?? ''); ?>"
                                placeholder="Add Hungarian answer..."
                                class="bg-transparent border border-white/10 rounded px-2 py-1 text-green-400 text-xs w-full focus:outline-none focus:border-indigo-500 <?php echo $p['answer_hu'] ? '' : 'border-dashed border-yellow-500/30'; ?>">
                            <button type="submit" class="text-slate-500 hover:text-green-400 transition-colors shrink-0" title="Save">&#10003;</button>
                        </form>
                    </td>
                    <td class="py-2 px-2 text-slate-400"><?php echo htmlspecialchars($p['answer_en']); ?></td>
                    <td class="py-2 px-2 text-slate-500"><?php echo htmlspecialchars($p['category']); ?></td>
                    <td class="py-2 px-2">
                        <form method="POST" onsubmit="return confirm('Delete this phrase?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="text-slate-600 hover:text-red-400 transition-colors text-xs">delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
