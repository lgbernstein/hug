<?php
// Notion → Hug MySQL Import Script
// Imports grammar patterns, sentences, vocabulary, interview Q&A, citizenship words, common expressions, and history dates
// Safe to re-run: uses INSERT IGNORE / ON DUPLICATE KEY UPDATE
// Data sourced from Larry's Notion workspace on 2026-03-29

session_start();
$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$results = [];
$counts = ['grammar' => 0, 'sentences' => 0, 'vocab' => 0, 'interview' => 0, 'citizenship_words' => 0, 'expressions' => 0, 'history' => 0, 'knowledge' => 0];

// ============================================================
// 1. GRAMMAR PATTERNS → grammar_patterns table
//    Source: Notion collection a108ddfa-9490-45d3-9de2-5a56d84dc413
// ============================================================
$patterns = [
    // [pattern, suffix_words, explanation, part_of_speech, tags]
    ['Question Words', 'ki/kit/kinek…; mi/mit/minek…; melyik/melyiket…; hány, mennyi, hol/hova/honnan', 'Core Hungarian question words and their key case forms.', 'Other', 'Determiners'],
    ['Time expressions — quarter/half system', '—', 'Hungarian expresses time relative to the upcoming hour using negyed (quarter), fél (half), and háromnegyed (three-quarter).', 'Other', 'Possessive,Vowel harmony,Numbers and dates,Word order'],
    ['Afternoon time — use délután + regular time', '—', 'After 12:00, the quarter/half/three-quarter system is not used; use regular clock times with délután, reggel, este, etc.', 'Other', 'Possessive,Vowel harmony,Numbers and dates'],
    ['Possessive chains — possessor -nak/-nek + possessed suffix', '-nak/-nek + possessive endings (-ja/-je etc.)', 'For explicit possessor marking, add -nak/-nek to the possessor and apply the appropriate possessive ending to the possessed noun.', 'Noun', 'Possessive,Vowel harmony,Relations'],
    ['Ordinals', '-adik/-edik/-ödik', 'Ordinals -adik/-edik/-ödik; first irregular', 'Other', 'Numbers and dates,Case,Vowel harmony'],
    ['On the Xth of a month (-án/-én)', '-án/-én (← -a/-e + -n)', 'Ordinal day surfaces with -án/-én', 'Other', 'Numbers and dates,Case,Vowel harmony'],
    ['Numbers — counting and years', 'tíz, tizen‑; száz‑; ezer‑ patterns', 'Cardinal numbers, tens, hundreds (száz), thousands (ezer), birth years', 'Other', 'Numbers and dates,Quantifiers'],
    ['Seasons and months', '-i adjective (téli, tavaszi, nyári, őszi)', 'Map months to seasons with -i adjectives', 'Other', 'Numbers and dates,Adjectives'],
    ['Months', 'Months + ordinals', 'Months with pronunciation and context', 'Other', 'Numbers and dates,Adjectives'],
    ['Months + ordinals', '<sorszám> + hónap; hónap + <évszak>-i', 'Two-sentence month + season drill', 'Other', 'Numbers and dates,Adjectives'],
    ['Noun plurals', '-k with linking vowel -o/-e/-ö', 'Plural -k with harmony vowels', 'Noun', 'Vowel harmony,Nouns'],
    ['Allative -hoz/-hez/-höz', '-hoz/-hez/-höz', 'To or toward; harmony; -j', 'Other', 'Vowel harmony,Case,Places: direction'],
    ['Inessive -ban/-ben', '-ban/-ben', 'In/inside location; harmony choice', 'Other', 'Case,Places: location'],
    ['val/-vel assimilation', '-val/-vel', 'With/by; v assimilates after consonants', 'Postposition', 'Assimilation,Vowel harmony,Relations'],
    ['Weather adjectives', '-s/-os/-es/-ös', 'N→Adj with -s, harmony', 'Adjective', 'Vowel harmony,Adjectives'],
    ['Dates with ordinals', '-án/-én (via -a/-e + -n)', 'Dates: ordinal day plus -n', 'Other', 'Vowel harmony,Case,Numbers and dates'],
    ['Ordinal formation', '-adik/-edik/-ödik', 'Ordinals -adik/-edik/-ödik; first irregular', 'Other', 'Vowel harmony,Case,Numbers and dates'],
    ['Demonstratives + article', 'ez/az + a + N; ezek/azok + a + N‑pl', 'Demonstratives with article before nouns', 'Other', 'Word order,Determiners'],
    ['hány vs mennyi', 'hány + N(sg); mennyi + N', 'hány counts; mennyi amounts', 'Other', 'Word order,Quantifiers'],
    ['Number plus noun singular', '[Number] + N(sg)', 'After numerals, noun stays singular', 'Other', 'Word order,Quantifiers,Numbers and dates'],
    ['Possessive nouns', '-om/-em/-öm, -m; -od/-ed/-öd, -d; -a/-e, -ja/-je; -unk/-ünk, -nk; -otok/-etek/-ötök, -tok/-tek/-tök; -uk/-ük, -juk/-jük', 'Possessive endings by person, harmony', 'Noun', 'Possessive,Vowel harmony,Nouns'],
    ['Exception Nouns', '-a/-e (3sg possessive)', 'Nouns that take -a/-e (not -ja/-je) in 3rd‑person possessive.', 'Noun', 'Possessive,Nouns,Vowel harmony'],
    ['Days of the week', '-n/-on/-en/-ön; -nként', 'Days of the week: add -n/-on/-en/-ön to say "on (a day)"; use -nként for "every".', 'Noun', 'Possessive,Vowel harmony'],
    ['Important Dates in Hungarian History', 'Dates / years', 'Core Hungarian history milestones with pronunciation and quick speaking drill.', 'Other', 'Numbers and dates'],
    ['Common Prefixes (igekötők)', 'be- ki- fel- le- el- meg- oda- rá-', 'Common verb prefixes and their meanings', 'Verb', 'Verbs'],
    ['Times of the Day', 'reggel délelőtt dél délután este éjszaka éjfél', 'Times of day vocabulary', 'Other', ''],
    ['The Verb "Van"', '', 'Van/vannak usage and conjugation', 'Verb', 'Verbs'],
    ['Verb Classes - Present Tense', '', 'Present tense verb class patterns', 'Verb', 'Verbs,Vowel harmony'],
    ['Questions to include van/vannak', '', 'When van/vannak must appear in questions', 'Other', 'Verbs'],
    ['Question Words (detailed)', '', 'Comprehensive question word forms and usage', 'Other', 'Determiners'],
    ['VAN / VANNAK', '', 'Existence/location verb full reference', 'Verb', 'Verbs'],
    ['EZ / AZ / EZEK / AZOK', 'ez / az / ezek / azok', 'Demonstratives for this/that/these/those. Used with article before nouns.', 'Other', 'Determiners,Word order'],
    ['MÁR / MÉG', 'már / még', 'Contrast már vs még. már = already, már nem = no longer. még = still/yet/more.', 'Adverb', 'Numbers and dates,Word order'],
    ['Present tense endings (indefinite conjugation)', '-ek/-ök/-ok; -sz; ∅; -ünk/-unk; -tek/-tök/-tok; -nek/-nak', 'Present tense endings for indefinite conjugation with vowel harmony.', 'Verb', 'Verbs,Vowel harmony'],
    ['Personal Pronouns – Subject vs. Object Forms', 'én→engem, te→téged, ő→őt, mi→minket, ti→titeket, ők→őket', 'Subject vs. object pronoun forms and when to use each.', 'Other', 'Determiners'],
    ['Tud', 'tudok/tudsz/tud, tudunk/tudtok/tudnak; tudom/tudod/tudja, tudjuk/tudjátok/tudják', 'tud = to know (a fact/skill) or can/be able to; takes definite endings with objects.', 'Verb', 'Verbs'],
    ['Possessive Exceptions', '-ja/-je (with lengthening), exceptions → -a/-e', 'Default 3sg possessive uses -ja/-je with vowel lengthening; list highlights common exceptions.', 'Noun', 'Possessive,Nouns,Vowel harmony'],
    ['Alphabet – Compact Pronunciation Table', '', 'Hungarian alphabet with pronunciation guide', 'Other', ''],
    ['Definite vs. Indefinite — Example Table', '', 'Examples of definite vs indefinite conjugation', 'Verb', 'Verbs'],
    ['Hungarian History - basic dates and names', '', 'Key dates and figures in Hungarian history', 'Other', 'Numbers and dates'],
    ['Questions words (reference)', '', 'Quick-reference question word list', 'Other', 'Determiners'],
];

