<?php
// Interview Q&A Sentences → Hug MySQL Import
// Source: Interview Sentences.xlsx + Terke's Lesson + Interview Conversations
// Structured interview question-answer pairs for citizenship interview prep
// Safe to re-run: ON DUPLICATE KEY UPDATE

$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$batch = 'interview_qa_sentences';
$counts = ['larry' => 0, 'maria' => 0, 'both' => 0];

// ============================================================
// INTERVIEW Q&A PAIRS — Larry's answers
// ============================================================
$larry_qa = [
    // Personal info
    ['Mi a neve?', 'What is your name?', 'Larry vagyok.', 'prep', 'Larry', 'interview-qa,personal,essential'],
    ['Mi a családi neve?', 'What is your family name?', 'A családi nevem Bernstein.', 'prep', 'Larry', 'interview-qa,personal,essential'],
    ['Mi a felesége neve?', 'What is your wife\'s name?', 'A feleségem neve Maria.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Mi az anyja neve?', 'What is your mother\'s name?', 'Az anyám neve Marlene volt.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Mi az apja neve?', 'What is your father\'s name?', 'Az apám neve Robert volt.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Mi a fia neve?', 'What is your son\'s name?', 'A fiam neve Tev.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Mi a lánya neve?', 'What is your daughter\'s name?', 'A lányom neve Hannah.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Hány éves Ön?', 'How old are you?', '68 éves vagyok.', 'prep', 'Larry', 'interview-qa,personal,essential'],
    ['Hány éves a felesége?', 'How old is your wife?', 'A feleségem 61 éves.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Hány éves a fia?', 'How old is your son?', 'A fiam 28 éves.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Hány éves a lánya?', 'How old is your daughter?', 'A lányom 26 éves.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Mikor született?', 'When were you born?', '1957. november 7-én születtem.', 'prep', 'Larry', 'interview-qa,personal,essential'],
    ['Mikor született az anyja?', 'When was your mother born?', 'Az anyám 1934-ben született.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Mikor született az apja?', 'When was your father born?', 'Az apám 1934-ben született.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Mikor született a fia?', 'When was your son born?', 'A fiam 1998-ban született.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Mikor született a lánya?', 'When was your daughter born?', 'A lányom 2000-ben született.', 'prep', 'Larry', 'interview-qa,family,essential'],
    // Ancestry
    ['Le tudja vezetni magyar származását?', 'Can you trace your Hungarian ancestry?', 'Az apai nagypapám, Bernstein Edward magyar volt. Polenában született 1901-ben.', 'prep', 'Larry', 'interview-qa,ancestry,essential'],
    ['Melyik felmenője volt magyar?', 'Which ancestor was Hungarian?', 'Az apai nagyapám, Bernstein Edward magyar volt.', 'prep', 'Larry', 'interview-qa,ancestry,essential'],
    ['Mi volt a magyar felmenőjének a neve?', 'What was your Hungarian ancestor\'s name?', 'A neve Bernstein Edward volt.', 'prep', 'Larry', 'interview-qa,ancestry,essential'],
    ['Mikor költözött a magyar felmenője az USA-ba?', 'When did your Hungarian ancestor move to the USA?', '1920-ban emigrált Amerikába.', 'prep', 'Larry', 'interview-qa,ancestry,essential'],
    ['Miért emigrált a nagyapja Amerikába?', 'Why did your grandfather emigrate to America?', 'Trianon és az antiszemitizmus miatt, mert zsidó volt.', 'prep', 'Larry', 'interview-qa,ancestry,essential'],
    // Life
    ['Hol él?', 'Where do you live?', 'Az USA-ban, California államban, Laguna Niguelben élek.', 'prep', 'Larry', 'interview-qa,personal,essential'],
    ['Mi a foglalkozása?', 'What is your occupation?', 'Nyugdíjas orvos vagyok. Belgyógyász szakorvos voltam.', 'prep', 'Larry', 'interview-qa,occupation,essential'],
    ['Hol végezte tanulmányait?', 'Where did you study?', 'A George Washington Egyetemen végeztem tanulmányaimat.', 'prep', 'Larry', 'interview-qa,education,essential'],
    ['Mettől meddig tanult az egyetemen?', 'From when to when did you study?', '1986-tól 1990-ig tanultam a George Washington Egyetemen.', 'prep', 'Larry', 'interview-qa,education,essential'],
    ['Van testvére?', 'Do you have siblings?', 'Igen, egy húgom van. A neve Leslie.', 'prep', 'Larry', 'interview-qa,family,essential'],
    ['Házas?', 'Are you married?', 'Igen, házas vagyok. A feleségem neve Maria.', 'prep', 'Larry', 'interview-qa,personal,essential'],
    ['Van gyereke?', 'Do you have children?', 'Igen, két gyerekem van. A fiam neve Tev, a lányom neve Hannah.', 'prep', 'Larry', 'interview-qa,family,essential'],
    // Motivation
    ['Miért szeretne magyar állampolgár lenni?', 'Why do you want to be a Hungarian citizen?', 'Mert a nagypapám magyar volt. Büszke vagyok a magyar gyökereimre.', 'prep', 'Larry', 'interview-qa,motivation,essential'],
    ['Miért tanul magyarul?', 'Why are you learning Hungarian?', 'Mert magyar származású vagyok, és szeretnék magyar állampolgár lenni.', 'prep', 'Larry', 'interview-qa,motivation,essential'],
    // Travel / culture
    ['Járt már Magyarországon?', 'Have you been to Hungary?', 'Igen, 2025 decemberében jártam Budapesten.', 'prep', 'Larry', 'interview-qa,travel,essential'],
    ['Hogy tetszik Önnek Budapest?', 'How do you like Budapest?', 'Nagyon tetszik. Szép város, az ételek finomak, az épületek gyönyörűek.', 'prep', 'Larry', 'interview-qa,travel,essential'],
    ['Mit tud Magyarországról?', 'What do you know about Hungary?', 'Budapest szép város. Az ételek finomak, sok kávézó és étterem van.', 'prep', 'Larry', 'interview-qa,culture,essential'],
    ['Mivel tölti a szabadidejét?', 'How do you spend your free time?', 'Szeretek a számítógépen dolgozni, kertészkedni és teniszezni.', 'prep', 'Larry', 'interview-qa,hobby,essential'],
];

