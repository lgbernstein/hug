<?php
// Önéletrajza (Biography) Drill Sentences → Hug MySQL Import
// Larry's and Maria's biography broken into individual drillable sentences
// These are the core sentences the interviewer will walk through section by section
// Safe to re-run: ON DUPLICATE KEY UPDATE

$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$batch = 'oneletrajza_drill';
$counts = ['larry' => 0, 'maria' => 0];

// ============================================================
// LARRY'S ÖNÉLETRAJZA — individual sentences
// ============================================================
$larry = [
    // Opening / personal
    ['Jó napot kívánok! Bernstein Lawrence vagyok.', 'Good day! I am Lawrence Bernstein.', '', 'prep', 'Larry', 'oneletrajza,opening,essential'],
    ['1957. november 7-én születtem Los Angelesben, Californiában.', 'I was born on November 7, 1957, in Los Angeles, California.', '', 'prep', 'Larry', 'oneletrajza,personal,essential'],
    ['Amerikai vagyok, magyar származású.', 'I am American, of Hungarian origin.', '', 'prep', 'Larry', 'oneletrajza,personal,essential'],
    ['Orvos voltam, belgyógyász szakorvos.', 'I was a doctor, an internist specialist.', '', 'prep', 'Larry', 'oneletrajza,occupation,essential'],
    ['Most nyugdíjas vagyok.', 'Now I am retired.', '', 'prep', 'Larry', 'oneletrajza,occupation,essential'],
    ['Laguna Niguelben élek, Californiában.', 'I live in Laguna Niguel, California.', '', 'prep', 'Larry', 'oneletrajza,personal,essential'],

    // Family
    ['Házas vagyok.', 'I am married.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['A feleségem neve Maria Zambrano.', 'My wife\'s name is Maria Zambrano.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['Ő is orvos, belgyógyász.', 'She is also a doctor, an internist.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['Maria Kolumbiában, Medellínben született.', 'Maria was born in Medellín, Colombia.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['A feleségem 61 éves.', 'My wife is 61 years old.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['Két gyerekünk van.', 'We have two children.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['A fiam neve Tev. 1998-ban született.', 'My son\'s name is Tev. He was born in 1998.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['Tev egyetemista, New Yorkban él.', 'Tev is a university student, he lives in New York.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['A lányom neve Hannah. 2000-ben született.', 'My daughter\'s name is Hannah. She was born in 2000.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['Hannah szoftvermérnök, Los Angelesben él.', 'Hannah is a software engineer, she lives in Los Angeles.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],

    // Parents
    ['Az édesanyám neve Marlene volt.', 'My mother\'s name was Marlene.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['Az édesapám neve Robert volt.', 'My father\'s name was Robert.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['Az édesapám ügyvéd volt.', 'My father was a lawyer.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['A szüleim már nem élnek.', 'My parents are no longer alive.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['Az édesanyám 2022-ben hunyt el.', 'My mother passed away in 2022.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['Az édesapám 2021-ben hunyt el.', 'My father passed away in 2021.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],

    // Siblings
    ['Egy húgom van. A neve Leslie.', 'I have one younger sister. Her name is Leslie.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['A húgom Los Angelesben él.', 'My sister lives in Los Angeles.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],
    ['A húgom 65 éves.', 'My sister is 65 years old.', '', 'prep', 'Larry', 'oneletrajza,family,essential'],

    // Hungarian ancestry
    ['Az apai nagyapám Bernstein Edward volt.', 'My paternal grandfather was Bernstein Edward.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],
    ['Polenában született, Magyarországon, 1901-ben.', 'He was born in Polena, Hungary, in 1901.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],
    ['Polena Munkács mellett volt.', 'Polena was near Munkács.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],
    ['Ma ez a terület Ukrajna része.', 'Today this area is part of Ukraine.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],
    ['1920-ban emigrált Amerikába, hajóval New Yorkba.', 'He emigrated to America in 1920, by ship to New York.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],
    ['Trianon és az antiszemitizmus miatt emigrált.', 'He emigrated because of Trianon and antisemitism.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],
    ['Üzletember volt. Női ruha üzletet nyitott.', 'He was a businessman. He opened a women\'s clothing store.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],
    ['A családot Californiába költöztette a felesége egészsége miatt.', 'He moved the family to California for his wife\'s health.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],
    ['Két fia volt: Robert, aki ügyvéd volt, és Donny, aki orvos.', 'He had two sons: Robert, who was a lawyer, and Donny, who was a doctor.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],
    ['Robert az édesapám volt.', 'Robert was my father.', '', 'prep', 'Larry', 'oneletrajza,ancestry,essential'],

    // Budapest trip
    ['2025 decemberében Budapesten voltunk.', 'We were in Budapest in December 2025.', '', 'prep', 'Larry', 'oneletrajza,travel,essential'],
    ['Budapest szép város.', 'Budapest is a beautiful city.', '', 'prep', 'Larry', 'oneletrajza,travel,essential'],
    ['Az ételek finomak, az emberek kedvesek.', 'The food is delicious, the people are kind.', '', 'prep', 'Larry', 'oneletrajza,travel,essential'],

    // Motivation
    ['Büszke vagyok a magyar örökségemre.', 'I am proud of my Hungarian heritage.', '', 'prep', 'Larry', 'oneletrajza,motivation,essential'],
    ['Szeretnék magyar állampolgár lenni.', 'I would like to become a Hungarian citizen.', '', 'prep', 'Larry', 'oneletrajza,motivation,essential'],
    ['Szeretném megőrizni a családom kultúráját.', 'I want to preserve my family\'s culture.', '', 'prep', 'Larry', 'oneletrajza,motivation,essential'],
    ['Szeretném átadni ezt az örökséget a gyerekeimnek.', 'I want to pass this heritage on to my children.', '', 'prep', 'Larry', 'oneletrajza,motivation,essential'],

    // Education
    ['A George Washington Egyetemen tanultam.', 'I studied at George Washington University.', '', 'prep', 'Larry', 'oneletrajza,education,essential'],
    ['1986-tól 1990-ig tanultam az egyetemen.', 'I studied at university from 1986 to 1990.', '', 'prep', 'Larry', 'oneletrajza,education,essential'],
];

// ============================================================
// MARIA'S ÖNÉLETRAJZA — individual sentences
// ============================================================
$maria = [
    // Opening / personal
    ['Jó napot kívánok! Maria Zambrano vagyok.', 'Good day! I am Maria Zambrano.', '', 'prep', 'Maria', 'oneletrajza,opening,essential'],
    ['1964. december 27-én születtem Medellínben, Kolumbiában.', 'I was born on December 27, 1964, in Medellín, Colombia.', '', 'prep', 'Maria', 'oneletrajza,personal,essential'],
    ['Amerikai vagyok.', 'I am American.', '', 'prep', 'Maria', 'oneletrajza,personal,essential'],
    ['Orvos vagyok, belgyógyász szakorvos.', 'I am a doctor, an internist specialist.', '', 'prep', 'Maria', 'oneletrajza,occupation,essential'],
    ['Jelenleg otthon dolgozom.', 'I currently work from home.', '', 'prep', 'Maria', 'oneletrajza,occupation,essential'],
    ['Laguna Niguelben élek, Californiában.', 'I live in Laguna Niguel, California.', '', 'prep', 'Maria', 'oneletrajza,personal,essential'],

    // Family
    ['Házas vagyok.', 'I am married.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['A férjem neve Larry.', 'My husband\'s name is Larry.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['A férjem magyar származású.', 'My husband is of Hungarian origin.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Két gyerekünk van.', 'We have two children.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['A fiam neve Tev. 1998-ban született.', 'My son\'s name is Tev. He was born in 1998.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['A lányom neve Hannah. 2000-ben született.', 'My daughter\'s name is Hannah. She was born in 2000.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],

    // Siblings
    ['Négy testvérem van.', 'I have four siblings.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Egy bátyám van, Lajos. Ő 62 éves, New Yorkban él.', 'I have an older brother, Lajos. He is 62, lives in New York.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Egy húgom van, Szilvia. Ő New Yorkban él.', 'I have a younger sister, Szilvia. She lives in New York.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Két öcsém van: Josef és Peter.', 'I have two younger brothers: Josef and Peter.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Josef 55 éves, villanyszerelő, Californiában él.', 'Josef is 55, an electrician, lives in California.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Peter 52 éves, Minneapolisban él.', 'Peter is 52, lives in Minneapolis.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],

    // Parents
    ['Az édesanyám neve Leonor volt.', 'My mother\'s name was Leonor.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Az édesapám neve Adolf volt.', 'My father\'s name was Adolf.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Az édesanyám nővér volt, az édesapám pedig mérnök volt.', 'My mother was a nurse, and my father was an engineer.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Az édesanyám 58 éves volt, amikor elhunyt.', 'My mother was 58 years old when she passed away.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],
    ['Az édesapám 78 éves volt, amikor elhunyt.', 'My father was 78 years old when he passed away.', '', 'prep', 'Maria', 'oneletrajza,family,essential'],

    // Ancestry (through Larry)
    ['A férjem nagypapája, Bernstein Edward magyar volt.', 'My husband\'s grandfather, Bernstein Edward, was Hungarian.', '', 'prep', 'Maria', 'oneletrajza,ancestry,essential'],
    ['Mert a férjem magyar származású, ezért én is szeretnék magyar állampolgár lenni.', 'Because my husband is of Hungarian origin, I also want to become a Hungarian citizen.', '', 'prep', 'Maria', 'oneletrajza,motivation,essential'],

    // Education
    ['A Minnesotai Állami Egyetemen tanultam.', 'I studied at Minnesota State University.', '', 'prep', 'Maria', 'oneletrajza,education,essential'],
    ['1988-tól 1992-ig tanultam az egyetemen.', 'I studied at university from 1988 to 1992.', '', 'prep', 'Maria', 'oneletrajza,education,essential'],

    // Travel
    ['2025 decemberében Budapesten voltunk.', 'We were in Budapest in December 2025.', '', 'prep', 'Maria', 'oneletrajza,travel,essential'],
    ['Nagyon tetszik Budapest.', 'I really like Budapest.', '', 'prep', 'Maria', 'oneletrajza,travel,essential'],
];

// Insert all
$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags, import_batch) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");

foreach ($larry as $r) {
    $stmt->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $batch);
    $stmt->execute();
    $counts['larry']++;
}
foreach ($maria as $r) {
    $stmt->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $batch);
    $stmt->execute();
    $counts['maria']++;
}
$stmt->close();
$conn->close();

$total = $counts['larry'] + $counts['maria'];
header('Content-Type: text/html; charset=utf-8');
echo "<h2>Önéletrajza Drill Import Complete</h2>";
echo "<p>Batch: <code>$batch</code></p>";
echo "<ul>";
echo "<li>Larry's bio sentences: {$counts['larry']}</li>";
echo "<li>Maria's bio sentences: {$counts['maria']}</li>";
echo "<li>Total: $total</li>";
echo "</ul>";
echo "<p>Tagged: <code>oneletrajza</code> + topic + <code>essential</code></p>";
echo "<p>Safe to re-run.</p>";
?>