$stmt = $conn->prepare("INSERT INTO grammar_patterns (pattern, suffix_words, explanation, part_of_speech, tags) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE suffix_words=VALUES(suffix_words), explanation=VALUES(explanation), part_of_speech=VALUES(part_of_speech), tags=VALUES(tags)");
foreach ($patterns as $p) {
    $stmt->bind_param('sssss', $p[0], $p[1], $p[2], $p[3], $p[4]);
    $stmt->execute();
    $counts['grammar']++;
}
$stmt->close();
$results[] = "Grammar patterns: {$counts['grammar']} processed";

// ============================================================
// 2. INTERVIEW Q&A → hungarian_prep
//    Source: Notion collection d1cd452f-1c5f-4d23-b5a3-2c4c34f7834e
//    32 rows from "All Q&A" view
// ============================================================

// Ensure tags column exists
$col = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'tags'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE hungarian_prep ADD COLUMN tags TEXT DEFAULT NULL AFTER `who`");
}

// [question_hu, answer_en, answer_hu, category, who, tags]
$interview_qa = [
    // Formal questions with answers
    ['Kérem, mondja el, mi a foglalkozása.', 'Please tell me what your occupation is.', '[TK-confirm] a foglalkozásom.', 'interview', 'All', 'interview,formal-register,occupation'],
    ['Kérem, mondja el, mi az édesanyja leánykori neve.', "Please tell me your mother's maiden name.", 'Az édesanyám leánykori neve [TK-confirm].', 'interview', 'All', 'interview,formal-register,family'],
    ['Kérem, mondja el, miért szeretne magyar állampolgár lenni.', 'Please tell me why you would like to become a Hungarian citizen.', 'Magyar állampolgár szeretnék lenni, mert magyar származású vagyok, és szeretném megőrizni a családi kötődést és a nyelvet.', 'interview', 'All', 'interview,formal-register,origins'],
    ['Kérem, mondja el, milyen gyakran beszél magyarul.', 'Please tell me how often you speak Hungarian.', '[TK-confirm] (Naponta beszélek magyarul. / Hetente többször beszélek magyarul.)', 'interview', 'All', 'interview,formal-register'],
    ['Kérem, mondja el, mi a teljes neve.', 'Please tell me your full name.', 'A teljes nevem [TK-confirm teljes név].', 'interview', 'All', 'interview,formal-register,basic-info'],
    ['Kérem, mondja el, mi az édesapja neve.', "Please tell me your father's name.", 'Az édesapám neve [TK-confirm].', 'interview', 'All', 'interview,formal-register,family'],
    ['Kérem, mondja el, mióta lakik ezen a címen.', 'Please tell me since when you have been living at this address.', '[TK-confirm év] óta lakom ezen a címen.', 'interview', 'All', 'interview,formal-register,residence,time-expressions'],
    ['Kérem, mutassa be az okmányait. Van útlevele vagy személyi igazolványa?', 'Please show me your documents. Do you have a passport or an ID card?', 'Igen. Itt van az útlevelem, és [TK-confirm] (személyi igazolványom is van / személyi igazolványom nincs).', 'interview', 'All', 'interview,formal-register,documents'],
    ['Kérem, mondja el, járt-e már Magyarországon. Mikor és meddig volt ott?', 'Please tell me whether you have been to Hungary before. When and for how long were you there?', '[TK-confirm] (Igen, jártam Magyarországon. [TK-confirm év]-ben voltam ott [TK-confirm napok száma] napig.)', 'interview', 'All', 'interview,formal-register,travel'],
    ['Kérem, mondja el, honnan származik a családja Magyarországon.', 'Please tell me where your family comes from in Hungary.', 'A családom [TK-confirm település]-ról/-ből származik, [TK-confirm megye] megyéből.', 'interview', 'All', 'interview,formal-register,origins,family'],
    ['Kérem, mondja el, hol dolgozik.', 'Please tell me where you work.', '[TK-confirm cég]-nél dolgozom, [TK-confirm város]-ban/ben.', 'interview', 'All', 'interview,formal-register,occupation'],
    ['Kérem, mondja el, mikor és hol született.', 'Please tell me when and where you were born.', '[TK-confirm dátum]-én születtem [TK-confirm város]-ban/ben, [TK-confirm megye] megyében.', 'interview', 'All', 'interview,formal-register,birthplace,dates'],
    ['Kérem, mondja el, van-e gyermeke, és hány.', 'Please tell me whether you have any children, and how many.', '[TK-confirm] (Igen, [TK-confirm szám] gyermekem van. / Nem, nincs gyermekem.)', 'interview', 'All', 'interview,formal-register,family'],
    ['Kérem, mondja el, hol lakik jelenleg.', 'Please tell me where you currently live.', 'Jelenleg [TK-confirm város]-ban/ben lakom, a [TK-confirm utca] [TK-confirm házszám] alatt.', 'interview', 'All', 'interview,formal-register,residence'],
    ['Kérem, mondja el, házas-e.', 'Please tell me whether you are married.', '[TK-confirm] (Igen, házas vagyok. / Nem, nem vagyok házas.)', 'interview', 'All', 'interview,formal-register,family'],
    // Shorter questions with concrete answers
    ['Hány éves?', 'How old are you?', 'Kilencvenegy éves vagyok.', 'interview', 'Maria', 'interview,dates,numbers'],
    ['Mi a testvére neve?', "What is your sibling's name?", 'Az öcséim neve John és Peter.', 'interview', 'Maria', 'interview,family'],
    ['Van testvére?', 'Do you have any siblings?', 'Igen, van két öcsém.', 'interview', 'Maria', 'interview,family'],
    ['Mikor született?', 'When were you born?', 'Ezerkilencszázharmincnégyben születtem.', 'interview', 'Maria', 'interview,dates,numbers'],
    ['Mi az édesanyja neve?', "What is your mother's name?", 'Az édesanyám neve Maria Angelos volt.', 'interview', 'Maria', 'interview,family'],
    ['Hol született?', 'Where were you born?', 'Medellínben, Kolumbiában születtem.', 'interview', 'Maria', 'interview,birthplace'],
    ['Miben segíthetek?', 'How can I help you?', 'Állampolgársági interjúra jöttem.', 'interview', 'All', 'interview,greeting'],
    ['Mi a neve? / Hogy hívják?', 'What is your name?', 'A nevem Marlene Angelos.', 'interview', 'Maria', 'interview,basic-info'],
    ['Mi az édesapja neve?', "What is your father's name?", 'Az édesapám neve George Angelos volt.', 'interview', 'Maria', 'interview,family'],
    ['Elhozta az útlevelét?', 'Did you bring your passport?', 'Igen, elhoztam az útlevelemet.', 'interview', 'All', 'interview,documents'],
    ['Hol lakik most, és mióta él ott?', 'Where do you live now, and since when have you lived there?', 'Jelenleg Los Angelesben lakom, Kaliforniában, 2015 óta.', 'interview', 'All', 'interview,residence,time-expressions'],
    ['Az Ön családja Magyarország melyik részéről származik, és mivel foglalkoztak a felmenők?', 'Which part of Hungary does your family come from, and what did your ancestors do for a living?', 'Paternal nagyapám magyar származású volt; 1920-ban érkezett az Egyesült Államokba.', 'interview', 'Maria', 'interview,origins,family'],
    // Hungarian statements (from Q&A db entries without a question)
    ['Nyugdíjas orvos vagyok.', 'I am a retired doctor.', '', 'prep', 'All', 'interview,occupation'],
    ['Az édesanyám nővér volt, az édesapám pedig mérnök volt.', 'My mother was a nurse, and my father was an engineer.', '', 'prep', 'All', 'interview,family,occupation,past-tense'],
    ['Hol van a személyi igazolványa?', 'Where is your ID card?', '', 'prep', 'All', 'interview,documents,question-words'],
    ['Korábban orvos voltam, most nyugdíjas vagyok.', 'I used to be a doctor, now I am retired.', '', 'prep', 'All', 'interview,occupation,past-tense'],
];

