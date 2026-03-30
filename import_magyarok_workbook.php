<?php
// MagyarOK A1+ Grammar Workbook → Hug MySQL Import
// Source: Szita Szilvia – Pelcz Katalin, MagyarOK Nyelvtani munkafüzet A1+, 1. kötet
// Chapters 2-9: van, -ban/-ben, -i, -ul/-ül, regular verbs, possessives, questions, negation,
// demonstratives, ordinals, months, seasons, -ba/-be, time, -val/-vel, definite/indefinite, colors, clothing, occupations
// Safe to re-run: ON DUPLICATE KEY UPDATE

session_start();
$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$batch = 'magyarok_a1_workbook_ch2-9';
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

// ============================================================
// CHAPTER 4: Demonstratives, ordinals, months/seasons, dates, -ba/-be
// ============================================================

$ch4 = [
    // --- Demonstratives: ez/az ---
    ['Ez egy könyv.', 'This is a book.', '', 'prep', 'All', 'magyarok-ch4,demonstrative,beginner,level-1'],
    ['Az egy ház.', 'That is a house.', '', 'prep', 'All', 'magyarok-ch4,demonstrative,beginner,level-1'],
    ['Ez a könyv érdekes.', 'This book is interesting.', '', 'prep', 'All', 'magyarok-ch4,demonstrative,article,beginner,level-1'],
    ['Az a ház nagy.', 'That house is big.', '', 'prep', 'All', 'magyarok-ch4,demonstrative,article,beginner,level-1'],
    ['Ezek a diákok magyarok.', 'These students are Hungarian.', '', 'prep', 'All', 'magyarok-ch4,demonstrative,plural,beginner,level-1'],
    ['Azok az autók drágák.', 'Those cars are expensive.', '', 'prep', 'All', 'magyarok-ch4,demonstrative,plural,beginner,level-1'],
    ['Ez az én könyvem.', 'This is my book.', '', 'prep', 'All', 'magyarok-ch4,demonstrative,possessive,beginner,level-1'],

    // --- Ordinals ---
    ['Első', 'first', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],
    ['Második', 'second', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],
    ['Harmadik', 'third', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],
    ['Negyedik', 'fourth', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],
    ['Ötödik', 'fifth', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],
    ['Hatodik', 'sixth', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],
    ['Hetedik', 'seventh', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],
    ['Nyolcadik', 'eighth', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],
    ['Kilencedik', 'ninth', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],
    ['Tizedik', 'tenth', '', 'prep', 'All', 'magyarok-ch4,ordinal,beginner,level-1'],

    // --- Months ---
    ['Január', 'January', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['Február', 'February', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['Március', 'March', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['Április', 'April', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['Május', 'May', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['Június', 'June', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['Július', 'July', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['Augusztus', 'August', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['Szeptember', 'September', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['Október', 'October', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['November', 'November', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],
    ['December', 'December', '', 'prep', 'All', 'magyarok-ch4,month,beginner,level-1'],

    // --- Seasons ---
    ['Tavasz', 'spring', '', 'prep', 'All', 'magyarok-ch4,season,beginner,level-1'],
    ['Nyár', 'summer', '', 'prep', 'All', 'magyarok-ch4,season,beginner,level-1'],
    ['Ősz', 'autumn / fall', '', 'prep', 'All', 'magyarok-ch4,season,beginner,level-1'],
    ['Tél', 'winter', '', 'prep', 'All', 'magyarok-ch4,season,beginner,level-1'],
    ['Tavasszal meleg van.', 'In spring it is warm.', '', 'prep', 'All', 'magyarok-ch4,season,sentence,beginner,level-1'],
    ['Nyáron forró van.', 'In summer it is hot.', '', 'prep', 'All', 'magyarok-ch4,season,sentence,beginner,level-1'],
    ['Ősszel hűvös van.', 'In autumn it is cool.', '', 'prep', 'All', 'magyarok-ch4,season,sentence,beginner,level-1'],
    ['Télen hideg van.', 'In winter it is cold.', '', 'prep', 'All', 'magyarok-ch4,season,sentence,beginner,level-1'],

    // --- -ba/-be (into) ---
    ['Megyek a boltba.', 'I\'m going to the store.', '', 'prep', 'All', 'magyarok-ch4,ba-be,direction,beginner,level-1'],
    ['Bemegyek az iskolába.', 'I\'m going into the school.', '', 'prep', 'All', 'magyarok-ch4,ba-be,direction,beginner,level-1'],
    ['Budapestre megyek.', 'I\'m going to Budapest.', '', 'prep', 'All', 'magyarok-ch4,ba-be,direction,beginner,level-1'],
    ['Magyarországra megyek.', 'I\'m going to Hungary.', '', 'prep', 'All', 'magyarok-ch4,ba-be,direction,beginner,level-1'],
    ['Étterembe megyünk.', 'We\'re going to a restaurant.', '', 'prep', 'All', 'magyarok-ch4,ba-be,direction,beginner,level-1'],
    ['Hova mész?', 'Where are you going?', '', 'prep', 'All', 'magyarok-ch4,ba-be,question,beginner,level-1'],

    // --- Dates ---
    ['Mikor született?', 'When were you born?', '', 'prep', 'All', 'magyarok-ch4,date,question,beginner,level-1'],
    ['1957. november 7-én születtem.', 'I was born on November 7, 1957.', '', 'prep', 'Larry', 'magyarok-ch4,date,beginner,level-1'],
    ['Augusztus 20-án van az államalapítás napja.', 'August 20 is the day of the state foundation.', '', 'prep', 'All', 'magyarok-ch4,date,culture,beginner,level-1'],
];

// ============================================================
// CHAPTER 5: Hány/Mennyi, object -t, -s/-sz/-z verbs, transitive/intransitive, ik-verbs
// ============================================================

$ch5 = [
    // --- Hány? Mennyi? ---
    ['Hány tojás kell a palacsintába?', 'How many eggs for the pancake?', 'Kettő.', 'prep', 'All', 'magyarok-ch5,hany-mennyi,beginner,level-2'],
    ['Mennyi liszt kell a palacsintába?', 'How much flour for the pancake?', 'Húsz deka.', 'prep', 'All', 'magyarok-ch5,hany-mennyi,beginner,level-2'],
    ['Hány kiló liszt van itthon?', 'How many kilos of flour are at home?', '', 'prep', 'All', 'magyarok-ch5,hany-mennyi,beginner,level-2'],

    // --- A tárgyrag: -t (object marker) ---
    ['kávét', 'coffee (object)', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,beginner,level-2'],
    ['szilvát', 'plum (object)', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,beginner,level-2'],
    ['barackot', 'peach (object)', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,beginner,level-2'],
    ['halat', 'fish (object)', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,beginner,level-2'],
    ['mézet', 'honey (object)', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,beginner,level-2'],
    ['gyümölcsöt', 'fruit (object)', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,beginner,level-2'],
    ['házat', 'house (object)', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,beginner,level-2'],
    ['könyvet', 'book (object)', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,beginner,level-2'],
    ['Jó reggelt kívánok!', 'Good morning!', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,greeting,beginner,level-2'],
    ['Jó napot kívánok!', 'Good day!', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,greeting,beginner,level-2'],
    ['Jó estét kívánok!', 'Good evening!', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,greeting,beginner,level-2'],
    ['Jó éjszakát kívánok!', 'Good night!', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,greeting,beginner,level-2'],
    ['Jó étvágyat kívánok!', 'Bon appétit!', '', 'prep', 'All', 'magyarok-ch5,targyrag-t,greeting,beginner,level-2'],

    // --- Transitive vs intransitive ---
    ['A könyvtárban vagyok.', 'I am in the library. (intransitive)', '', 'prep', 'All', 'magyarok-ch5,transitive,beginner,level-2'],
    ['Évával beszélgetek.', 'I am chatting with Éva. (intransitive)', '', 'prep', 'All', 'magyarok-ch5,transitive,val-vel,beginner,level-2'],
    ['Múzeumba megyek.', 'I am going to the museum. (intransitive)', '', 'prep', 'All', 'magyarok-ch5,transitive,beginner,level-2'],
    ['Gulyáslevest főzök.', 'I am cooking goulash soup. (transitive)', '', 'prep', 'All', 'magyarok-ch5,transitive,targyrag-t,beginner,level-2'],
    ['Lecsót csinálok.', 'I am making lecso. (transitive)', '', 'prep', 'All', 'magyarok-ch5,transitive,targyrag-t,beginner,level-2'],
    ['Csokoládét veszek.', 'I am buying chocolate. (transitive)', '', 'prep', 'All', 'magyarok-ch5,transitive,targyrag-t,beginner,level-2'],
    ['Paprikát teszek a lecsóba.', 'I put paprika in the lecso. (transitive)', '', 'prep', 'All', 'magyarok-ch5,transitive,targyrag-t,beginner,level-2'],
    ['Egy magas házat látok.', 'I see a tall house. (transitive)', '', 'prep', 'All', 'magyarok-ch5,transitive,targyrag-t,beginner,level-2'],

    // --- -s, -sz, -z végű igék (mos, vesz, főz) ---
    ['Mosok.', 'I wash.', '', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Mosol.', 'You wash.', '', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Veszek.', 'I buy.', '', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Veszel.', 'You buy.', '', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Főzök.', 'I cook.', '', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Főzöl.', 'You cook.', '', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Mit főzöl?', 'What are you cooking?', 'Vacsorát.', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Mit vesz a piacon?', 'What do you buy at the market?', 'Friss zöldséget.', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Mit hoz a boltból?', 'What do you bring from the shop?', 'Gyümölcsöt.', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Mit tesz a levesbe?', 'What do you put in the soup?', 'Sárgarépát.', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],
    ['Mit keres?', 'What are you looking for?', 'A piacot.', 'prep', 'All', 'magyarok-ch5,s-sz-z-verbs,beginner,level-2'],

    // --- Ik-verbs: iszik, eszik, sörözik ---
    ['Iszom.', 'I drink.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,beginner,level-2'],
    ['Iszol.', 'You drink.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,beginner,level-2'],
    ['Iszik.', 'He/she drinks.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,beginner,level-2'],
    ['Eszem.', 'I eat.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,beginner,level-2'],
    ['Eszel.', 'You eat.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,beginner,level-2'],
    ['Eszik.', 'He/she eats.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,beginner,level-2'],
    ['A moziban filmet nézek.', 'I watch a movie at the cinema.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,ban-ben,beginner,level-2'],
    ['A kávézóban süteményt eszem és kávét iszom.', 'I eat pastry and drink coffee at the café.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,ban-ben,beginner,level-2'],
    ['Az étteremben ebédelek.', 'I have lunch at the restaurant.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,ban-ben,beginner,level-2'],
    ['A piacon zöldséget és gyümölcsöt veszek.', 'I buy vegetables and fruit at the market.', '', 'prep', 'All', 'magyarok-ch5,ik-verbs,targyrag-t,beginner,level-2'],

    // --- Number suffixes: egyet, kettőből, háromba ---
    ['Hány tojást veszel?', 'How many eggs do you buy?', 'Hatot.', 'prep', 'All', 'magyarok-ch5,number-suffix,beginner,level-2'],
    ['Hány almát kér?', 'How many apples would you like?', 'Hármat.', 'prep', 'All', 'magyarok-ch5,number-suffix,beginner,level-2'],
    ['Két csokoládét kérek.', 'I\'d like two chocolates.', '', 'prep', 'All', 'magyarok-ch5,number-suffix,targyrag-t,beginner,level-2'],
    ['Bocsánat, hány csokoládét kér?', 'Excuse me, how many chocolates would you like?', 'Kettőt.', 'prep', 'All', 'magyarok-ch5,number-suffix,beginner,level-2'],

    // --- Demonstrative pronoun suffixes: ebbe, abból ---
    ['Ebből a krumpliból kérek.', 'I\'d like some of this potato.', '', 'prep', 'All', 'magyarok-ch5,demonstrative-suffix,beginner,level-2'],
    ['Abból a salátából veszek.', 'I\'ll buy some of that salad.', '', 'prep', 'All', 'magyarok-ch5,demonstrative-suffix,beginner,level-2'],
    ['Erre a piacra megyek.', 'I\'m going to this market.', '', 'prep', 'All', 'magyarok-ch5,demonstrative-suffix,beginner,level-2'],
    ['Abba a boltba megyek.', 'I\'m going into that shop.', '', 'prep', 'All', 'magyarok-ch5,demonstrative-suffix,beginner,level-2'],
    ['Ezt a mézet kérem.', 'I\'d like this honey.', '', 'prep', 'All', 'magyarok-ch5,demonstrative-suffix,beginner,level-2'],
    ['Azt a mézet kérem.', 'I\'d like that honey.', '', 'prep', 'All', 'magyarok-ch5,demonstrative-suffix,beginner,level-2'],

    // --- Practice: intransitive sentences ---
    ['Budapesten lakom.', 'I live in Budapest.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,beginner,level-2'],
    ['Magyarországon élek.', 'I live in Hungary.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,beginner,level-2'],
    ['Ma este moziba megyek.', 'I\'m going to the cinema tonight.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,beginner,level-2'],
    ['Délután nyelviskolában vagyok.', 'I\'m at the language school in the afternoon.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,beginner,level-2'],

    // --- Practice: transitive sentences ---
    ['Virágot veszek.', 'I buy flowers.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,targyrag-t,beginner,level-2'],
    ['Egy csésze kávét kérek.', 'I\'d like a cup of coffee.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,targyrag-t,beginner,level-2'],
    ['Levest főzök.', 'I\'m cooking soup.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,targyrag-t,beginner,level-2'],
    ['Egy hosszú e-mailt írok.', 'I\'m writing a long email.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,targyrag-t,beginner,level-2'],
    ['Gulyást főzök.', 'I\'m cooking goulash.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,targyrag-t,beginner,level-2'],
    ['Szendvicset eszem.', 'I\'m eating a sandwich.', '', 'prep', 'All', 'magyarok-ch5,transitive-practice,ik-verbs,beginner,level-2'],
];

// ============================================================
// CHAPTER 7: verb prefixes, definite/indefinite conjugation, definite conjugation table
// ============================================================

$ch7 = [
    // --- Verb prefixes: fel-, le-, be-, ki- ---
    ['Felmegyek az emeletre.', 'I go up to the floor.', '', 'prep', 'All', 'magyarok-ch7,verb-prefix,beginner,level-3'],
    ['Lejövök az emeletről.', 'I come down from the floor.', '', 'prep', 'All', 'magyarok-ch7,verb-prefix,beginner,level-3'],
    ['Bemegyek az irodába.', 'I go into the office.', '', 'prep', 'All', 'magyarok-ch7,verb-prefix,beginner,level-3'],
    ['Kijövök az irodából.', 'I come out of the office.', '', 'prep', 'All', 'magyarok-ch7,verb-prefix,beginner,level-3'],
    ['Kijövök a házból.', 'I come out of the house.', '', 'prep', 'All', 'magyarok-ch7,verb-prefix,beginner,level-3'],
    ['Bemegy Ági a könyvtárba?', 'Is Ági going into the library?', 'Be. / Igen, bemegy.', 'prep', 'All', 'magyarok-ch7,verb-prefix,beginner,level-3'],
    ['Felmész az emeletre?', 'Are you going up?', 'Fel. / Igen, felmegyek.', 'prep', 'All', 'magyarok-ch7,verb-prefix,beginner,level-3'],
    ['Kimegyünk a kertbe?', 'Shall we go out to the garden?', 'Ki. / Igen, kimegyünk.', 'prep', 'All', 'magyarok-ch7,verb-prefix,beginner,level-3'],
    ['Lefekszel aludni?', 'Are you going to lie down to sleep?', 'Le. / Igen, lefekszem.', 'prep', 'All', 'magyarok-ch7,verb-prefix,beginner,level-3'],

    // --- Indefinite object types (határozatlan tárgy → indefinite conjugation) ---
    ['Házi feladatot írok.', 'I\'m writing homework. (indefinite — no article)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],
    ['Szavakat tanulok.', 'I\'m learning words. (indefinite)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],
    ['Egy érdekes könyvet olvasok.', 'I\'m reading an interesting book. (indefinite — egy)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],
    ['Mennyi sajtot kér?', 'How much cheese do you want? (indefinite — mennyi)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],
    ['Három sört kérek.', 'I\'d like three beers. (indefinite — number)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],
    ['Sok e-mailt írok.', 'I write a lot of emails. (indefinite — sok)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],
    ['Kit szeretsz?', 'Who do you like? (indefinite — kit question)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],
    ['Mit írsz?', 'What are you writing? (indefinite — mit question)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],
    ['Szeretek valakit.', 'I like someone. (indefinite — valaki)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],
    ['Nem látok senkit.', 'I don\'t see anyone. (indefinite — senki)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,negation,beginner,level-3'],
    ['Mindent tudok.', 'I know everything. (indefinite — mindent)', '', 'prep', 'All', 'magyarok-ch7,indefinite-conj,beginner,level-3'],

    // --- Definite object types (határozott tárgy → definite conjugation) ---
    ['A szomszéd fiút szeretem.', 'I like the neighbor boy. (definite — a/az article)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,beginner,level-3'],
    ['Ezt a fiút szeretem.', 'I like this boy. (definite — ezt)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,beginner,level-3'],
    ['Azt a fiút szeretem.', 'I like that boy. (definite — azt)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,beginner,level-3'],
    ['A barátomat várom.', 'I\'m waiting for my friend. (definite — possessive on object)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,possessive,beginner,level-3'],
    ['Ismerem a lányodat.', 'I know your daughter. (definite — possessive)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,possessive,beginner,level-3'],
    ['Ezt kérem.', 'I\'d like this. (definite — ezt)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,beginner,level-3'],
    ['Ezeket kérem.', 'I\'d like these. (definite — ezeket)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,beginner,level-3'],
    ['Azt nem szeretem.', 'I don\'t like that. (definite — azt)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,negation,beginner,level-3'],
    ['Dénest szeretem.', 'I like Dénes. (definite — proper name)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,beginner,level-3'],
    ['Jól ismerem Pécset.', 'I know Pécs well. (definite — proper name)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,beginner,level-3'],
    ['Melyik fiút szereted?', 'Which boy do you like? (definite — melyik)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,beginner,level-3'],

    // --- Definite conjugation table: tanul, szeret, süt ---
    ['Tanulom ezt a nyelvet.', 'I\'m learning this language. (definite)', '', 'prep', 'All', 'magyarok-ch7,definite-conj,conjugation,beginner,level-3'],
    ['Tanulod a szavakat?', 'Are you learning the words?', '', 'prep', 'All', 'magyarok-ch7,definite-conj,conjugation,beginner,level-3'],
    ['Tanulja a ragozást.', 'He/she is learning the conjugation.', '', 'prep', 'All', 'magyarok-ch7,definite-conj,conjugation,beginner,level-3'],
    ['Tanuljuk a magyar nyelvet.', 'We are learning Hungarian.', '', 'prep', 'All', 'magyarok-ch7,definite-conj,conjugation,beginner,level-3'],
    ['Szeretem Budapestet.', 'I love Budapest.', '', 'prep', 'All', 'magyarok-ch7,definite-conj,conjugation,beginner,level-3'],
    ['Szereted a gulyáslevest?', 'Do you like goulash soup?', '', 'prep', 'All', 'magyarok-ch7,definite-conj,conjugation,beginner,level-3'],
    ['Szereti az őszt?', 'Does he/she like autumn?', '', 'prep', 'All', 'magyarok-ch7,definite-conj,conjugation,beginner,level-3'],
    ['Szeretjük a magyar nyelvet.', 'We love the Hungarian language.', '', 'prep', 'All', 'magyarok-ch7,definite-conj,conjugation,beginner,level-3'],
    ['Ismered a magyar konyhát?', 'Do you know Hungarian cuisine?', '', 'prep', 'All', 'magyarok-ch7,definite-conj,conjugation,beginner,level-3'],

    // --- Practice: definite vs indefinite ---
    ['A házi feladatot írom.', 'I\'m writing the homework. (definite)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Ezt a szót nem értjük.', 'We don\'t understand this word.', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,negation,beginner,level-3'],
    ['A magyartanárt várom.', 'I\'m waiting for the Hungarian teacher.', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Jól ismerem Magyarországot.', 'I know Hungary well.', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Az új szavakat tanulom.', 'I\'m learning the new words.', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Látod azt az éttermet?', 'Do you see that restaurant?', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Melyik süteményt kéred?', 'Which cake do you want?', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Melyik múzeumot akarja látni?', 'Which museum do you want to see? (formal)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Ismeri Sopront?', 'Do you know Sopron? (formal, definite)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Magyarul tanulok.', 'I\'m learning Hungarian. (indefinite — no object)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['A magyar nyelvet tanulom.', 'I\'m learning the Hungarian language. (definite)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Valamit mindig tanulunk.', 'We always learn something. (indefinite — valamit)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Hanna most tanulja a ragozást.', 'Hanna is now learning the conjugation. (definite)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Ezt nem értem.', 'I don\'t understand this.', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,negation,beginner,level-3'],
    ['Semmit nem értem.', 'I don\'t understand anything. (indefinite — semmit)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,negation,beginner,level-3'],
    ['Mindent értek.', 'I understand everything. (indefinite — mindent)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Zenét hallgatok.', 'I\'m listening to music. (indefinite)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['A hétvégét otthon töltöm.', 'I\'m spending the weekend at home. (definite)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Kit keresel?', 'Who are you looking for? (indefinite — kit)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
    ['Egy e-mailt küldök Gábornak.', 'I\'m sending an email to Gábor. (indefinite — egy)', '', 'prep', 'All', 'magyarok-ch7,def-vs-indef,beginner,level-3'],
];

// ============================================================
// CHAPTER 6: Telling time, -val/-vel (with/by), review
// ============================================================

$ch6 = [
    // --- Telling time ---
    ['Hány óra van?', 'What time is it?', '', 'prep', 'All', 'magyarok-ch6,time,question,beginner,level-2'],
    ['Egy óra van.', 'It is one o\'clock.', '', 'prep', 'All', 'magyarok-ch6,time,beginner,level-2'],
    ['Kettő óra van.', 'It is two o\'clock.', '', 'prep', 'All', 'magyarok-ch6,time,beginner,level-2'],
    ['Fél három van.', 'It is half past two.', '', 'prep', 'All', 'magyarok-ch6,time,beginner,level-2'],
    ['Negyed négy van.', 'It is quarter past three.', '', 'prep', 'All', 'magyarok-ch6,time,beginner,level-2'],
    ['Háromnegyed öt van.', 'It is quarter to five.', '', 'prep', 'All', 'magyarok-ch6,time,beginner,level-2'],
    ['Tizennégy óra tizenöt perc van.', 'It is 14:15.', '', 'prep', 'All', 'magyarok-ch6,time,beginner,level-2'],
    ['Este nyolc óra harminc perc van.', 'It is 8:30 PM.', '', 'prep', 'All', 'magyarok-ch6,time,beginner,level-2'],
    ['Hány órakor?', 'At what time?', '', 'prep', 'All', 'magyarok-ch6,time,question,beginner,level-2'],
    ['Nyolckor kelek.', 'I get up at eight.', '', 'prep', 'All', 'magyarok-ch6,time,routine,beginner,level-2'],
    ['Délben ebédelek.', 'I eat lunch at noon.', '', 'prep', 'All', 'magyarok-ch6,time,routine,beginner,level-2'],

    // --- -val/-vel (with, by means of) ---
    ['Busszal megyek.', 'I\'m going by bus.', '', 'prep', 'All', 'magyarok-ch6,val-vel,transport,beginner,level-2'],
    ['Vonattal utazom.', 'I\'m traveling by train.', '', 'prep', 'All', 'magyarok-ch6,val-vel,transport,beginner,level-2'],
    ['Autóval megyek.', 'I\'m going by car.', '', 'prep', 'All', 'magyarok-ch6,val-vel,transport,beginner,level-2'],
    ['Mariával megyek.', 'I\'m going with Maria.', '', 'prep', 'All', 'magyarok-ch6,val-vel,beginner,level-2'],
    ['Péterrel beszélek.', 'I\'m talking with Péter.', '', 'prep', 'All', 'magyarok-ch6,val-vel,assimilation,beginner,level-2'],
    ['Kanállal eszem.', 'I eat with a spoon.', '', 'prep', 'All', 'magyarok-ch6,val-vel,assimilation,beginner,level-2'],
    ['A feleségemmel élek.', 'I live with my wife.', '', 'prep', 'All', 'magyarok-ch6,val-vel,beginner,level-2'],

    // --- Hány? / Mennyi? ---
    ['Hány ember van itt?', 'How many people are here?', '', 'prep', 'All', 'magyarok-ch6,hany-mennyi,question,beginner,level-2'],
    ['Mennyi idő van még?', 'How much time is left?', '', 'prep', 'All', 'magyarok-ch6,hany-mennyi,question,beginner,level-2'],
    ['Mennyibe kerül?', 'How much does it cost?', '', 'prep', 'All', 'magyarok-ch6,hany-mennyi,question,beginner,level-2'],
];

// ============================================================
// CHAPTER 8: Definite/indefinite conjugation intro, colors, clothing
// ============================================================

$ch8 = [
    // --- Definite vs indefinite concept ---
    ['Látok egy kutyát.', 'I see a dog. (indefinite)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],
    ['Látom a kutyát.', 'I see the dog. (definite)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],
    ['Beszélek magyarul.', 'I speak Hungarian. (indefinite — no object)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],
    ['Beszélem a magyart.', 'I speak the Hungarian (language). (definite)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],
    ['Rajzol egy házat.', 'He draws a house. (indefinite)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],
    ['Rajzolja a házat.', 'He draws the house. (definite)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],
    ['Látom a macskát.', 'I see the cat. (definite)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],
    ['Látok egy macskát.', 'I see a cat. (indefinite)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],
    ['Tanulom a magyart.', 'I\'m learning Hungarian. (definite)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],
    ['Embereket látok.', 'I see people. (indefinite — no article)', '', 'prep', 'All', 'magyarok-ch8,def-indef-intro,beginner,level-2'],

    // --- Colors ---
    ['Kék', 'blue', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Piros', 'red', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Fekete', 'black', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Fehér', 'white', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Szürke', 'gray', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Sárga', 'yellow', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Zöld', 'green', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Barna', 'brown', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Lila', 'purple', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Rózsaszín', 'pink', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Narancssárga', 'orange', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Világoskék', 'light blue', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],
    ['Sötétkék', 'dark blue', '', 'prep', 'All', 'magyarok-ch8,color,beginner,level-2'],

    // --- Clothing ---
    ['Kabát', 'coat', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Ing', 'shirt', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Nadrág', 'trousers / pants', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Szoknya', 'skirt', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Öltöny', 'suit (men)', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Kosztüm', 'suit (women)', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Zokni', 'socks', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Pizsama', 'pajamas', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Dzseki', 'jacket', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Mellény', 'vest', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],

    // --- Shopping sentences ---
    ['Van kabátod?', 'Do you have a coat?', '', 'prep', 'All', 'magyarok-ch8,clothing,question,beginner,level-2'],
    ['Igen, van kabátom.', 'Yes, I have a coat.', '', 'prep', 'All', 'magyarok-ch8,clothing,beginner,level-2'],
    ['Van piros kabátod?', 'Do you have a red coat?', '', 'prep', 'All', 'magyarok-ch8,clothing,color,beginner,level-2'],
    ['Nem, nincs lila kabátom.', 'No, I don\'t have a purple coat.', '', 'prep', 'All', 'magyarok-ch8,clothing,color,negation,beginner,level-2'],
    ['Mennyibe kerül ez a kabát?', 'How much does this coat cost?', '', 'prep', 'All', 'magyarok-ch8,shopping,question,beginner,level-2'],
    ['A kabát huszonkilencezer-kilencszázkilencven forintba kerül.', 'The coat costs 29,990 forints.', '', 'prep', 'All', 'magyarok-ch8,shopping,number,beginner,level-2'],
    ['Egy üveg vörösbor hétezer-ötszáz forintba kerül.', 'A bottle of red wine costs 7,500 forints.', '', 'prep', 'All', 'magyarok-ch8,shopping,number,beginner,level-2'],
];

// ============================================================
// CHAPTER 9: Question words, biography structure, occupations
// ============================================================

$ch9 = [
    // --- Question words (kérdőszavak) ---
    ['Ki?', 'Who?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Mi?', 'What?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Hol?', 'Where?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Hova?', 'Where to?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Mikor?', 'When?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Miért?', 'Why?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Hogyan?', 'How?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Hány?', 'How many?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Mennyi?', 'How much?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Milyen?', 'What kind of? / What is ... like?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],
    ['Melyik?', 'Which one?', '', 'prep', 'All', 'magyarok-ch9,question-word,beginner,level-2'],

    // --- Occupations ---
    ['Asztalos', 'carpenter / furniture maker', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Óvónő', 'kindergarten teacher (female)', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Könyvtáros', 'librarian', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Főszakács', 'chef', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Pincér', 'waiter', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Postás', 'mailman', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Bolti eladó', 'shop assistant', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Szobafestő', 'painter and decorator', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Gazdálkodó', 'farmer', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Cukrász', 'pastry cook / confectioner', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Ápolónő', 'nurse', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Varrónő', 'seamstress', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Kozmetikus', 'beautician', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],
    ['Informatikus', 'IT professional', '', 'prep', 'All', 'magyarok-ch9,occupation,beginner,level-2'],

    // --- Biography Q&A pattern ---
    ['Hogy hívják?', 'What is your name?', '', 'prep', 'All', 'magyarok-ch9,biography,question,beginner,level-2'],
    ['Mi a foglalkozása?', 'What is your occupation?', '', 'prep', 'All', 'magyarok-ch9,biography,question,beginner,level-2'],
    ['Milyen színű a szeme?', 'What color are your eyes?', '', 'prep', 'All', 'magyarok-ch9,biography,question,beginner,level-2'],
    ['A szemem kék.', 'My eyes are blue.', '', 'prep', 'All', 'magyarok-ch9,biography,beginner,level-2'],
    ['Kék szemű vagyok.', 'I have blue eyes.', '', 'prep', 'All', 'magyarok-ch9,biography,beginner,level-2'],
    ['Barna hajam van.', 'I have brown hair.', '', 'prep', 'All', 'magyarok-ch9,biography,beginner,level-2'],
    ['Barna hajú vagyok.', 'I am brown-haired.', '', 'prep', 'All', 'magyarok-ch9,biography,beginner,level-2'],
    ['Mit csinál a munkahelyén?', 'What do you do at your workplace?', '', 'prep', 'All', 'magyarok-ch9,biography,question,beginner,level-2'],
    ['Segít.', 'He/she helps.', '', 'prep', 'All', 'magyarok-ch9,verb,beginner,level-2'],
    ['Embereket gyógyítok.', 'I heal people.', '', 'prep', 'All', 'magyarok-ch9,biography,sentence,beginner,level-2'],
];

// Insert all phrases
$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags, import_batch) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");

foreach (array_merge($ch2, $ch3, $ch4, $ch5, $ch6, $ch7, $ch8, $ch9) as $r) {
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
$allCount = count($ch2) + count($ch3) + count($ch4) + count($ch5) + count($ch6) + count($ch7) + count($ch8) + count($ch9);
echo "<p><strong>Ch2: " . count($ch2) . " | Ch3: " . count($ch3) . " | Ch4: " . count($ch4) . " | Ch5: " . count($ch5) . " | Ch6: " . count($ch6) . " | Ch7: " . count($ch7) . " | Ch8: " . count($ch8) . " | Ch9: " . count($ch9) . " | Total: $allCount phrases</strong></p>";
echo "<p>Tagged: <code>magyarok-chN</code> + grammar topic + <code>beginner</code> + <code>level-1/2/3</code></p>";
echo "<p>Level 1 = basics (van, locations, questions) | Level 2 = time, colors, clothing, occupations, questions | Level 3 = definite/indefinite conjugation</p>";
echo "<p>Safe to re-run.</p>";
?>
