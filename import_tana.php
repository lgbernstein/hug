<?php
// Tana → Hug MySQL Import Script
// Imports interview Q&A, vocabulary, facts/history/culture, and knowledge cards
// Safe to re-run: uses INSERT IGNORE / ON DUPLICATE KEY UPDATE
// Data sourced from Larry's Tana workspace on 2026-03-29

session_start();
$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$results = [];
$counts = ['interview' => 0, 'vocab' => 0, 'knowledge' => 0, 'facts_phrases' => 0];

// Ensure tags column exists
$conn->query("ALTER TABLE hungarian_prep ADD COLUMN IF NOT EXISTS tags TEXT AFTER `who`");
$conn->query("ALTER TABLE hungarian_prep ADD COLUMN IF NOT EXISTS import_batch VARCHAR(100) AFTER tags");

$batch = 'tana_2026-03-29';

// ============================================================
// 1. INTERVIEW Q&A → hungarian_prep
//    Source: Tana "Hungarian Citizenship Interview Cheat Sheet" (qh6CW3jbwoYF)
//    + Tagged Hungarian Interview Answer/Question nodes
// ============================================================

$interview = [
    // [question_hu, answer_en, answer_hu, category, who, tags]

    // --- Personal Info ---
    ['Hogy hívják?', 'What is your name?', 'Larry Bernsteinnek hívnak.', 'interview', 'Larry', 'interview,essential,personal-info'],
    ['Hol született?', 'Where were you born?', 'Los Angelesben születtem.', 'interview', 'Larry', 'interview,essential,personal-info'],
    ['Mikor született?', 'When were you born?', '1957. november 7-én születtem.', 'interview', 'Larry', 'interview,essential,personal-info'],
    ['Házas?', 'Are you married?', 'Igen, házas vagyok. A feleségem Maria.', 'interview', 'Larry', 'interview,essential,personal-info'],

    // --- Family ---
    ['Az édesanyját hogy hívják?', 'What is your mother\'s name?', 'Marlene-nek hívják.', 'interview', 'Larry', 'interview,essential,family'],
    ['Az édesapját hogy hívják?', 'What is your father\'s name?', 'Robertnek hívják.', 'interview', 'Larry', 'interview,essential,family'],
    ['Az édesanyja hol született?', 'Where was your mother born?', 'Chicagóban született.', 'interview', 'Larry', 'interview,family'],
    ['Az édesapja hol született?', 'Where was your father born?', 'New Yorkban született.', 'interview', 'Larry', 'interview,family'],
    ['Van testvére?', 'Do you have siblings?', 'Igen, van két gyerekem. Tev 1998-ban született, Hannah 2000-ben született.', 'interview', 'Larry', 'interview,family'],

    // --- Hungarian Origin ---
    ['Magyar származású?', 'Are you of Hungarian origin?', 'Igen, magyar származású vagyok.', 'interview', 'All', 'interview,essential,origin'],
    ['Melyik felmenője volt magyar?', 'Which ancestor was Hungarian?', 'A nagyapám, Edward Bernstein magyar volt.', 'interview', 'Larry', 'interview,essential,origin'],
    ['Hol született a nagyapja?', 'Where was your grandfather born?', 'A nagyapám Polenában született 1901-ben. Polena Magyarország része volt Trianon előtt.', 'interview', 'Larry', 'interview,essential,origin,history'],
    ['Mikor emigrált a nagyapja?', 'When did your grandfather emigrate?', '1920-ban emigrált Amerikába.', 'interview', 'Larry', 'interview,essential,origin,history'],
    ['Le tudja vezetni magyar származását?', 'Can you trace your Hungarian origin?', 'Az apai nagyapám, Bernstein Edward magyar volt. Polenában született 1901-ben. 1920-ban emigrált Amerikába.', 'interview', 'Larry', 'interview,essential,origin'],
    ['Tudja, miért emigrált a nagyapja Amerikába?', 'Do you know why your grandfather emigrated to America?', 'Trianon és az antiszemitizmus miatt, mert zsidó volt.', 'interview', 'Larry', 'interview,essential,origin,history'],

    // --- Where You Live ---
    ['Most hol él?', 'Where do you live now?', 'Most Laguna Niguelben élek, Kaliforniában.', 'interview', 'Larry', 'interview,essential,personal-info'],
    ['Jelenleg hol lakik?', 'Where do you currently reside?', 'Laguna Niguelben lakom.', 'interview', 'Larry', 'interview,personal-info'],
    ['Hol van Laguna Niguel?', 'Where is Laguna Niguel?', 'Kaliforniában, Los Angeles közelében.', 'interview', 'Larry', 'interview,personal-info'],

    // --- Profession ---
    ['Mi a foglalkozása?', 'What is your profession?', 'Orvos voltam, de most nyugdíjas vagyok.', 'interview', 'Larry', 'interview,essential,profession'],
    ['Hol dolgozik?', 'Where do you work?', 'Most nem dolgozom, nyugdíjas vagyok.', 'interview', 'Larry', 'interview,profession'],
    ['Mi volt a szülei foglalkozása?', 'What was your parents\' profession?', 'Az apám ügyvéd volt. Az anyám háziasszony volt.', 'interview', 'Larry', 'interview,profession'],

    // --- Education ---
    ['Hol végezte tanulmányait?', 'Where did you finish your studies?', 'A UCLA egyetemen végeztem.', 'interview', 'Larry', 'interview,education'],
    ['Mikor végezte a tanulmányait?', 'When did you finish your studies?', '1983-ban.', 'interview', 'Larry', 'interview,education'],

    // --- Hungarian Language ---
    ['Mióta tanul magyarul?', 'Since when do you study Hungarian?', '2025 júliusa óta tanulok magyarul.', 'interview', 'All', 'interview,essential,language'],
    ['Milyen fokon beszél magyarul?', 'What level Hungarian do you speak?', 'Alapfokon beszélek magyarul.', 'interview', 'All', 'interview,language'],
    ['Melyik nyelviskolában tanult?', 'Which language school?', 'Hungarian Solutions-nél tanulok.', 'interview', 'All', 'interview,language'],
    ['Szeret magyarul tanulni?', 'Do you like learning Hungarian?', 'Igen, nagyon szeretem. Nehéz, de szeretem.', 'interview', 'All', 'interview,language'],

    // --- Budapest & Hungary ---
    ['Járt már Magyarországon?', 'Have you been to Hungary?', 'Igen, voltam Budapesten.', 'interview', 'All', 'interview,essential,budapest'],
    ['Ön járt már Magyarországon?', 'Have you been to Hungary? (formal)', 'Igen, voltam Budapesten 2025 decemberében.', 'interview', 'All', 'interview,essential,budapest'],
    ['Mikor járt Budapesten?', 'When were you in Budapest?', '2025 decemberében voltam Budapesten.', 'interview', 'All', 'interview,budapest'],
    ['Hogy tetszik Budapest?', 'How do you like Budapest?', 'Nagyon tetszik Budapest. Gyönyörű város. Szép utcák, kedves emberek, finom ételek.', 'interview', 'All', 'interview,budapest'],
    ['Mit látott Budapesten?', 'What did you see in Budapest?', 'Az Operaházat láttam. A Szent István Bazilikát. A Dunát és a Parlamentet. A hidakat. A karácsonyi vásárt.', 'interview', 'All', 'interview,budapest'],
    ['Mit látott Magyarországon?', 'What did you see in Hungary?', 'Budapestet láttam. A Parlamentet, a Bazilikát, a Dunát, a hidakat és a karácsonyi vásárt.', 'interview', 'All', 'interview,budapest'],
    ['Mi tetszett Magyarországon?', 'What did you like in Hungary?', 'Az ételek, a kávéházak, az épületek és a kedves emberek.', 'interview', 'All', 'interview,budapest'],
    ['Milyen magyar ételeket szeret?', 'What Hungarian foods do you like?', 'A rántott húst szeretem. A kürtőskalácsot. A csirkepaprikást.', 'interview', 'All', 'interview,food'],
    ['Beteg volt?', 'Were you sick?', 'Igen, sajnos beteg voltam, ezért nem láttunk mindent.', 'interview', 'All', 'interview,budapest'],
    ['Melyik tetszett jobban: Budapest vagy Győr?', 'Which did you like better: Budapest or Győr?', 'Csak Budapesten voltam, de nagyon tetszett.', 'interview', 'All', 'interview,budapest'],
    ['Tud főzni magyar ételeket?', 'Can you cook Hungarian dishes?', 'Nem, de szeretem a magyar ételeket.', 'interview', 'All', 'interview,food'],

    // --- Motivation ---
    ['Miért szeretne magyar állampolgár lenni?', 'Why do you want to be a Hungarian citizen?', 'Mert büszke vagyok a magyar származásomra. Nagyon tetszik Budapest és Magyarország.', 'interview', 'All', 'interview,essential,motivation'],
    ['Miért jött ma ide?', 'Why did you come here today?', 'Szeretnék magyar állampolgárságot kérelmezni.', 'interview', 'All', 'interview,essential,motivation'],
    ['Miért kér állampolgárságot?', 'Why are you applying for citizenship?', 'Szeretném megőrizni a családi hagyományt.', 'interview', 'All', 'interview,essential,motivation'],
    ['Szeretnék magyar állampolgár lenni, mert szeretném megőrizni a családom kultúráját.', 'I would like to become a Hungarian citizen because I want to preserve my family\'s culture.', 'Szeretnék magyar állampolgár lenni, mert szeretném megőrizni a családom kultúráját.', 'interview', 'All', 'interview,essential,motivation,best-answer'],
    ['Büszke vagyok a származásomra.', 'I am proud of my heritage.', 'Büszke vagyok a származásomra.', 'interview', 'All', 'interview,essential,motivation,best-answer'],
    ['Szeretnék többet utazni Magyarországra.', 'I would like to travel more to Hungary.', 'Szeretnék többet utazni Magyarországra.', 'interview', 'All', 'interview,motivation,best-answer'],
    ['Szeretném jobban ismerni a magyar kultúrát.', 'I would like to know Hungarian culture better.', 'Szeretném jobban ismerni a magyar kultúrát.', 'interview', 'All', 'interview,motivation'],
    ['Szeretném átadni a gyerekeimnek ezt az örökséget.', 'I would like to pass this heritage on to my children.', 'Szeretném átadni a gyerekeimnek ezt az örökséget.', 'interview', 'All', 'interview,motivation,best-answer'],

    // --- Origin best answers ---
    ['A nagyapám Trianon és az antiszemitizmus miatt emigrált Amerikába. Zsidó volt.', 'My grandfather emigrated to America because of Trianon and antisemitism. He was Jewish.', 'A nagyapám Trianon és az antiszemitizmus miatt emigrált Amerikába. Zsidó volt.', 'interview', 'Larry', 'interview,essential,origin,best-answer'],
    ['Trianon után a faluja már nem volt Magyarország része.', 'After Trianon, his village was no longer part of Hungary.', 'Trianon után a faluja már nem volt Magyarország része.', 'interview', 'Larry', 'interview,essential,origin,best-answer'],

    // --- Interview Phrases ---
    ['Jó napot kívánok!', 'Good day! (safest greeting)', 'Jó napot kívánok!', 'interview', 'All', 'interview,essential,greeting'],
    ['Állampolgársági interjúra jöttem.', 'I came for a citizenship interview.', 'Állampolgársági interjúra jöttem.', 'interview', 'All', 'interview,essential'],
    ['Izgul? / Ideges?', 'Are you nervous?', 'Persze, nagyon izgulok.', 'interview', 'All', 'interview'],
    ['Hogy utazott?', 'How did you travel?', 'Autóval jöttem.', 'interview', 'All', 'interview'],
    ['Itt van az útlevele?', 'Do you have your passport?', 'Igen, itt van. Parancsoljon.', 'interview', 'All', 'interview'],
    ['Megkínálhatom egy teával?', 'Can I offer you tea?', 'Igen, köszönöm. / Nem, köszönöm.', 'interview', 'All', 'interview'],
    ['Van kérdése?', 'Do you have a question?', 'Nem, nincs kérdésem.', 'interview', 'All', 'interview,essential'],
    ['Elismételné legyen szíves lassabban?', 'Could you please repeat it more slowly?', 'Elismételné legyen szíves lassabban?', 'interview', 'All', 'interview,useful-phrase'],

    // --- Documents ---
    ['Milyen dokumentumokat hozott magával?', 'What documents did you bring?', 'Az útlevelemet. A jelentkezési lapot. A születési anyakönyvi kivonatomat. És a lefordított dokumentumokat.', 'interview', 'All', 'interview,documents'],
    ['Kitöltötte a kérelmet?', 'Did you fill out the application?', 'Igen, kitöltöttem.', 'interview', 'All', 'interview,documents'],
    ['Lefordíttatta a dokumentumait?', 'Did you have your documents translated?', 'Igen, le vannak fordítva.', 'interview', 'All', 'interview,documents'],

    // --- Closing ---
    ['Köszönöm, hogy eljött!', 'Thank you for coming.', 'Köszönöm szépen!', 'interview', 'All', 'interview,closing'],
    ['Körülbelül 6 hónap múlva jelentkezünk.', 'We\'ll contact you in about 6 months.', 'Köszönöm.', 'interview', 'All', 'interview,closing'],
    ['Gratulálok! Ön nemsokára magyar állampolgár lesz!', 'Congratulations! You\'ll soon be a Hungarian citizen!', 'Nagyon köszönöm!', 'interview', 'All', 'interview,closing'],

    // --- Weather & Greetings ---
    ['Szép időnk van ma!', 'We have nice weather today!', 'Igen, kellemes. / Igen, süt a nap.', 'interview', 'All', 'interview,greetings'],
    ['Mit szól, már megint esik az eső!', 'It\'s raining again!', 'Igen, mindig esik Kaliforniában.', 'interview', 'All', 'interview,greetings'],

    // --- Colors ---
    ['Mi a kedvenc színe?', 'What\'s your favorite color?', 'A kedvenc színem a kék.', 'interview', 'All', 'interview,colors'],
    ['Milyen színű a mappája?', 'What color is your folder?', 'A mappám kék.', 'interview', 'All', 'interview,colors'],
    ['Milyen színű a magyar zászló?', 'What colour is the Hungarian flag?', 'Piros, fehér és zöld.', 'interview', 'All', 'interview,colors,flag'],
    ['Milyen színű a magyar útlevél?', 'What colour is the Hungarian passport?', 'Sötétkék.', 'interview', 'All', 'interview,colors'],

    // --- Time ---
    ['Hány óra van most?', 'What time is it now?', '(check the clock)', 'interview', 'All', 'interview,time'],
    ['Mennyi az idő?', 'What time is it?', '(check the clock)', 'interview', 'All', 'interview,time'],

    // --- Hobbies & Personal ---
    ['Mit szeret sportolni?', 'What sports do you like?', 'Szeretek teniszezni.', 'interview', 'Larry', 'interview,hobbies'],
    ['Szeretek a számítógépen dolgozni és teniszezni a legjobban.', 'I like working on the computer and playing tennis the most.', 'Szeretek a számítógépen dolgozni és teniszezni a legjobban.', 'interview', 'Larry', 'interview,hobbies'],
    ['Akciófilmeket, komédiákat és kungfu filmeket nézek.', 'I watch action movies, comedies, and kung fu films.', 'Akciófilmeket, komédiákat és kungfu filmeket nézek.', 'interview', 'Larry', 'interview,hobbies'],

    // --- Questions you'll hear (question-only, for listening practice) ---
    ['Ki szeretne bemutatkozni?', 'Who would like to introduce themselves?', '', 'interview', 'All', 'interview,question'],
    ['Milyen gyakran?', 'How often?', '', 'interview', 'All', 'interview,question'],
    ['Ki járt már Magyarországon?', 'Who has been to Hungary?', '', 'interview', 'All', 'interview,question'],

    // --- Budapest-specific statements ---
    ['Budapesten voltam, de sajnos beteg voltam.', 'I was in Budapest, but unfortunately I was sick.', 'Budapesten voltam, de sajnos beteg voltam.', 'interview', 'All', 'interview,budapest'],
    ['Szeretem a gulyást, de a kedvencem a rántott hús.', 'I like goulash, but my favorite is schnitzel.', 'Szeretem a gulyást, de a kedvencem a rántott hús.', 'interview', 'All', 'interview,food'],
    ['Mit tud Magyarországról?', 'What do you know about Hungary?', 'Budapest szép. Az ételek finomak. Sok kávézó és étterem van. Az épületek nagyon szépek mindenhol.', 'interview', 'All', 'interview,budapest'],
    ['Piros-fehér-zöld, ez a magyar föld', 'Red, white, green — this is Hungarian land', 'Piros-fehér-zöld, ez a magyar föld', 'interview', 'All', 'interview,flag,culture,essential'],
];