// Insert with answer_hu support
$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");
foreach ($interview_qa as $row) {
    $stmt->bind_param('ssssss', $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
    $stmt->execute();
    if ($conn->affected_rows > 0) $counts['interview']++;
}
$stmt->close();
$results[] = "Interview Q&A: {$counts['interview']} imported/updated";

// ============================================================
// 3. CITIZENSHIP INTERVIEW WORDS → hungarian_prep
//    Source: Notion collection 2948fa7e-ab97-8056-97f1-000b748156f7
//    36 rows - vocabulary for citizenship interview context
// ============================================================
// [question_hu (the word), answer_en, answer_hu (example sentence), category, who, tags]
$citizenship_words = [
    ['nagykövetség', 'embassy', 'A magyar nagykövetségen voltam.', 'prep', 'All', 'citizenship-words,places'],
    ['konzulátus', 'consulate', 'Smaller diplomatic office than an embassy.', 'prep', 'All', 'citizenship-words,places'],
    ['kérelem', 'application / request', 'Állampolgársági kérelmet adtam be.', 'prep', 'All', 'citizenship-words,documents'],
    ['űrlap / nyomtatvány', 'form', 'Ki kell töltenie az űrlapot.', 'prep', 'All', 'citizenship-words,documents'],
    ['aláírás', 'signature', 'Ide kérem az aláírását.', 'prep', 'All', 'citizenship-words,actions'],
    ['aláír', 'to sign', 'Kérem, írja alá!', 'prep', 'All', 'citizenship-words,actions'],
    ['útlevél', 'passport', 'Van érvényes útlevele?', 'prep', 'All', 'citizenship-words,documents'],
    ['személyi igazolvány', 'ID card', 'Hungarian personal identification card.', 'prep', 'All', 'citizenship-words,documents'],
    ['jogosítvány / vezetői engedély', "driver's license", 'Van amerikai jogosítványom.', 'prep', 'All', 'citizenship-words,documents'],
    ['születési anyakönyvi kivonat', 'birth certificate', 'Be kell mutatnia a születési anyakönyvi kivonatot.', 'prep', 'All', 'citizenship-words,documents'],
    ['házassági anyakönyvi kivonat', 'marriage certificate', 'Official marriage record.', 'prep', 'All', 'citizenship-words,documents'],
    ['válási papír / ítélet', 'divorce decree', 'Van válási ítélete?', 'prep', 'All', 'citizenship-words,documents'],
    ['állampolgárság', 'citizenship', 'Magyar állampolgárságot kérek.', 'prep', 'All', 'citizenship-words,status'],
    ['állampolgár', 'citizen', 'Amerikai állampolgár vagyok.', 'prep', 'All', 'citizenship-words,people'],
    ['útlevélkérelem', 'passport application', 'Compound: útlevél + kérelem.', 'prep', 'All', 'citizenship-words,documents'],
    ['dátum', 'date', 'Mi a mai dátum?', 'prep', 'All', 'citizenship-words,forms'],
    ['pecsét', 'stamp / seal', 'Kérem a pecsétet ide!', 'prep', 'All', 'citizenship-words,documents'],
    ['hivatal', 'office / bureau', 'Az okmányirodában dolgozik.', 'prep', 'All', 'citizenship-words,places'],
    ['okmány', 'document / ID', 'General word for official IDs or papers.', 'prep', 'All', 'citizenship-words,documents'],
    ['okmányiroda', 'document office', 'Handles passports, IDs, etc.', 'prep', 'All', 'citizenship-words,places'],
    ['nyilatkozat', 'declaration / statement', 'Aláírtam a nyilatkozatot.', 'prep', 'All', 'citizenship-words,documents'],
    ['bizonyítvány', 'certificate / diploma', 'Often used for education or proof.', 'prep', 'All', 'citizenship-words,documents'],
    ['hiteles másolat', 'certified copy', 'For submission of official records.', 'prep', 'All', 'citizenship-words,documents'],
    ['fordítás', 'translation', 'Hivatalos fordítást kérek.', 'prep', 'All', 'citizenship-words,documents'],
    ['fordító', 'translator', 'A fordító aláírta a dokumentumot.', 'prep', 'All', 'citizenship-words,people'],
    ['hivatalos', 'official / formal', 'Hivatalos dokumentum.', 'prep', 'All', 'citizenship-words,descriptive'],
    ['érvényes', 'valid', 'Az útlevele még érvényes.', 'prep', 'All', 'citizenship-words,status'],
    ['lejárt', 'expired', 'A jogosítványom lejárt.', 'prep', 'All', 'citizenship-words,status'],
    ['irat / dokumentum', 'record / document', 'General administrative term.', 'prep', 'All', 'citizenship-words,documents'],
    ['jelentkezés', 'application / registration', 'Often for school or job applications.', 'prep', 'All', 'citizenship-words,actions'],
    ['kérelmező', 'applicant', 'The person submitting a request.', 'prep', 'All', 'citizenship-words,people'],
    ['tanú', 'witness', 'May appear on older documents.', 'prep', 'All', 'citizenship-words,people'],
    ['állandó lakcím', 'permanent address', 'Appears on all Hungarian IDs.', 'prep', 'All', 'citizenship-words,personal-info'],
    ['ideiglenes lakcím', 'temporary address', 'For shorter stays.', 'prep', 'All', 'citizenship-words,personal-info'],
    ['születési hely', 'place of birth', 'Found on official papers.', 'prep', 'All', 'citizenship-words,personal-info'],
    ['keltezés', 'date of issue / issuance', 'Formal term for "dated."', 'prep', 'All', 'citizenship-words,forms'],
];

$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");
foreach ($citizenship_words as $row) {
    $stmt->bind_param('ssssss', $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
    $stmt->execute();
    if ($conn->affected_rows > 0) $counts['citizenship_words']++;
}
$stmt->close();
$results[] = "Citizenship words: {$counts['citizenship_words']} imported/updated";

// ============================================================
// 4. SENTENCES TO PRACTICE → hungarian_prep
//    Source: Notion collection eaa5d139-2afc-4cb0-abe1-97a2637b4eea
//    13 rows
// ============================================================
$sentences = [
    ['Korábban orvos voltam, most nyugdíjas vagyok.', 'I used to be a doctor, now I am retired.', '', 'prep', 'All', 'past-tense,occupation'],
    ['Március tizenötödikén ünneplünk.', 'We celebrate on March 15th.', '', 'prep', 'All', 'dates,ordinals'],
    ['Január elsején születtem.', 'I was born on January 1st.', '', 'prep', 'All', 'dates,ordinals,past-tense'],
    ['Annával beszélek.', 'I am speaking with Anna.', '', 'prep', 'All', 'instrumental-val-vel'],
    ['Ez a ház nagy.', 'This house is big.', '', 'prep', 'All', 'demonstratives,adjectives'],
    ['Hány könyv van az asztalon?', 'How many books are on the table?', '', 'prep', 'All', 'question-words,quantifiers'],
    ['Ez a negyedik feladat.', 'This is the fourth task.', '', 'prep', 'All', 'ordinals,demonstratives'],
    ['Mennyi pénz kell?', 'How much money is needed?', '', 'prep', 'All', 'question-words,quantifiers'],
    ['Busszal megyek.', 'I am going by bus.', '', 'prep', 'All', 'instrumental-val-vel,assimilation'],
    ['Napos az idő.', 'The weather is sunny.', '', 'prep', 'All', 'weather-adjectives'],
    ['Ő a hatodik.', 'They are sixth.', '', 'prep', 'All', 'ordinals'],
    ['Esős idő van.', 'It is rainy weather.', '', 'prep', 'All', 'weather-adjectives,van-vannak'],
    ['Az a könyv érdekes.', 'That book is interesting.', '', 'prep', 'All', 'demonstratives,adjectives'],
];

$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), tags=VALUES(tags)");
foreach ($sentences as $row) {
    $stmt->bind_param('ssssss', $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
    $stmt->execute();
    if ($conn->affected_rows > 0) $counts['sentences']++;
}
$stmt->close();
$results[] = "Sentences to Practice: {$counts['sentences']} imported/updated";

// ============================================================
// 5. VOCABULARY → hungarian_prep
//    Source: Notion collection e4f50d0f-52fd-463a-b9b5-63cbd497cc54
//    8 rows
// ============================================================
$vocabulary = [
    // [word, meaning, example, pos_as_category]
    ['megye', 'county', 'Budapest megyében.', 'Noun'],
    ['foglalkozik', 'to work as, to be occupied with', 'Mivel foglalkozik az édesapja?', 'Verb'],
    ['dátum', 'date', 'Kérem, mondja a dátumot: YYYY. MM. DD.', 'Noun'],
    ['család', 'family', 'Az Ön családja melyik részről származik?', 'Noun'],
    ['lakik', 'lives (resides)', 'Los Angelesben lakom 2015 óta.', 'Verb'],
    ['mióta', 'since when', 'Mióta él ott?', 'Other'],
    ['született', 'was born', '1990. 05. 14-én születtem Budapesten.', 'Verb'],
];

$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");
$who_all = 'All';
foreach ($vocabulary as $v) {
    $tags = 'vocabulary,' . strtolower($v[3]);
    $cat = 'prep';
    $stmt->bind_param('ssssss', $v[0], $v[1], $v[2], $cat, $who_all, $tags);
    $stmt->execute();
    if ($conn->affected_rows > 0) $counts['vocab']++;
}
$stmt->close();
$results[] = "Vocabulary: {$counts['vocab']} imported/updated";

// ============================================================
// 6. HISTORICAL DATES → knowledge_cards
//    Source: Notion collection 2948fa7e-ab97-8029-bd28-000be901b94c
//    10 rows
// ============================================================
$history = [
    // [title_hu (event name), title_en, content_hu (description), content_en (pronunciation), key_fact (year/date)]
    ['Honfoglalás', 'Conquest of the Carpathian Basin', 'A magyarok bejönnek a Kárpát-medencébe, Árpád vezetésével.', 'Honfoglalás — [hon-fohg-lah-lahsh]', '895'],
    ['Államalapítás', 'Foundation of the Hungarian State', 'Szent István király megkoronázása.', 'Államalapítás — [ahl-lah-mah-lah-pee-tahsh]', '1000. augusztus 20.'],
    ['Tatárjárás', 'Mongol Invasion', 'A tatárok lerombolták az ország nagy részét.', 'Tatárjárás — [tah-tahr-yaː-rahsh]', '1241–1242'],
    ['Mohácsi csata', 'Battle of Mohács', 'Magyar vereség a törökök ellen.', 'Mohácsi csata — [mo-haa-chi cha-tah]', '1526. augusztus 29.'],
    ['Buda török megszállása', 'Capture of Buda by the Turks', 'Magyarország középső része török uralom alá került.', 'Buda török megszállása — [boo-dah tö-rök meg-szál-lá-sha]', '1541'],
    ['Buda visszafoglalása', 'Liberation of Buda', 'A keresztény seregek felszabadították Budát.', 'Buda visszafoglalása — [boo-dah vis-sah-fohg-lah-lah-sha]', '1686'],
    ['Forradalom és szabadságharc', 'Revolution and War of Independence', 'Kossuth és Petőfi vezették, szabadságot követeltek.', 'Forradalom és szabadságharc — [for-ra-dah-lom eesh sa-bod-shaːg-harts]', '1848. március 15.'],
    ['Kiegyezés', 'Austro-Hungarian Compromise', 'Létrejött az Osztrák-Magyar Monarchia.', 'Kiegyezés — [kee-egg-yeh-zaysh]', '1867'],
    ['Trianoni béke', 'Treaty of Trianon', 'Magyarország elveszti területeinek kétharmadát.', 'Trianoni béke — [tree-ɒ-no-ni bay-keh]', '1920. június 4.'],
    ['Forradalom (1956)', 'Revolution against Soviet rule', 'Felkelés a kommunista hatalom ellen.', 'Forradalom — [for-ra-dah-lom]', '1956. október 23.'],
    ['Köztársaság kikiáltása', 'Proclamation of the Republic', 'Magyarország demokratikus állammá válik.', 'Köztársaság kikiáltása — [kø-staːr-shah-shahg kee-kee-ahl-tah-sha]', '1989. október 23.'],
    ['EU-csatlakozás', 'EU accession', 'Magyarország belép az Európai Unióba.', 'EU-csatlakozás — [ay-oo cha-tloh-ko-zaːsh]', '2004. május 1.'],
];

$stmt = $conn->prepare("INSERT IGNORE INTO knowledge_cards (category, title_hu, title_en, content_hu, content_en, key_fact) VALUES (?, ?, ?, ?, ?, ?)");
$hist_cat = 'history';
foreach ($history as $h) {
    $stmt->bind_param('ssssss', $hist_cat, $h[0], $h[1], $h[2], $h[3], $h[4]);
    $stmt->execute();
    if ($conn->affected_rows > 0) $counts['knowledge']++;
}
$stmt->close();

// Also add history dates as practice phrases in hungarian_prep
$history_phrases = [
    ['Nyolcszázkilencvenötben volt a Honfoglalás.', 'The Hungarian Conquest was in 895.', '', 'prep', 'All', 'history,dates,numbers'],
    ['Ezerben volt az Államalapítás.', 'The founding of the Hungarian State was in 1000.', '', 'prep', 'All', 'history,dates'],
    ['Augusztus huszadikán ünnepeljük Szent István napját.', "We celebrate Saint Stephen's Day on August 20th.", '', 'prep', 'All', 'history,dates,ordinals'],
    ['Ezerkétszáznegyvenegyedikben volt a Muhi csata.', 'The Battle of Muhi was in 1241.', '', 'prep', 'All', 'history,dates,numbers'],
    ['Ezerötszázhuszonhatban volt a Mohácsi csata.', 'The Battle of Mohács was in 1526.', '', 'prep', 'All', 'history,dates,numbers'],
    ['Március tizenötödikén ünnepeljük a Forradalmat.', 'We celebrate the Revolution on March 15th.', '', 'prep', 'All', 'history,dates,ordinals'],
    ['Ezerkilencszázhúszban írták alá a Trianoni békeszerződést.', 'The Treaty of Trianon was signed in 1920.', '', 'prep', 'All', 'history,dates,numbers,past-tense'],
    ['Ezerkilencszázötvenhatban volt a Forradalom.', 'The Hungarian Revolution was in 1956.', '', 'prep', 'All', 'history,dates,numbers'],
    ['Ezerkilencszáznyolcvankilencben kikiáltották a Köztársaságot.', 'The Republic was proclaimed in 1989.', '', 'prep', 'All', 'history,dates,numbers'],
    ['Kétezer-négyben Magyarország belépett az Európai Unióba.', 'Hungary joined the European Union in 2004.', '', 'prep', 'All', 'history,dates,numbers'],
];

$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), tags=VALUES(tags)");
foreach ($history_phrases as $row) {
    $stmt->bind_param('ssssss', $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
    $stmt->execute();
    if ($conn->affected_rows > 0) $counts['history']++;
}
$stmt->close();
$results[] = "Historical: {$counts['knowledge']} knowledge cards + {$counts['history']} practice phrases";

// ============================================================
// 7. COMMON EXPRESSIONS → hungarian_prep
//    Source: Hungarian Solutions Course textbook content
// ============================================================
$expressions = [
    // Ch1 — Introduction
    ['Bocsánat!', "I'm sorry. Pardon. Excuse me."],
    ['Elnézést!', "I'm sorry. Pardon. Excuse me."],
    ['Elnézést, nem értem.', "I'm sorry, I don't understand."],
    ['Fogalmam sincs.', 'I have no idea.'],
    ['Kérdezhetek valamit?', 'Can I ask you something?'],
    ['Köszönöm.', 'Thank you.'],
    ['Külföldi vagyok.', "I'm a foreigner."],
    ['Lassabban, legyen szíves!', 'Slower, please.'],
    ['Nem beszélek magyarul.', "I don't speak Hungarian."],
    ['Nem tudom.', "I don't know."],
    ['Sajnos, nem értem.', "I'm sorry, I don't understand."],
    ['Szívesen.', 'You are welcome.'],
    ['Tessék!', 'Here you are.'],
    ['Világos minden?', 'Is everything clear?'],
    // Ch2 — Encounters
    ['Jó reggelt kívánok!', 'Good morning.'],
    ['Jó napot kívánok!', 'Good afternoon.'],
    ['Jó estét kívánok!', 'Good evening.'],
    ['Jó éjszakát kívánok!', 'Good night.'],
    ['Szervusz!', 'Hi. Hello. Bye. (informal)'],
    ['Szia!', 'Hi. Hello. Bye. (informal)'],
    ['Viszontlátásra!', 'Goodbye!'],
    ['Nagyon örülök.', 'Very nice to meet you.'],
    ['Semmi baj.', 'No problem.'],
    ['Tényleg?', 'Really?'],
    ['Lehet.', "Maybe. It's possible."],
    ['És Ön?', 'And you? (formal)'],
    ['Hány éves vagy?', 'How old are you?'],
    ['Hol élsz?', 'Where do you live?'],
    ['Milyen nemzetiségű vagy?', 'What nationality are you?'],
    ['Milyen nyelven beszélsz?', 'What language do you speak?'],
    ['Miért tanulsz magyarul?', 'Why are you learning Hungarian?'],
    ['Budapesten élek.', 'I live in Budapest.'],
    ['Egy kicsit tudok oroszul.', 'I can speak a little Russian.'],
    ['Elég jól beszélek németül.', 'I can speak German quite well.'],
    // Ch3 — Office
    ['Jól vagyok.', "I'm well."],
    ['Megvagyok.', "I'm OK."],
    ['Minden rendben van.', 'Everything is fine.'],
    ['Persze.', 'Of course. Sure.'],
    ['Rendben. Jó.', 'All right. Good.'],
    ['Segítesz?', 'Can you help me?'],
    ['Tudsz segíteni?', 'Can you help me?'],
    ['Jó munkát kívánok!', 'Enjoy your work!'],
    ['Mennyibe kerül ez a szék?', 'How much does this chair cost?'],
    ['Ki az a magas férfi?', 'Who is that tall man?'],
    ['Most megyek, mert vár a főnök.', 'I have to go now, my boss is waiting.'],
    // Ch4 — City
    ['Elnézést, van itt a közelben étterem?', 'Excuse me, is there a restaurant nearby?'],
    ['Hány óra van?', 'What time is it?'],
    ['Egy óra van.', "It's one o'clock."],
    ['Fél egy van.', "It's half past twelve."],
    ['Háromnegyed egy van.', "It's quarter to one."],
    ['Közel van.', "It's near."],
    ['Messze van a vár?', 'Is the castle far?'],
    ['Milyen nap van ma?', 'What day is it today?'],
    ['Nem csinálok semmit.', "I'm not doing anything."],
    ['Megyünk együtt moziba?', 'Shall we go to the cinema together?'],
    ['Magyarországon nincsenek magas hegyek.', 'There are no high mountains in Hungary.'],
    // Ch5 — Shopping/Restaurant
    ['Jó étvágyat!', 'Enjoy your meal!'],
    ['Fizetni szeretnék.', "I'd like to pay."],
    ['Készpénzzel fizetek.', "I'll pay cash."],
    ['Mennyit fizetek?', 'How much do I owe you?'],
    ['Mennyibe kerül a szőlő?', 'How much do the grapes cost?'],
    ['Még valamit?', 'Anything else?'],
    ['Köszönöm, csak körülnézek.', 'I am just looking around, thank you.'],
    ['Köszönöm, mást nem kérek.', 'That will be all, thank you.'],
    ['Szabad ez az asztal?', 'Is this table free?'],
    ['Ebédelni szeretnék.', 'I would like to have lunch.'],
    ['Egészségesen táplálkozom.', 'I eat healthy.'],
    ['Asztalt szeretnék foglalni két személyre.', "I'd like to book a table for two."],
    // Ch6 — Services/Free time
    ['Esik az eső.', "It's raining."],
    ['Esik a hó.', "It's snowing."],
    ['Fúj a szél.', 'The wind is blowing.'],
    ['Süt a nap.', 'The sun is shining.'],
    ['Hideg van.', "It's cold."],
    ['Meleg van.', "It's hot."],
    ['Imádok úszni.', 'I love swimming.'],
    ['Mindennap főzök.', 'I cook every day.'],
    ['Külföldre utazom.', "I'm travelling abroad."],
    ['Igen, ráérek.', 'Yes, I have time.'],
    ['Ráérsz pénteken?', 'Are you free on Friday?'],
    ['Rendszeresen járok uszodába.', 'I go swimming regularly.'],
    ['Sajnos nem tudok táncolni.', "Unfortunately I can't dance."],
    // Ch7 — Weekdays
    ['Felkelek.', 'I get up.'],
    ['Bemegyek az irodába.', 'I go into the office.'],
    ['Kijövök az irodából.', 'I come out of the office.'],
    ['Kimegyek az utcára.', 'I go out to the street.'],
    ['Lefekszem.', 'I go to bed.'],
    ['Leülök.', 'I sit down.'],
    ['Szeptember óta tanulok magyarul.', "I've been learning Hungarian since September."],
    ['Hiányzik a barátom.', 'I miss my friend.'],
    ['Fontos, amit csinálok.', 'What I do is important.'],
    // Ch8 — Home
    ['Van egy testvérem.', 'I have one sibling.'],
    ['Nagyon szeretem az édesanyámat.', 'I love my mother very much.'],
    ['Jó környéken lakunk.', 'We live in a nice neighbourhood.'],
    ['Lakást bérelek.', "I'm renting a flat."],
    ['Saját lakásom van.', "I've got my own flat."],
    ['Nincs háziállatom.', "I don't have a pet."],
    ['Amikor kicsi voltam, szerettem biciklizni.', 'When I was little, I loved riding my bike.'],
    ['Minden barátomat meghívtam.', 'I invited all my friends.'],
];

$cat = 'prep';
$who = 'All';
$empty = '';
$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en)");
foreach ($expressions as $e) {
    $tags = 'common-expressions';
    $stmt->bind_param('ssssss', $e[0], $e[1], $empty, $cat, $who, $tags);
    $stmt->execute();
    if ($conn->affected_rows > 0) $counts['expressions']++;
}
$stmt->close();
$results[] = "Common expressions: {$counts['expressions']} imported/updated";

// ============================================================
// 8. DRILL GROUPS — tag-based phrase groupings
// ============================================================
$col = $conn->query("SHOW COLUMNS FROM drill_groups LIKE 'tag_match'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE drill_groups ADD COLUMN tag_match VARCHAR(500) DEFAULT NULL AFTER description");
}