// ============================================================
// INTERVIEW Q&A PAIRS — Maria's answers
// ============================================================
$maria_qa = [
    ['Mi a neve?', 'What is your name?', 'Maria Zambrano vagyok.', 'prep', 'Maria', 'interview-qa,personal,essential'],
    ['Mi a családi neve?', 'What is your family name?', 'A családi nevem Bernstein. A lánykori nevem Zambrano.', 'prep', 'Maria', 'interview-qa,personal,essential'],
    ['Mi a férje neve?', 'What is your husband\'s name?', 'A férjem neve Larry.', 'prep', 'Maria', 'interview-qa,family,essential'],
    ['Hány éves Ön?', 'How old are you?', '61 éves vagyok.', 'prep', 'Maria', 'interview-qa,personal,essential'],
    ['Mikor született?', 'When were you born?', '1964. december 27-én születtem.', 'prep', 'Maria', 'interview-qa,personal,essential'],
    ['Hol él?', 'Where do you live?', 'Californiában, Laguna Niguelben élek.', 'prep', 'Maria', 'interview-qa,personal,essential'],
    ['Mi a foglalkozása?', 'What is your occupation?', 'Orvos vagyok. Belgyógyász szakorvos.', 'prep', 'Maria', 'interview-qa,occupation,essential'],
    ['Hol végezte tanulmányait?', 'Where did you study?', 'A Minnesotai Állami Egyetemen végeztem tanulmányaimat.', 'prep', 'Maria', 'interview-qa,education,essential'],
    ['Mettől meddig tanult az egyetemen?', 'From when to when did you study?', '1988-tól 1992-ig tanultam a Minnesotai Állami Egyetemen.', 'prep', 'Maria', 'interview-qa,education,essential'],
    ['Van testvére?', 'Do you have siblings?', 'Igen, négy testvérem van. Egy bátyám, egy húgom és két öcsém.', 'prep', 'Maria', 'interview-qa,family,essential'],
    ['Van gyereke?', 'Do you have children?', 'Igen, két gyerekünk van. A fiam neve Tev, a lányom neve Hannah.', 'prep', 'Maria', 'interview-qa,family,essential'],
    ['Ki volt magyar a családjában?', 'Who was Hungarian in your family?', 'A férjem nagypapája magyar volt.', 'prep', 'Maria', 'interview-qa,ancestry,essential'],
    ['Miért szeretne magyar állampolgár lenni?', 'Why do you want to be a Hungarian citizen?', 'Mert a férjem magyar származású. Szeretném megőrizni a családom kultúráját.', 'prep', 'Maria', 'interview-qa,motivation,essential'],
    ['Miért tanul magyarul?', 'Why are you learning Hungarian?', 'Mert a férjem magyar származású, és szeretnék magyar állampolgár lenni.', 'prep', 'Maria', 'interview-qa,motivation,essential'],
    ['Járt már Magyarországon?', 'Have you been to Hungary?', 'Igen, 2025 decemberében jártam Budapesten.', 'prep', 'Maria', 'interview-qa,travel,essential'],
    ['Hogy tetszik Önnek Budapest?', 'How do you like Budapest?', 'Nagyon tetszik Budapest. Szép város, az ételek finomak.', 'prep', 'Maria', 'interview-qa,travel,essential'],
];

