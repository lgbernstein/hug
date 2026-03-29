<?php
// v7 Schema Migration — 5-Tab Reorganization
// Run once to add new tables and columns. Safe to re-run.

$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }

$results = [];

// 1. Add import_batch column to hungarian_prep (tracks Google Sheets imports)
$col = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'import_batch'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE hungarian_prep ADD COLUMN import_batch VARCHAR(100) DEFAULT NULL AFTER drill_group");
    $results[] = "Added 'import_batch' column to hungarian_prep";
} else {
    $results[] = "'import_batch' column already exists";
}

// 2. Create knowledge_cards table
$conn->query("CREATE TABLE IF NOT EXISTS knowledge_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('history','geography','family','culture') NOT NULL,
    title_hu VARCHAR(500) NOT NULL,
    title_en VARCHAR(500),
    content_hu TEXT,
    content_en TEXT,
    key_fact TEXT,
    tags TEXT,
    who VARCHAR(10) DEFAULT 'All',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_title (title_hu(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$results[] = "knowledge_cards table ready";

// 3. Create learning_resources table
$conn->query("CREATE TABLE IF NOT EXISTS learning_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    url TEXT NOT NULL,
    icon VARCHAR(20) DEFAULT '🔗',
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$results[] = "learning_resources table ready";

// 4. Seed learning resources (skip if already populated)
$cnt = $conn->query("SELECT COUNT(*) AS c FROM learning_resources")->fetch_assoc()['c'];
if ((int)$cnt === 0) {
    $resources = [
        ['Listening', 'Pimsleur', 'https://www.pimsleur.com/learn-hungarian', '🎧', 1],
        ['Listening', 'HungarianPod101', 'https://www.hungarianpod101.com/', '🎙️', 2],
        ['Listening', 'YouTube', 'https://www.youtube.com/results?search_query=learn+hungarian', '▶️', 3],
        ['Vocabulary', 'Drops', 'https://languagedrops.com/', '💧', 4],
        ['Vocabulary', 'Quizlet', 'https://quizlet.com/', '📇', 5],
        ['Vocabulary', 'Duolingo', 'https://www.duolingo.com/', '🦉', 6],
        ['Grammar & Textbook', 'Aktív MagyarOK', 'https://magyar-ok.hu/', '📖', 7],
        ['My Materials', 'Google Sheets', 'https://docs.google.com/spreadsheets/d/1V7ubIGvU_SCWC5z3tWzkf1c1JR02joFJqqhb1x2-bFk/edit?gid=50814931#gid=50814931', '📊', 8],
        ['My Materials', 'Notion Hub', 'https://www.notion.so/lbernstein/Learning-Hungarian-2368fa7eab9780b99fe2fc824d3efa53', '📝', 9],
        ['My Materials', 'Google Drive', 'https://drive.google.com/', '📁', 10],
    ];
    $stmt = $conn->prepare("INSERT INTO learning_resources (category, name, url, icon, sort_order) VALUES (?, ?, ?, ?, ?)");
    foreach ($resources as $r) {
        $stmt->bind_param('ssssi', $r[0], $r[1], $r[2], $r[3], $r[4]);
        $stmt->execute();
    }
    $stmt->close();
    $results[] = "Seeded " . count($resources) . " learning resources";
} else {
    $results[] = "Learning resources already seeded ($cnt rows)";
}

// 5. Seed knowledge cards (skip if already populated)
$cnt = $conn->query("SELECT COUNT(*) AS c FROM knowledge_cards")->fetch_assoc()['c'];
if ((int)$cnt === 0) {
    $cards = [
        // History
        ['history', 'A honfoglalás', 'The Hungarian Conquest', 'A magyarok 895-ben érkeztek a Kárpát-medencébe Árpád vezetésével.', 'The Hungarians arrived in the Carpathian Basin in 895, led by Árpád.', '895', 'history,founding'],
        ['history', 'Szent István megkoronázása', 'Coronation of Saint Stephen', 'István királyt 1000-ben (vagy 1001-ben) koronázták meg. Ő alapította a Magyar Királyságot.', 'King Stephen was crowned in 1000 (or 1001). He founded the Kingdom of Hungary.', '1000/1001', 'history,founding'],
        ['history', 'A tatárjárás', 'The Mongol Invasion', 'A tatárok 1241-42-ben pusztították Magyarországot. IV. Béla király újjáépítette az országot.', 'The Mongols devastated Hungary in 1241-42. King Béla IV rebuilt the country.', '1241-1242', 'history,wars'],
        ['history', 'Mohácsi csata', 'Battle of Mohács', 'A mohácsi csata 1526-ban volt. A magyar sereg vereséget szenvedett az Oszmán Birodalomtól.', 'The Battle of Mohács was in 1526. The Hungarian army was defeated by the Ottoman Empire.', '1526', 'history,wars'],
        ['history', 'Az 1848-as forradalom', 'The Revolution of 1848', 'Az 1848-as forradalom március 15-én kezdődött. Petőfi Sándor olvasta fel a Nemzeti dalt.', 'The 1848 revolution began on March 15. Sándor Petőfi recited the National Song.', '1848. március 15.', 'history,revolution'],
        ['history', 'A kiegyezés', 'The Austro-Hungarian Compromise', 'A kiegyezés 1867-ben volt. Létrejött az Osztrák-Magyar Monarchia.', 'The Compromise was in 1867. The Austro-Hungarian Monarchy was established.', '1867', 'history,politics'],
        ['history', 'A trianoni békeszerződés', 'Treaty of Trianon', 'A trianoni békeszerződést 1920-ban írták alá. Magyarország elvesztette területének kétharmadát.', 'The Treaty of Trianon was signed in 1920. Hungary lost two-thirds of its territory.', '1920', 'history,treaties'],
        ['history', 'Az 1956-os forradalom', 'The 1956 Revolution', 'Az 1956-os forradalom október 23-án kezdődött. A magyarok a szovjet megszállás ellen harcoltak.', 'The 1956 revolution began on October 23. Hungarians fought against Soviet occupation.', '1956. október 23.', 'history,revolution'],
        ['history', 'A rendszerváltás', 'The Change of Regime', 'A rendszerváltás 1989-90-ben történt. Magyarország demokratikus köztársaság lett.', 'The change of regime happened in 1989-90. Hungary became a democratic republic.', '1989-1990', 'history,politics'],
        ['history', 'Magyarország EU-csatlakozása', 'Hungary Joins the EU', 'Magyarország 2004. május 1-jén csatlakozott az Európai Unióhoz.', 'Hungary joined the European Union on May 1, 2004.', '2004', 'history,politics'],

        // Geography
        ['geography', 'Magyarország szomszédai', 'Hungary\'s Neighbors', 'Magyarországnak 7 szomszédja van: Ausztria, Szlovákia, Ukrajna, Románia, Szerbia, Horvátország, Szlovénia.', 'Hungary has 7 neighbors: Austria, Slovakia, Ukraine, Romania, Serbia, Croatia, Slovenia.', '7 szomszéd', 'geography,borders'],
        ['geography', 'Budapest', 'Budapest', 'Budapest Magyarország fővárosa. A Duna folyó osztja két részre: Buda és Pest.', 'Budapest is Hungary\'s capital. The Danube River divides it into two parts: Buda and Pest.', 'Főváros', 'geography,cities'],
        ['geography', 'A Duna', 'The Danube', 'A Duna Magyarország leghosszabb folyója. Észak-déli irányban szeli át az országot.', 'The Danube is Hungary\'s longest river. It crosses the country from north to south.', 'Leghosszabb folyó', 'geography,rivers'],
        ['geography', 'A Tisza', 'The Tisza', 'A Tisza a második legnagyobb folyó Magyarországon. Keleten folyik keresztül az országon.', 'The Tisza is the second largest river in Hungary. It flows through the eastern part of the country.', 'Második legnagyobb', 'geography,rivers'],
        ['geography', 'A Balaton', 'Lake Balaton', 'A Balaton Közép-Európa legnagyobb tava. A „Magyar Tenger" néven is ismert.', 'Lake Balaton is the largest lake in Central Europe. It\'s also known as the "Hungarian Sea".', 'Legnagyobb tó', 'geography,lakes'],
        ['geography', 'A Hortobágy', 'The Hortobágy', 'A Hortobágy Magyarország legnagyobb pusztája és nemzeti parkja. UNESCO Világörökségi helyszín.', 'The Hortobágy is Hungary\'s largest steppe and national park. It\'s a UNESCO World Heritage Site.', 'Nemzeti Park', 'geography,nature'],
        ['geography', 'Magyar nagyvárosok', 'Major Hungarian Cities', 'A legnagyobb városok: Budapest, Debrecen, Szeged, Miskolc, Pécs, Győr.', 'The largest cities: Budapest, Debrecen, Szeged, Miskolc, Pécs, Győr.', '6 nagyváros', 'geography,cities'],
        ['geography', 'Magyarország területe', 'Hungary\'s Area', 'Magyarország területe 93 030 km². Lakossága körülbelül 10 millió fő.', 'Hungary\'s area is 93,030 km². Its population is approximately 10 million.', '93 030 km²', 'geography,facts'],

        // Culture
        ['culture', 'A Himnusz', 'The National Anthem', 'A magyar Himnusz szövegét Kölcsey Ferenc írta 1823-ban. A zenéjét Erkel Ferenc szerezte.', 'The lyrics of the Hungarian National Anthem were written by Ferenc Kölcsey in 1823. The music was composed by Ferenc Erkel.', 'Kölcsey Ferenc, 1823', 'culture,symbols'],
        ['culture', 'A Szózat', 'The Appeal', 'A Szózat a második legfontosabb nemzeti vers. Vörösmarty Mihály írta 1836-ban.', 'The Szózat is the second most important national poem. Written by Mihály Vörösmarty in 1836.', 'Vörösmarty, 1836', 'culture,symbols'],
        ['culture', 'A magyar zászló', 'The Hungarian Flag', 'A magyar zászló három színű: piros, fehér és zöld. Vízszintes csíkok.', 'The Hungarian flag has three colors: red, white and green. Horizontal stripes.', 'Piros, fehér, zöld', 'culture,symbols'],
        ['culture', 'A magyar címer', 'The Hungarian Coat of Arms', 'A magyar címer bal oldalán ezüst sávok, jobb oldalán kettős kereszt látható egy hármas halmon.', 'The left side of the Hungarian coat of arms shows silver stripes, the right side a double cross on three hills.', 'Kettős kereszt', 'culture,symbols'],
        ['culture', 'Nemzeti ünnepek', 'National Holidays', 'Három nemzeti ünnep: március 15. (1848-as forradalom), augusztus 20. (Szent István), október 23. (1956-os forradalom).', 'Three national holidays: March 15 (1848 revolution), August 20 (Saint Stephen), October 23 (1956 revolution).', 'Márc. 15, Aug. 20, Okt. 23', 'culture,holidays'],
        ['culture', 'Híres magyar feltalálók', 'Famous Hungarian Inventors', 'Híres feltalálók: Rubik Ernő (Rubik-kocka), Bíró László (golyóstoll), Puskás Tivadar (telefonközpont).', 'Famous inventors: Ernő Rubik (Rubik\'s Cube), László Bíró (ballpoint pen), Tivadar Puskás (telephone exchange).', 'Rubik, Bíró, Puskás', 'culture,people'],
        ['culture', 'Magyar Nobel-díjasok', 'Hungarian Nobel Laureates', 'Magyarországnak több mint 13 Nobel-díjasa van, köztük Szent-Györgyi Albert (C-vitamin).', 'Hungary has more than 13 Nobel laureates, including Albert Szent-Györgyi (Vitamin C).', '13+ díjas', 'culture,people'],
        ['culture', 'A magyar konyha', 'Hungarian Cuisine', 'A magyar konyha legismertebb ételei: gulyás, pörkölt, lángos, kürtőskalács, töltött káposzta.', 'The most well-known Hungarian dishes: goulash, pörkölt, lángos, chimney cake, stuffed cabbage.', 'Gulyás, pörkölt', 'culture,food'],
        ['culture', 'Az Országgyűlés', 'The Parliament', 'Az Országgyűlés Magyarország törvényhozó szerve. Az Országház Budapesten, a Duna-parton áll.', 'The National Assembly is Hungary\'s legislative body. The Parliament building stands on the Danube bank in Budapest.', 'Törvényhozás', 'culture,government'],
        ['culture', 'A köztársasági elnök', 'The President', 'A köztársasági elnök Magyarország államfője. A miniszterelnök a kormányfő.', 'The President is Hungary\'s head of state. The Prime Minister is the head of government.', 'Államfő vs Kormányfő', 'culture,government'],

        // Geography bonus
        ['geography', 'A Fertő tó', 'Lake Fertő (Neusiedl)', 'A Fertő tó Magyarország és Ausztria határán található. UNESCO Világörökségi helyszín.', 'Lake Fertő is located on the border of Hungary and Austria. UNESCO World Heritage Site.', 'Határon', 'geography,lakes'],
        ['geography', 'Magyar régiók', 'Hungarian Regions', 'Magyarország hét régióra oszlik: Közép-Magyarország, Közép-Dunántúl, Nyugat-Dunántúl, Dél-Dunántúl, Észak-Magyarország, Észak-Alföld, Dél-Alföld.', 'Hungary is divided into seven regions: Central Hungary, Central Transdanubia, Western Transdanubia, Southern Transdanubia, Northern Hungary, Northern Great Plain, Southern Great Plain.', '7 régió', 'geography,regions'],

        // History bonus
        ['history', 'Hunyadi Mátyás', 'King Matthias', 'Mátyás király (1458-1490) Magyarország egyik legismertebb királya. A reneszánsz kultúrát hozta Magyarországra.', 'King Matthias (1458-1490) was one of Hungary\'s most famous kings. He brought Renaissance culture to Hungary.', '1458-1490', 'history,kings'],
        ['history', 'A dualizmus kora', 'The Age of Dualism', 'A dualizmus kora (1867-1918) a fejlődés időszaka volt. Budapest ekkor vált modern nagyvárossá.', 'The age of dualism (1867-1918) was a period of development. Budapest became a modern metropolis during this time.', '1867-1918', 'history,politics'],
    ];
    $stmt = $conn->prepare("INSERT IGNORE INTO knowledge_cards (category, title_hu, title_en, content_hu, content_en, key_fact, tags) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $inserted = 0;
    foreach ($cards as $c) {
        $stmt->bind_param('sssssss', $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6]);
        $stmt->execute();
        if ($stmt->affected_rows > 0) $inserted++;
    }
    $stmt->close();
    $results[] = "Seeded $inserted knowledge cards";
} else {
    $results[] = "Knowledge cards already seeded ($cnt rows)";
}

echo "<h2>v7 Migration Results</h2><ul>";
foreach ($results as $r) echo "<li>" . htmlspecialchars($r) . "</li>";
echo "</ul><p>Done. <a href='index.php'>Back to App</a> | <a href='admin.php'>Admin</a></p>";

$conn->close();