$idx = $conn->query("SHOW INDEX FROM drill_groups WHERE Key_name = 'unique_name'");
if ($idx && $idx->num_rows === 0) {
    $conn->query("DELETE d FROM drill_groups d INNER JOIN (
        SELECT name, MIN(id) AS keep_id FROM drill_groups GROUP BY name HAVING COUNT(*) > 1
    ) dups ON d.name = dups.name AND d.id != dups.keep_id");
    $conn->query("ALTER TABLE drill_groups ADD UNIQUE KEY unique_name (name)");
}

$drill_groups = [
    ['Asking Questions', 'mi? kit? kinek? — question words & cases', 'question-words', 'notion'],
    ['My, Your, Our — Possessives', 'Possessive endings for all persons', 'possessive', 'notion'],
    ['Numbers & Dates', 'Counting, ordinals, months, dates', 'dates,numbers,ordinals', 'notion'],
    ['Vowel Harmony', 'How suffixes change with vowels: -ban/-ben, -val/-vel', 'instrumental-val-vel,inessive-ban-ben', 'notion'],
    ['Describing Things', 'Weather, colors, adjectives with -s/-os/-es/-ös', 'weather-adjectives,adjectives', 'notion'],
    ['This & That', 'ez/az/ezek/azok — demonstratives with articles', 'demonstratives', 'notion'],
    ['Verb Prefixes', 'be- ki- fel- le- el- meg- oda- rá-', 'verb-prefix', 'notion'],
    ['About Me', 'Name, age, birthplace, documents', 'interview,basic-info,greeting,documents', 'notion'],
    ['My Family', 'Parents, siblings, marital status, children', 'interview,family', 'notion'],
    ['My Work', 'Job, occupation, retirement', 'interview,occupation', 'notion'],
    ['Why Hungary?', 'Heritage, motivation for citizenship', 'interview,origins', 'notion'],
    ['Greetings & Expressions', 'Jó reggelt, Szia, Viszontlátásra, etc.', 'common-expressions', 'notion'],
    ['Shopping & Eating Out', 'Ordering, paying, asking prices', 'shopping,restaurant', 'notion'],
    ['Hungarian History', 'Key dates and events (895–2004)', 'history', 'notion'],
    ['Daily Routines', 'felkelek, bemegyek — verbs in context', 'daily,verb-prefix', 'notion'],
    ['With Something (-val/-vel)', 'Instrumental case & consonant assimilation', 'instrumental-val-vel,assimilation', 'notion'],
    ['Talking About the Past', 'Past tense sentences', 'past-tense', 'notion'],
    ['Being Polite (Ön)', 'Formal/polite speech', 'formal-register', 'notion'],
    ['Time & Duration', 'Telling time, how long, since when', 'time-expressions', 'notion'],
    ['Places & Directions', 'Where? -ban/-ben, -hoz/-hez, -ból/-ből', 'inessive-ban-ben,places', 'notion'],
    ['Citizenship Vocabulary', 'Key words for documents, offices, and status', 'citizenship-words', 'notion'],
];