$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags, import_batch) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), category=VALUES(category), tags=VALUES(tags)");
foreach ($interview as $r) {
    $stmt->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $batch);
    $stmt->execute();
    $counts['interview']++;
}
$stmt->close();
$results[] = "Interview Q&A: {$counts['interview']} processed";


// ============================================================
// 2. VOCABULARY & EXPRESSIONS → hungarian_prep
//    Source: Tana "Hungarian Study Phrases" (dR8LEFCqD3YY)
// ============================================================

$vocab = [
    // [question_hu, answer_en, answer_hu, category, who, tags]

    // --- Core Verbs & Expressions ---
    ['imádok', 'I adore', '', 'prep', 'All', 'verb,expression,tana-vocab'],
    ['szeretek utazni', 'I like travelling', '', 'prep', 'All', 'verb,expression,tana-vocab'],
    ['szeretek főzni', 'I like cooking', '', 'prep', 'All', 'verb,expression,tana-vocab'],
    ['szeretek magyarul tanulni', 'I like learning Hungarian', '', 'prep', 'All', 'verb,expression,tana-vocab'],
    ['nehéz, de szeretek magyarul tanulni', 'It\'s hard, but I like learning Hungarian', '', 'prep', 'All', 'expression,interview-useful,tana-vocab'],
    ['nem sokat beszéltem magyarul, de sokat értek', 'I didn\'t speak much Hungarian, but I understand a lot', '', 'prep', 'All', 'expression,interview-useful,tana-vocab'],
    ['mindenki nagyon kedves', 'everyone is very kind', '', 'prep', 'All', 'expression,tana-vocab'],
    ['természetesen', 'naturally', '', 'prep', 'All', 'adverb,tana-vocab'],
    ['rendszeresen', 'regularly', '', 'prep', 'All', 'adverb,tana-vocab'],
    ['persze', 'of course', '', 'prep', 'All', 'expression,tana-vocab'],
    ['szerény', 'modest', '', 'prep', 'All', 'adjective,tana-vocab'],

    // --- Personal Information ---
    ['nincs gyerekem', 'I don\'t have any kids', '', 'prep', 'All', 'personal,expression,tana-vocab'],
    ['házas vagyok', 'I\'m married', '', 'prep', 'All', 'personal,expression,tana-vocab'],
    ['kettős állampolgár', 'dual citizen', '', 'prep', 'All', 'personal,expression,tana-vocab'],
    ['amerikai és ausztrál állampolgár vagyok', 'I\'m an American and Australian citizen', '', 'prep', 'Maria', 'personal,expression,tana-vocab'],
    ['pszichológus vagyok', 'I\'m a psychologist', '', 'prep', 'Maria', 'profession,tana-vocab'],
    ['kórházban dolgozom', 'I work in a hospital', '', 'prep', 'All', 'profession,tana-vocab'],
    ['egy étteremben dolgozom', 'I work in a restaurant', '', 'prep', 'All', 'profession,tana-vocab'],
    ['vegyészmérnök', 'chemical engineer', '', 'prep', 'All', 'profession,tana-vocab'],
    ['pénzügyi igazgató', 'financial director', '', 'prep', 'All', 'profession,tana-vocab'],

    // --- Time & Frequency ---
    ['nyáron', 'in the summer', '', 'prep', 'All', 'time,tana-vocab'],
    ['egy évben háromszor', 'three times a year', '', 'prep', 'All', 'time,frequency,tana-vocab'],
    ['kétszer egy évben', 'twice a year', '', 'prep', 'All', 'time,frequency,tana-vocab'],
    ['hat hónapja', 'for six months', '', 'prep', 'All', 'time,tana-vocab'],
    ['január 6-án interjúztam', 'I did my interview on January 6', '', 'prep', 'All', 'time,interview-useful,tana-vocab'],
    ['március negyedikén', 'on March 4', '', 'prep', 'All', 'time,tana-vocab'],

    // --- Places & Geography ---
    ['Erdély', 'Transylvania', '', 'prep', 'All', 'place,geography,tana-vocab'],
    ['falu', 'village', '', 'prep', 'All', 'noun,geography,tana-vocab'],
    ['sziget', 'island', '', 'prep', 'All', 'noun,geography,tana-vocab'],
    ['a Duna folyó', 'the Danube River', '', 'prep', 'All', 'place,geography,essential,tana-vocab'],
    ['Margit sziget', 'Margaret Island', '', 'prep', 'All', 'place,budapest,tana-vocab'],
    ['Hősök tere', 'Heroes\' Square', '', 'prep', 'All', 'place,budapest,tana-vocab'],
    ['Szabadság híd', 'Liberty Bridge', '', 'prep', 'All', 'place,budapest,tana-vocab'],
    ['Nyugati pályaudvar', 'Nyugati Train Station', '', 'prep', 'All', 'place,budapest,tana-vocab'],
    ['Széchenyi fürdő', 'Széchenyi Baths', '', 'prep', 'All', 'place,budapest,tana-vocab'],
    ['Gellért hegy', 'Gellért Hill', '', 'prep', 'All', 'place,budapest,tana-vocab'],
    ['Országház', 'Parliament', '', 'prep', 'All', 'place,budapest,essential,tana-vocab'],
    ['Bazilika', 'Basilica', '', 'prep', 'All', 'place,budapest,tana-vocab'],
    ['Hollandia', 'the Netherlands', '', 'prep', 'All', 'place,geography,tana-vocab'],

    // --- Food & Culture ---
    ['csirkepaprikás', 'chicken paprikash', '', 'prep', 'All', 'food,tana-vocab'],
    ['töltött káposzta', 'stuffed cabbage', '', 'prep', 'All', 'food,tana-vocab'],
    ['báránypörkölt', 'lamb stew', '', 'prep', 'All', 'food,tana-vocab'],
    ['kocsonya', 'aspic', '', 'prep', 'All', 'food,tana-vocab'],
    ['kürtőskalács', 'chimney cake', '', 'prep', 'All', 'food,tana-vocab'],
    ['só', 'salt', '', 'prep', 'All', 'food,noun,tana-vocab'],
    ['sós', 'salty', '', 'prep', 'All', 'food,adjective,tana-vocab'],
    ['fahéj', 'cinnamon', '', 'prep', 'All', 'food,noun,tana-vocab'],
    ['édes', 'sweet', '', 'prep', 'All', 'food,adjective,tana-vocab'],

    // --- Useful Adjectives & Nouns ---
    ['hangsúly', 'intonation', '', 'prep', 'All', 'noun,tana-vocab'],
    ['ékezet', 'accent (diacritic)', '', 'prep', 'All', 'noun,tana-vocab'],
    ['hivatalos', 'official', '', 'prep', 'All', 'adjective,tana-vocab'],
    ['hivatalosan', 'officially', '', 'prep', 'All', 'adverb,tana-vocab'],
    ['főváros', 'capital', '', 'prep', 'All', 'noun,geography,tana-vocab'],
    ['építészet', 'architecture', '', 'prep', 'All', 'noun,tana-vocab'],
    ['szobor', 'statue', '', 'prep', 'All', 'noun,tana-vocab'],
    ['tó', 'lake', '', 'prep', 'All', 'noun,geography,tana-vocab'],
    ['igaz', 'true', '', 'prep', 'All', 'adjective,tana-vocab'],
    ['hamis', 'false', '', 'prep', 'All', 'adjective,tana-vocab'],

    // --- Fixed Phrases ---
    ['Még mindig fiatal vagy', 'You\'re still young', '', 'prep', 'All', 'phrase,tana-vocab'],
    ['minden magyar gyerek ismeri', 'every Hungarian child knows this', '', 'prep', 'All', 'phrase,tana-vocab'],
    ['minden jó lett', 'everything turned out well', '', 'prep', 'All', 'phrase,tana-vocab'],

    // --- Historical dates as drillable phrases ---
    ['895: Honfoglalás', '895: The Conquest of the Carpathian Basin', 'A magyarok bejöttek a Kárpát-medencébe Árpád vezetésével.', 'prep', 'All', 'history,date,tana-vocab'],
    ['1000: Államalapítás', '1000: Foundation of the State — King St. Stephen', 'Szent István királyt 1000-ben koronázták meg.', 'prep', 'All', 'history,date,critical,tana-vocab'],
    ['1848: Forradalom', '1848: Revolution against the Habsburgs', 'Az 1848-as forradalom a Habsburg uralom ellen volt.', 'prep', 'All', 'history,date,tana-vocab'],
    ['1920: Trianoni békeszerződés', '1920: Treaty of Trianon', 'Magyarország elveszítette területének kétharmadát.', 'prep', 'All', 'history,date,critical,tana-vocab'],
    ['1956: Forradalom', '1956: Revolution against the Soviets', 'Az 1956-os forradalom a szovjet uralom ellen volt. Október 23. nemzeti ünnep.', 'prep', 'All', 'history,date,critical,tana-vocab'],
];

