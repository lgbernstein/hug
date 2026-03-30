<?php
// MagyarOK A1+ Grammar Workbook → Hug MySQL Import
// Source: Szita Szilvia – Pelcz Katalin, MagyarOK Nyelvtani munkafüzet A1+, 1. kötet
// Chapters 2-3 (pages 6-20): van, -ban/-ben, -i, -ul/-ül, regular verbs, possessives, questions, negation
// Safe to re-run: ON DUPLICATE KEY UPDATE

session_start();
$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$batch = 'magyarok_a1_workbook_ch2-3';
$counts = ['phrases' => 0, 'grammar' => 0];

// ============================================================
// CHAPTER 2: van, -ban/-ben, -i, -ul/-ül, regular verbs, possessives, questions
// ============================================================

$ch2 = [
    // --- A létige: van (the verb "to be") ---
    // Conjugation drill sentences
    ['Én mérnök vagyok.', 'I am an engineer.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Te diák vagy.', 'You are a student.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Ön is mérnök?', 'Are you also an engineer?', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Barta Dániel magyar.', 'Barta Dániel is Hungarian.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Mária szegedi.', 'Mária is from Szeged.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Lajos tanár.', 'Lajos is a teacher.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Péter menedzser.', 'Péter is a manager.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Budapesti vagyok.', 'I am from Budapest.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Tanár vagy.', 'You are a teacher.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Negyven éves vagyok.', 'I am forty years old.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Ki vagy?', 'Who are you?', '', 'prep', 'All', 'magyarok-ch2,van,question-words,beginner,level-1'],
    ['Mi vagy?', 'What are you? (occupation)', '', 'prep', 'All', 'magyarok-ch2,van,question-words,beginner,level-1'],
    ['Hány éves vagy?', 'How old are you?', '', 'prep', 'All', 'magyarok-ch2,van,question-words,beginner,level-1'],
    ['Hány éves Ön?', 'How old are you? (formal)', '', 'prep', 'All', 'magyarok-ch2,van,question-words,beginner,level-1'],
    ['Milyen nemzetiségű vagy?', 'What nationality are you?', '', 'prep', 'All', 'magyarok-ch2,van,question-words,beginner,level-1'],
    ['Éva vagyok.', 'I am Éva.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Ön Sarah.', 'You are Sarah. (formal)', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Ő András.', 'He is András.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Húsz éves vagyok.', 'I am twenty years old.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],
    ['Ez egy város.', 'This is a city.', '', 'prep', 'All', 'magyarok-ch2,van,beginner,level-1'],

    // --- Helyhatározóragok: -ban/-ben, -n (location suffixes) ---
    ['Németországban élek.', 'I live in Germany.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Szegeden élsz?', 'Do you live in Szeged?', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Olaszországban élek.', 'I live in Italy.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Vera Magyarországon él.', 'Vera lives in Hungary.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Zürichben tanulok.', 'I study in Zurich.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Düsseldorfban élsz?', 'Do you live in Düsseldorf?', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Most Helsinkiben vagyok.', 'I am in Helsinki now.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Afrikában élek.', 'I live in Africa.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Európában élek.', 'I live in Europe.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Ázsiában élek.', 'I live in Asia.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Amerikában élek.', 'I live in America.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Ausztráliában élek.', 'I live in Australia.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Spanyolországban élek.', 'I live in Spain.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Párizsban élek.', 'I live in Paris.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Az USA-ban élek.', 'I live in the USA.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Berlinben élek.', 'I live in Berlin.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Debrecenben élek.', 'I live in Debrecen.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Budapesten élek.', 'I live in Budapest.', '', 'prep', 'Larry', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Manchesterben élek.', 'I live in Manchester.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Milánóban élek.', 'I live in Milan.', '', 'prep', 'All', 'magyarok-ch2,ban-ben,location,beginner,level-1'],

    // --- Származási hely: -i (origin adjective) ---
    ['Európa → európai', 'Europe → European', '', 'prep', 'All', 'magyarok-ch2,i-suffix,origin,beginner,level-1'],
    ['Amerika → amerikai', 'America → American', '', 'prep', 'All', 'magyarok-ch2,i-suffix,origin,beginner,level-1'],
    ['London → londoni', 'London → from London', '', 'prep', 'All', 'magyarok-ch2,i-suffix,origin,beginner,level-1'],
    ['Szeged → szegedi', 'Szeged → from Szeged', '', 'prep', 'All', 'magyarok-ch2,i-suffix,origin,beginner,level-1'],
    ['Budapest → budapesti', 'Budapest → from Budapest', '', 'prep', 'All', 'magyarok-ch2,i-suffix,origin,beginner,level-1'],

    // --- Milyen nyelven? -ul/-ül (language adverbs) ---
    ['Magyarul beszélek.', 'I speak Hungarian.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Jól beszélsz magyarul.', 'You speak Hungarian well.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Nem tudok spanyolul.', 'I don\'t know Spanish.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Mária nagyon jól beszél arabul.', 'Mária speaks Arabic very well.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Egy kicsit beszélek szerbül.', 'I speak a little Serbian.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Sajnos nem beszélek németül.', 'Unfortunately I don\'t speak German.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Tud héberül?', 'Do you know Hebrew?', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Angolul beszélek.', 'I speak English.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Törökül beszélek.', 'I speak Turkish.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Lengyelül beszélek.', 'I speak Polish.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Finnül beszélek.', 'I speak Finnish.', '', 'prep', 'All', 'magyarok-ch2,ul-ul,language,beginner,level-1'],
    ['Milyen nyelven beszélsz?', 'What language do you speak?', '', 'prep', 'All', 'magyarok-ch2,ul-ul,question-words,beginner,level-1'],

    // --- Szabályos igék (regular verbs) ---
    ['Tanulok.', 'I study/learn.', '', 'prep', 'All', 'magyarok-ch2,regular-verbs,beginner,level-1'],
    ['Tanulsz.', 'You study/learn.', '', 'prep', 'All', 'magyarok-ch2,regular-verbs,beginner,level-1'],
    ['Beszélek.', 'I speak.', '', 'prep', 'All', 'magyarok-ch2,regular-verbs,beginner,level-1'],
    ['Beszélsz.', 'You speak.', '', 'prep', 'All', 'magyarok-ch2,regular-verbs,beginner,level-1'],
    ['Örülök.', 'I am glad.', '', 'prep', 'All', 'magyarok-ch2,regular-verbs,beginner,level-1'],
    ['Örülünk.', 'We are glad.', '', 'prep', 'All', 'magyarok-ch2,regular-verbs,beginner,level-1'],
    ['Japánul tanulok.', 'I am learning Japanese.', '', 'prep', 'All', 'magyarok-ch2,regular-verbs,ul-ul,beginner,level-1'],
    ['Párizsban élünk.', 'We live in Paris.', '', 'prep', 'All', 'magyarok-ch2,regular-verbs,ban-ben,beginner,level-1'],
    ['Péter és Jan Brüsszelben él.', 'Péter and Jan live in Brussels.', '', 'prep', 'All', 'magyarok-ch2,regular-verbs,ban-ben,beginner,level-1'],

    // --- Birtokos személyjelek: -m, -d (possessives 1st/2nd) ---
    ['a címem', 'my address', '', 'prep', 'All', 'magyarok-ch2,possessive-md,beginner,level-1'],
    ['a házam', 'my house', '', 'prep', 'All', 'magyarok-ch2,possessive-md,beginner,level-1'],
    ['a feleségem', 'my wife', '', 'prep', 'All', 'magyarok-ch2,possessive-md,beginner,level-1'],
    ['a házsszámod', 'your house number', '', 'prep', 'All', 'magyarok-ch2,possessive-md,beginner,level-1'],
    ['a házad', 'your house', '', 'prep', 'All', 'magyarok-ch2,possessive-md,beginner,level-1'],
    ['a feleséged', 'your wife', '', 'prep', 'All', 'magyarok-ch2,possessive-md,beginner,level-1'],
    ['a nevem', 'my name', '', 'prep', 'All', 'magyarok-ch2,possessive-md,beginner,level-1'],
    ['a telefonszámom', 'my phone number', '', 'prep', 'All', 'magyarok-ch2,possessive-md,beginner,level-1'],
    ['az anyukám', 'my mom', '', 'prep', 'All', 'magyarok-ch2,possessive-md,beginner,level-1'],

    // --- Kérdőszók: Ki? Mi? Hol? (question words) ---
    ['Ki vagy?', 'Who are you?', '', 'prep', 'All', 'magyarok-ch2,question-words,beginner,level-1'],
    ['Mi ez?', 'What is this?', '', 'prep', 'All', 'magyarok-ch2,question-words,beginner,level-1'],
    ['Hol élsz?', 'Where do you live?', '', 'prep', 'All', 'magyarok-ch2,question-words,beginner,level-1'],
    ['Hány éves vagy?', 'How old are you?', '', 'prep', 'All', 'magyarok-ch2,question-words,beginner,level-1'],
    ['Melyik városban élsz?', 'Which city do you live in?', '', 'prep', 'All', 'magyarok-ch2,question-words,ban-ben,beginner,level-1'],
    ['Milyen nyelven beszélsz?', 'What language do you speak?', '', 'prep', 'All', 'magyarok-ch2,question-words,ul-ul,beginner,level-1'],
    ['Miért tanulsz magyarul?', 'Why are you learning Hungarian?', '', 'prep', 'All', 'magyarok-ch2,question-words,ul-ul,beginner,level-1'],
    ['Hány ember él Kínában?', 'How many people live in China?', '', 'prep', 'All', 'magyarok-ch2,question-words,ban-ben,beginner,level-1'],

    // --- Szórend: fókuszpozíció (word order: focus position) ---
    ['Milyen nyelven tud Hakan?', 'What language does Hakan know?', 'Törökül.', 'prep', 'All', 'magyarok-ch2,word-order,focus,beginner,level-1'],
    ['Ki tud törökül?', 'Who knows Turkish?', 'Hakan.', 'prep', 'All', 'magyarok-ch2,word-order,focus,beginner,level-1'],
    ['Hol él Petra?', 'Where does Petra live?', 'Varsóban.', 'prep', 'All', 'magyarok-ch2,word-order,focus,beginner,level-1'],
    ['Melyik városban élsz?', 'Which city do you live in?', 'Budapesten élek.', 'prep', 'All', 'magyarok-ch2,word-order,focus,beginner,level-1'],
    ['Petra japánul beszél.', 'Petra speaks Japanese. (focus on Japanese)', '', 'prep', 'All', 'magyarok-ch2,word-order,focus,beginner,level-1'],
    ['Petra jól beszél japánul.', 'Petra speaks Japanese well. (focus on well)', '', 'prep', 'All', 'magyarok-ch2,word-order,focus,beginner,level-1'],
    ['Én is tudok japánul.', 'I also know Japanese.', '', 'prep', 'All', 'magyarok-ch2,word-order,focus,beginner,level-1'],
    ['Én vagyok Kis Kornél.', 'I am Kis Kornél.', '', 'prep', 'All', 'magyarok-ch2,word-order,focus,beginner,level-1'],
];

// ============================================================
// CHAPTER 3: van (detail), a/az, possessives -ja/-je, negation, numbers, tud
// ============================================================

$ch3 = [
    // --- A létige: van (detailed — when to use/omit) ---
    ['Jól vagyok.', 'I am well.', '', 'prep', 'All', 'magyarok-ch3,van,beginner,level-1'],
    ['Az irodában vagyok.', 'I am in the office.', '', 'prep', 'All', 'magyarok-ch3,van,ban-ben,beginner,level-1'],
    ['Jól vagy?', 'Are you well?', '', 'prep', 'All', 'magyarok-ch3,van,beginner,level-1'],
    ['Az irodában vagy?', 'Are you in the office?', '', 'prep', 'All', 'magyarok-ch3,van,ban-ben,beginner,level-1'],
    ['Ön jól van?', 'Are you well? (formal)', '', 'prep', 'All', 'magyarok-ch3,van,beginner,level-1'],
    ['Lia az irodában van.', 'Lia is in the office.', '', 'prep', 'All', 'magyarok-ch3,van,ban-ben,beginner,level-1'],
    ['Jól vagyunk.', 'We are well.', '', 'prep', 'All', 'magyarok-ch3,van,beginner,level-1'],
    ['Az irodában vagyunk.', 'We are in the office.', '', 'prep', 'All', 'magyarok-ch3,van,ban-ben,beginner,level-1'],
    ['Önök is jól vannak?', 'Are you (pl. formal) also well?', '', 'prep', 'All', 'magyarok-ch3,van,beginner,level-1'],
    ['Ők is az irodában vannak.', 'They are also in the office.', '', 'prep', 'All', 'magyarok-ch3,van,ban-ben,beginner,level-1'],
    ['Péter magyar?', 'Is Péter Hungarian?', 'Igen, ő.', 'prep', 'All', 'magyarok-ch3,van,beginner,level-1'],
    ['Hogy van a feleséged?', 'How is your wife?', 'Köszönöm, jól.', 'prep', 'All', 'magyarok-ch3,van,possessive,beginner,level-1'],
    ['Hogy vagy?', 'How are you?', 'Jól.', 'prep', 'All', 'magyarok-ch3,van,beginner,level-1'],
    ['Jól vagytok?', 'Are you (pl.) well?', 'Igen, mi is jól vagyunk.', 'prep', 'All', 'magyarok-ch3,van,beginner,level-1'],

    // --- A névelő: a/az, egy (articles) ---
    ['Ez egy drága mobiltelefon.', 'This is an expensive mobile phone.', '', 'prep', 'All', 'magyarok-ch3,article,beginner,level-1'],
    ['Ez egy nyomtató.', 'This is a printer.', '', 'prep', 'All', 'magyarok-ch3,article,beginner,level-1'],
    ['A számítógép egy tárgy.', 'The computer is an object.', '', 'prep', 'All', 'magyarok-ch3,article,beginner,level-1'],
    ['Gabi tanár.', 'Gabi is a teacher.', '', 'prep', 'All', 'magyarok-ch3,article,van,beginner,level-1'],
    ['Én menedzser vagyok.', 'I am a manager.', '', 'prep', 'All', 'magyarok-ch3,article,van,beginner,level-1'],

    // --- Birtokos személyjelek: -ja/-je, -a/-e (3rd person possessives) ---
    ['a telefonja', 'his/her phone', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['a számítógépe', 'his/her computer', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['a lámpája', 'his/her lamp', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['az asztala', 'his/her table', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['a laptopja', 'his/her laptop', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['a háza', 'his/her house', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['az irodája', 'his/her office', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['Nóra irodája', 'Nóra\'s office', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['Robi autója', 'Robi\'s car', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['a barátnőm telefonja', 'my girlfriend\'s phone', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['az Ön e-mailje', 'your email (formal)', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['az anyja', 'his/her mother', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['az apja', 'his/her father', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['a főnök irodája', 'the boss\'s office', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['Barbi tanára most is a nyelviskolában van.', 'Barbi\'s teacher is still at the language school.', '', 'prep', 'All', 'magyarok-ch3,possessive-3rd,ban-ben,beginner,level-2'],

    // --- Többszörös toldalékolás (stacking suffixes) ---
    ['a táskám', 'my bag', '', 'prep', 'All', 'magyarok-ch3,stacking,beginner,level-2'],
    ['a táskámban', 'in my bag', '', 'prep', 'All', 'magyarok-ch3,stacking,ban-ben,beginner,level-2'],
    ['a könyved', 'your book', '', 'prep', 'All', 'magyarok-ch3,stacking,beginner,level-2'],
    ['a könyvedben', 'in your book', '', 'prep', 'All', 'magyarok-ch3,stacking,ban-ben,beginner,level-2'],
    ['Az országomban sok szép város van.', 'In my country there are many beautiful cities.', '', 'prep', 'All', 'magyarok-ch3,stacking,ban-ben,beginner,level-2'],

    // --- A létige tagadása (negation of van) ---
    ['Nem vagyok magyar.', 'I am not Hungarian.', '', 'prep', 'All', 'magyarok-ch3,negation,van,beginner,level-1'],
    ['Nem vagyok mérnök.', 'I am not an engineer.', '', 'prep', 'All', 'magyarok-ch3,negation,van,beginner,level-1'],
    ['Nem vagy magyar?', 'You are not Hungarian?', '', 'prep', 'All', 'magyarok-ch3,negation,van,beginner,level-1'],
    ['Ön nem magyar?', 'You are not Hungarian? (formal)', '', 'prep', 'All', 'magyarok-ch3,negation,van,beginner,level-1'],
    ['Sarah nem mérnök.', 'Sarah is not an engineer.', '', 'prep', 'All', 'magyarok-ch3,negation,van,beginner,level-1'],
    ['Ez nem könyv.', 'This is not a book.', '', 'prep', 'All', 'magyarok-ch3,negation,van,beginner,level-1'],
    ['Nem vagyok jól.', 'I am not well.', '', 'prep', 'All', 'magyarok-ch3,negation,van,beginner,level-1'],
    ['Nem vagyok az irodában.', 'I am not in the office.', '', 'prep', 'All', 'magyarok-ch3,negation,van,ban-ben,beginner,level-1'],
    ['Nincs jól.', 'He/she is not well.', '', 'prep', 'All', 'magyarok-ch3,negation,nincs,beginner,level-2'],
    ['Gábor nincs az irodában.', 'Gábor is not in the office.', '', 'prep', 'All', 'magyarok-ch3,negation,nincs,ban-ben,beginner,level-2'],
    ['A táskámban nincs gyógyszer.', 'There is no medicine in my bag.', '', 'prep', 'All', 'magyarok-ch3,negation,nincs,stacking,beginner,level-2'],
    ['A kollégák nincsenek az irodában.', 'The colleagues are not in the office.', '', 'prep', 'All', 'magyarok-ch3,negation,nincsenek,ban-ben,beginner,level-2'],
    ['Nem vagyunk magyarok.', 'We are not Hungarian.', '', 'prep', 'All', 'magyarok-ch3,negation,van,beginner,level-1'],
    ['Nem vagytok jól?', 'Are you (pl.) not well?', '', 'prep', 'All', 'magyarok-ch3,negation,van,beginner,level-1'],
];

// Insert all phrases
$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags, import_batch) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");

foreach (array_merge($ch2, $ch3) as $r) {
    $stmt->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $batch);
    $stmt->execute();
    $counts['phrases']++;
}
$stmt->close();

// ============================================================
// Update grammar_patterns with chapter references and difficulty
// ============================================================
$grammarUpdates = [
    ['The Verb "Van"', 'magyarok-ch2,magyarok-ch3,van,beginner,level-1'],
    ['VAN / VANNAK', 'magyarok-ch2,magyarok-ch3,van,beginner,level-1'],
    ['Inessive -ban/-ben', 'magyarok-ch2,ban-ben,location,beginner,level-1'],
    ['Weather adjectives', 'magyarok-ch2,ul-ul,beginner,level-1'],
    ['Present tense endings (indefinite conjugation)', 'magyarok-ch2,regular-verbs,beginner,level-1'],
    ['Possessive nouns', 'magyarok-ch2,magyarok-ch3,possessive-md,possessive-3rd,beginner,level-1'],
    ['Possessive Exceptions', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['Exception Nouns', 'magyarok-ch3,possessive-3rd,beginner,level-2'],
    ['Question Words', 'magyarok-ch2,question-words,beginner,level-1'],
    ['Question Words (detailed)', 'magyarok-ch2,question-words,beginner,level-1'],
    ['Demonstratives + article', 'magyarok-ch3,article,beginner,level-2'],
    ['EZ / AZ / EZEK / AZOK', 'magyarok-ch3,article,beginner,level-2'],
];

$stmtGrammar = $conn->prepare("UPDATE grammar_patterns SET tags = CONCAT(COALESCE(tags,''), ',', ?) WHERE pattern = ?");
foreach ($grammarUpdates as $g) {
    $stmtGrammar->bind_param('ss', $g[1], $g[0]);
    $stmtGrammar->execute();
    if ($stmtGrammar->affected_rows > 0) $counts['grammar']++;
}
$stmtGrammar->close();

$conn->close();

header('Content-Type: text/html; charset=utf-8');
echo "<h2>MagyarOK A1+ Workbook Import Complete</h2>";
echo "<p>Batch: <code>$batch</code></p>";
echo "<ul>";
echo "<li>Phrases imported: {$counts['phrases']}</li>";
echo "<li>Grammar patterns tagged: {$counts['grammar']}</li>";
echo "</ul>";
echo "<p><strong>Chapters 2-3: " . count($ch2) . " + " . count($ch3) . " = " . (count($ch2) + count($ch3)) . " phrases</strong></p>";
echo "<p>All tagged with <code>magyarok-ch2</code> / <code>magyarok-ch3</code> + grammar topic + <code>beginner</code> + <code>level-1</code> or <code>level-2</code>.</p>";
echo "<p>Safe to re-run.</p>";
?>