// ============================================================
// COMMON INTERVIEW PHRASES — both users
// ============================================================
$common = [
    // Greetings (Jó napot kívánok! already in tana import)
    ['Hogy van?', 'How are you? (formal)', 'Köszönöm, jól.', 'prep', 'All', 'interview-qa,greeting,essential'],
    ['Köszönöm, jól. És Ön?', 'Fine, thanks. And you?', '', 'prep', 'All', 'interview-qa,greeting,essential'],
    ['Örülök a találkozásnak.', 'Nice to meet you.', '', 'prep', 'All', 'interview-qa,greeting,essential'],
    ['Köszönöm szépen az órát.', 'Thank you very much for the lesson.', '', 'prep', 'All', 'interview-qa,polite,essential'],
    ['Viszontlátásra!', 'Goodbye! (formal)', '', 'prep', 'All', 'interview-qa,greeting,essential'],
    // Useful interview phrases
    ['Elismételné legyen szíves?', 'Could you please repeat that?', '', 'prep', 'All', 'interview-qa,polite,essential'],
    ['Elismételné legyen szíves lassabban?', 'Could you please repeat it slower?', '', 'prep', 'All', 'interview-qa,polite,essential'],
    ['Nem értem.', 'I don\'t understand.', '', 'prep', 'All', 'interview-qa,polite,essential'],
    ['Nem, köszönöm. Nem kérek semmit.', 'No, thank you. I don\'t want anything.', '', 'prep', 'All', 'interview-qa,polite,essential'],
    // Culture questions
    ['Ki Magyarország miniszterelnöke?', 'Who is Hungary\'s prime minister?', 'Orbán Viktor.', 'prep', 'All', 'interview-qa,culture,essential'],
    ['Ki Magyarország köztársasági elnöke?', 'Who is Hungary\'s president?', 'Sulyok Tamás.', 'prep', 'All', 'interview-qa,culture,essential'],
    ['Ki Budapest főpolgármestere?', 'Who is Budapest\'s mayor?', 'Karácsony Gergely.', 'prep', 'All', 'interview-qa,culture,essential'],
    ['Milyen színű a magyar zászló?', 'What color is the Hungarian flag?', 'Piros-fehér-zöld.', 'prep', 'All', 'interview-qa,culture,essential'],
    ['Hány betű van a magyar ábécében?', 'How many letters in the Hungarian alphabet?', '44 betű van.', 'prep', 'All', 'interview-qa,culture,essential'],
    ['Hogy hívják a magyar tengert?', 'What is the Hungarian sea called?', 'A neve Balaton. A Balaton egy tó.', 'prep', 'All', 'interview-qa,culture,essential'],
    ['Mit gondol a magyar nyelvről?', 'What do you think about Hungarian?', 'Szép nyelv, de nehéz. Nagyon logikus.', 'prep', 'All', 'interview-qa,culture,essential'],
    // Weather (common interview small talk)
    ['Milyen az idő ma?', 'What\'s the weather like today?', '', 'prep', 'All', 'interview-qa,weather,beginner'],
    ['Milyen az idő ma Laguna Niguelben?', 'What\'s the weather like in Laguna Niguel today?', '', 'prep', 'All', 'interview-qa,weather,beginner'],
];

// Insert all
$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags, import_batch) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");

foreach ($larry_qa as $r) {
    $stmt->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $batch);
    $stmt->execute();
    $counts['larry']++;
}
foreach ($maria_qa as $r) {
    $stmt->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $batch);
    $stmt->execute();
    $counts['maria']++;
}
foreach ($common as $r) {
    $stmt->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $batch);
    $stmt->execute();
    $counts['both']++;
}
$stmt->close();
$conn->close();

$total = $counts['larry'] + $counts['maria'] + $counts['both'];
header('Content-Type: text/html; charset=utf-8');
echo "<h2>Interview Q&A Import Complete</h2>";
echo "<p>Batch: <code>$batch</code></p>";
echo "<ul>";
echo "<li>Larry Q&A pairs: {$counts['larry']}</li>";
echo "<li>Maria Q&A pairs: {$counts['maria']}</li>";
echo "<li>Common phrases: {$counts['both']}</li>";
echo "<li>Total: $total</li>";
echo "</ul>";
echo "<p>Tagged: <code>interview-qa</code> + topic + <code>essential</code></p>";
echo "<p>Safe to re-run.</p>";
?>