$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags, import_batch) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");
foreach ($vocab as $r) {
    $stmt->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $batch);
    $stmt->execute();
    $counts['vocab']++;
}
$stmt->close();
$results[] = "Vocabulary & expressions: {$counts['vocab']} processed";


// ============================================================
// 3. KNOWLEDGE CARDS → knowledge_cards table
//    Source: Tana "Hungarian Facts-History-Food" tagged nodes (iRh-s8a8gdZv)
// ============================================================

$knowledge = [
    // [category, title_hu, title_en, content_hu, content_en, key_fact, tags, who]
    ['geography', 'Duna', 'Danube', 'A Duna Magyarország leghosszabb folyója, és Budapest mellett folyik el, elválasztva Budát és Pestet.', 'The Danube is the longest river in Hungary and runs through Budapest, dividing it into Buda and Pest.', 'Longest river in Hungary, divides Budapest', 'facts,geography,critical', 'All'],
    ['geography', 'Budapest', 'Budapest', 'Budapest Magyarország fővárosa és legnagyobb városa, 1873-ban jött létre Buda, Óbuda és Pest egyesítésével.', 'Budapest is the capital and largest city of Hungary, formed in 1873 by the union of Buda, Óbuda, and Pest.', 'Capital, formed 1873', 'facts,geography,critical', 'All'],
    ['geography', 'Tisza', 'Tisza', 'A Tisza Magyarország második nagy folyója, az Alföldön folyik át.', 'The Tisza is the second major river of Hungary, running through the Great Plain.', 'Second major river', 'facts,geography,important', 'All'],
    ['geography', 'Balaton', 'Lake Balaton', 'A Balaton Közép-Európa legnagyobb tava, népszerű nyári üdülőhely.', 'Central Europe\'s largest lake, a popular summer resort.', 'Largest lake in Central Europe', 'facts,geography,important', 'All'],
    ['geography', 'Tokaj', 'Tokaj', 'Tokaj világhírű borvidék, az aszúbor hazája.', 'World-famous wine region, home of Aszú wine.', 'Famous wine region', 'facts,geography', 'All'],
    ['geography', 'Alföld', 'The Great Plain', 'Az Alföld Magyarország legnagyobb síksága, a puszta és a hortobágyi pásztorkodás hazája.', 'The Great Plain, home to the Puszta and Hortobágy pastoral traditions.', 'Largest plain', 'facts,geography', 'All'],
    ['government', 'Magyar Parlament', 'Hungarian Parliament', 'A Magyar Parlament az egyik legnagyobb parlamenti épület a világon, a Duna partján áll Budapesten.', 'The Hungarian Parliament building is one of the world\'s largest parliament buildings, located on the Danube in Budapest.', 'One of world\'s largest parliament buildings', 'facts,government,critical', 'All'],
    ['government', 'Alaptörvény', 'Constitution / Fundamental Law', 'Magyarország alkotmányát Alaptörvénynek hívják, amelyet 2011-ben fogadtak el.', 'Hungary\'s constitution is called the Alaptörvény (Fundamental Law), adopted in 2011.', 'Adopted 2011', 'facts,government,important', 'All'],
    ['government', 'Magyarország miniszterelnöke', 'Prime Minister of Hungary', 'Magyarország miniszterelnöke Orbán Viktor.', 'The prime minister of Hungary is Orbán Viktor.', 'Orbán Viktor', 'facts,government,critical', 'All'],
    ['government', 'Magyarország köztársasági elnöke', 'President of Hungary', 'Magyarország köztársasági elnöke Sulyok Tamás.', 'The president of the Republic of Hungary is Sulyok Tamás.', 'Sulyok Tamás', 'facts,government,critical', 'All'],
    ['government', 'Budapest főpolgármestere', 'Mayor of Budapest', 'Budapest főpolgármestere Karácsony Gergely.', 'The mayor of Budapest is Karácsony Gergely.', 'Karácsony Gergely', 'facts,government,important', 'All'],
    ['history', 'Szent István király', 'King Stephen I', 'I. István volt Magyarország első királya, akit 1000-ben karácsonykor koronáztak meg. Ő keresztényesítette Magyarországot. Augusztus 20. az ünnepe, egyben nemzeti ünnep.', 'Stephen I was Hungary\'s first king, crowned on Christmas Day 1000 AD. He Christianized Hungary. August 20 is his feast day and a national holiday.', 'First king, crowned 1000 AD, Aug 20 national holiday', 'facts,history,critical', 'All'],
    ['history', 'Trianon (1920)', 'Treaty of Trianon', 'Az 1920-as trianoni békeszerződés jelentősen csökkentette Magyarország területét az első világháború után. Az ország területének körülbelül kétharmadát elveszítette.', 'The 1920 Treaty of Trianon significantly reduced Hungary\'s territory after WWI. Hungary lost about two-thirds of its land.', '1920, lost 2/3 of territory', 'facts,history,critical', 'All'],
    ['history', '1956-os forradalom', '1956 Hungarian Revolution', 'Az 1956-os forradalom a szovjet uralom elleni felkelés volt, amelyet szovjet erők levertek. Október 23. ma nemzeti ünnep.', 'The 1956 Hungarian Revolution was an uprising against Soviet rule, crushed by Soviet forces. October 23 is now a national holiday.', 'Oct 23 national holiday', 'facts,history,critical', 'All'],
    ['culture', 'Gulyás', 'Goulash', 'A gulyás Magyarország leghíresebb étele — egy laktató leves/pörkölt marhahúsból, zöldségekből és paprikából.', 'Goulash is Hungary\'s most famous dish — a hearty soup/stew made with beef, vegetables, and paprika.', 'Most famous Hungarian dish', 'facts,food,important', 'All'],
    ['culture', 'Paprika', 'Paprika', 'A paprika Magyarország legjellegzetesebb fűszere, elengedhetetlen a magyar konyhában. Magyarország a világ vezető paprikatermelői közé tartozik.', 'Paprika is Hungary\'s most iconic spice, essential to Hungarian cuisine. Hungary is one of the world\'s top producers.', 'Most iconic spice', 'facts,food,important', 'All'],
    ['culture', 'Pálinka', 'Pálinka (fruit brandy)', 'A pálinka hagyományos magyar gyümölcspárlat, oltalom alatt álló eredetmegjelölésű ital Magyarországon termett gyümölcsből.', 'Pálinka is a traditional Hungarian fruit brandy, a protected designation of origin spirit made from fruit grown in Hungary.', 'Traditional fruit brandy', 'facts,food', 'All'],
    ['culture', 'Rubik-kocka', 'Rubik\'s Cube', 'A Rubik-kockát Rubik Ernő magyar építész találta fel 1974-ben. Ez a világ legtöbbet eladott kirakójátéka.', 'The Rubik\'s Cube was invented by Hungarian architect Ernő Rubik in 1974. It is the world\'s best-selling puzzle toy.', 'Invented 1974 by Ernő Rubik', 'facts,culture', 'All'],
];