$stmt = $conn->prepare("INSERT INTO drill_groups (name, description, tag_match, source) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE description=VALUES(description), tag_match=VALUES(tag_match)");
foreach ($drill_groups as $dg) {
    $stmt->bind_param('ssss', $dg[0], $dg[1], $dg[2], $dg[3]);
    $stmt->execute();
}
$stmt->close();
$results[] = "Drill groups: " . count($drill_groups) . " processed";

// ============================================================
// Summary
// ============================================================
$total = array_sum($counts);
$conn->close();
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Notion Import Results</title>
<style>
body { font-family: system-ui; background: #0f172a; color: #e2e8f0; padding: 2rem; max-width: 700px; margin: 0 auto; }
h2 { color: #818cf8; }
.card { background: #1e293b; border-radius: 8px; padding: 1rem 1.5rem; margin: 1rem 0; }
.card h3 { margin: 0 0 0.5rem; color: #a5b4fc; }
.num { color: #34d399; font-weight: bold; font-size: 1.2em; }
ul { list-style: none; padding: 0; }
li { padding: 0.3rem 0; border-bottom: 1px solid #334155; }
a { color: #818cf8; }
</style>
</head><body>
<h2>Notion → Hug Import Results</h2>

<div class="card">
    <h3>Summary</h3>
    <p>Total items processed: <span class="num"><?= $total ?></span></p>
    <p><small>Data sourced from Notion workspace on 2026-03-29</small></p>
</div>

<div class="card">
    <h3>Details</h3>
    <ul>
    <?php foreach ($results as $r): ?>
        <li><?= htmlspecialchars($r) ?></li>
    <?php endforeach; ?>
    </ul>
</div>

<div class="card">
    <h3>Counts by Type</h3>
    <ul>
        <li>Grammar patterns: <span class="num"><?= $counts['grammar'] ?></span></li>
        <li>Interview Q&A: <span class="num"><?= $counts['interview'] ?></span></li>
        <li>Citizenship words: <span class="num"><?= $counts['citizenship_words'] ?></span></li>
        <li>Practice sentences: <span class="num"><?= $counts['sentences'] ?></span></li>
        <li>Vocabulary: <span class="num"><?= $counts['vocab'] ?></span></li>
        <li>History → knowledge_cards: <span class="num"><?= $counts['knowledge'] ?></span></li>
        <li>History → practice phrases: <span class="num"><?= $counts['history'] ?></span></li>
        <li>Common expressions: <span class="num"><?= $counts['expressions'] ?></span></li>
        <li>Drill groups: <span class="num"><?= count($drill_groups) ?></span></li>
    </ul>
</div>

<div class="card">
    <h3>Notion Sources</h3>
    <ul>
        <li>Interview Q&A: <code>d1cd452f-1c5f-4d23-b5a3-2c4c34f7834e</code> (32 rows)</li>
        <li>Citizenship Words: <code>2948fa7e-ab97-8056-97f1-000b748156f7</code> (36 rows)</li>
        <li>Sentences to Practice: <code>eaa5d139-2afc-4cb0-abe1-97a2637b4eea</code> (13 rows)</li>
        <li>Vocabulary: <code>e4f50d0f-52fd-463a-b9b5-63cbd497cc54</code> (8 rows)</li>
        <li>Historical Dates: <code>2948fa7e-ab97-8029-bd28-000be901b94c</code> (12 rows)</li>
        <li>Grammar Patterns: <code>a108ddfa-9490-45d3-9de2-5a56d84dc413</code> (41 rows)</li>
    </ul>
</div>

<p><a href="admin.php">← Back to Admin</a></p>
</body></html>