// Ensure knowledge_cards has tags + who columns and category supports all values
$conn->query("ALTER TABLE knowledge_cards MODIFY COLUMN category VARCHAR(50) DEFAULT 'culture'");
$conn->query("ALTER TABLE knowledge_cards ADD COLUMN IF NOT EXISTS tags TEXT AFTER key_fact");
$conn->query("ALTER TABLE knowledge_cards ADD COLUMN IF NOT EXISTS `who` VARCHAR(10) DEFAULT 'All' AFTER tags");

$stmt = $conn->prepare("INSERT INTO knowledge_cards (category, title_hu, title_en, content_hu, content_en, key_fact, tags, `who`) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title_en=VALUES(title_en), content_hu=VALUES(content_hu), content_en=VALUES(content_en), key_fact=VALUES(key_fact), tags=VALUES(tags)");
foreach ($knowledge as $k) {
    $stmt->bind_param('ssssssss', $k[0], $k[1], $k[2], $k[3], $k[4], $k[5], $k[6], $k[7]);
    $stmt->execute();
    $counts['knowledge']++;
}
$stmt->close();
$results[] = "Knowledge cards: {$counts['knowledge']} processed";


// ============================================================
// 4. KNOWLEDGE → DRILLABLE PHRASES (hungarian_prep)
//    Generate practice phrases from knowledge cards
// ============================================================

$facts_phrases = [
    // [question_hu, answer_en, answer_hu, category, tags]
    ['Mi Magyarország fővárosa?', 'What is the capital of Hungary?', 'Budapest Magyarország fővárosa.', 'prep', 'facts,geography,critical'],
    ['Melyik a leghosszabb folyó Magyarországon?', 'What is the longest river in Hungary?', 'A Duna a leghosszabb folyó Magyarországon.', 'prep', 'facts,geography,critical'],
    ['Mi a Duna?', 'What is the Danube?', 'A Duna Magyarország leghosszabb folyója, elválasztja Budát és Pestet.', 'prep', 'facts,geography,critical'],
    ['Ki volt Magyarország első királya?', 'Who was Hungary\'s first king?', 'Szent István volt Magyarország első királya.', 'prep', 'facts,history,critical'],
    ['Mikor koronázták meg Szent Istvánt?', 'When was St. Stephen crowned?', '1000-ben koronázták meg.', 'prep', 'facts,history,critical'],
    ['Mi történt 1920-ban?', 'What happened in 1920?', 'A trianoni békeszerződés. Magyarország elveszítette területének kétharmadát.', 'prep', 'facts,history,critical'],
    ['Mi történt 1956-ban?', 'What happened in 1956?', 'A szovjet uralom elleni forradalom volt. Október 23. ma nemzeti ünnep.', 'prep', 'facts,history,critical'],
    ['Ki Magyarország miniszterelnöke?', 'Who is the prime minister of Hungary?', 'Orbán Viktor.', 'prep', 'facts,government,critical'],
    ['Ki Magyarország köztársasági elnöke?', 'Who is the president of Hungary?', 'Sulyok Tamás.', 'prep', 'facts,government,critical'],
    ['Ki Budapest főpolgármestere?', 'Who is the mayor of Budapest?', 'Karácsony Gergely.', 'prep', 'facts,government,important'],
    ['Mi az Alaptörvény?', 'What is the Fundamental Law?', 'Magyarország alkotmánya, amelyet 2011-ben fogadtak el.', 'prep', 'facts,government,important'],
    ['Mi a gulyás?', 'What is goulash?', 'Magyarország leghíresebb étele — leves marhahúsból és paprikából.', 'prep', 'facts,food,important'],
    ['Mi a paprika?', 'What is paprika?', 'Magyarország legjellegzetesebb fűszere, elengedhetetlen a magyar konyhában.', 'prep', 'facts,food,important'],
    ['Mi a Balaton?', 'What is Balaton?', 'Közép-Európa legnagyobb tava, népszerű nyári üdülőhely.', 'prep', 'facts,geography,important'],
    ['Mi a Tisza?', 'What is the Tisza?', 'Magyarország második nagy folyója, az Alföldön folyik át.', 'prep', 'facts,geography,important'],
    ['Augusztus 20. miért ünnep?', 'Why is August 20 a holiday?', 'Szent István ünnepe, nemzeti ünnep.', 'prep', 'facts,history,critical'],
    ['Október 23. miért ünnep?', 'Why is October 23 a holiday?', 'Az 1956-os forradalom emléknapja, nemzeti ünnep.', 'prep', 'facts,history,critical'],
    ['Ki találta fel a Rubik-kockát?', 'Who invented the Rubik\'s Cube?', 'Rubik Ernő magyar építész, 1974-ben.', 'prep', 'facts,culture'],
];

$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags, import_batch) VALUES (?, ?, ?, ?, 'All', ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");
foreach ($facts_phrases as $f) {
    $stmt->bind_param('ssssss', $f[0], $f[1], $f[2], $f[3], $f[4], $batch);
    $stmt->execute();
    $counts['facts_phrases']++;
}
$stmt->close();
$results[] = "Facts → drillable phrases: {$counts['facts_phrases']} processed";


// ============================================================
// OUTPUT
// ============================================================

$conn->close();

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Tana Import Complete</h2>";
echo "<p>Batch: <code>$batch</code></p>";
echo "<ul>";
foreach ($results as $r) {
    echo "<li>$r</li>";
}
echo "</ul>";
$total = array_sum($counts);
echo "<p><strong>Total: $total items processed</strong></p>";
echo "<p>Duplicates were updated (ON DUPLICATE KEY UPDATE). Safe to re-run.</p>";
?>
