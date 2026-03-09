<?php
// Notion → Hug MySQL Import Script
// Imports grammar patterns, sentences, vocabulary, interview Q&A, common expressions, and history dates
// Safe to re-run: uses INSERT IGNORE / ON DUPLICATE KEY UPDATE

session_start();
$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$results = [];
$counts = ['grammar' => 0, 'sentences' => 0, 'vocab' => 0, 'interview' => 0, 'expressions' => 0, 'history' => 0];

// ============================================================
// 1. GRAMMAR PATTERNS → grammar_patterns table
// ============================================================
$patterns = [
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
// 2. SENTENCES TO PRACTICE → hungarian_prep
// ============================================================
$sentences = [
    ['Korábban orvos voltam, most nyugdíjas vagyok.', 'I used to be a doctor, now I am retired.', 'prep', 'All', 'past-tense,occupation'],
    ['Március tizenötödikén ünneplünk.', 'We celebrate on March 15th.', 'prep', 'All', 'dates,ordinals'],
    ['Január elsején születtem.', 'I was born on January 1st.', 'prep', 'All', 'dates,ordinals,past-tense'],
    ['Annával beszélek.', 'I am speaking with Anna.', 'prep', 'All', 'instrumental-val-vel'],
    ['Ez a ház nagy.', 'This house is big.', 'prep', 'All', 'demonstratives,adjectives'],
    ['Hány könyv van az asztalon?', 'How many books are on the table?', 'prep', 'All', 'question-words,quantifiers'],
    ['Ez a negyedik feladat.', 'This is the fourth task.', 'prep', 'All', 'ordinals,demonstratives'],
    ['Mennyi pénz kell?', 'How much money is needed?', 'prep', 'All', 'question-words,quantifiers'],
    ['Busszal megyek.', 'I am going by bus.', 'prep', 'All', 'instrumental-val-vel,assimilation'],
    ['Napos az idő.', 'The weather is sunny.', 'prep', 'All', 'weather-adjectives'],
    ['Ő a hatodik.', 'They are sixth.', 'prep', 'All', 'ordinals'],
    ['Esős idő van.', 'It is rainy weather.', 'prep', 'All', 'weather-adjectives,van-vannak'],
    ['Az a könyv érdekes.', 'That book is interesting.', 'prep', 'All', 'demonstratives,adjectives'],
];

// Also add vocabulary items as practice phrases
$vocab_phrases = [
    ['A családom', 'My family', 'prep', 'All', 'possessive,family'],
    ['Budapest megyében.', 'In Budapest county.', 'prep', 'All', 'inessive-ban-ben,places'],
    ['Mivel foglalkozik az édesapja?', 'What does your father do for a living?', 'prep', 'All', 'question-words,occupation,possessive'],
    ['Kérem, mondja a dátumot.', 'Please say the date.', 'prep', 'All', 'dates,formal-register'],
    ['Az Ön családja melyik részről származik?', 'Which part does your family come from?', 'prep', 'All', 'question-words,family,formal-register'],
    ['Los Angelesben lakom 2015 óta.', 'I have lived in Los Angeles since 2015.', 'prep', 'All', 'inessive-ban-ben,time-expressions'],
    ['Mióta él ott?', 'Since when have you lived there?', 'prep', 'All', 'question-words,time-expressions'],
    ['1990. 05. 14-én születtem Budapesten.', 'I was born on May 14, 1990 in Budapest.', 'prep', 'All', 'dates,past-tense,inessive-ban-ben'],
];

// Interview Q&A — complete pairs only (skip [TK-confirm] placeholders for now)
$interview_phrases = [
    // Basic info
    ['Miben segíthetek?', 'How can I help you?', 'prep', 'All', 'interview,formal-register,greeting'],
    ['Állampolgársági interjúra jöttem.', 'I came for a citizenship interview.', 'prep', 'All', 'interview,formal-register'],
    ['Igen, elhoztam az útlevelemet.', 'Yes, I brought my passport.', 'prep', 'All', 'interview,documents,possessive'],
    ['A nevem Marlene Angelos.', 'My name is Marlene Angelos.', 'prep', 'Maria', 'interview,basic-info'],
    ['Ezerkilencszázharmincnégyben születtem.', 'I was born in 1934.', 'prep', 'Maria', 'interview,dates,past-tense'],
    ['Medellínben, Kolumbiában születtem.', 'I was born in Medellín, Colombia.', 'prep', 'Maria', 'interview,birthplace,inessive-ban-ben'],
    ['Kilencvenegy éves vagyok.', 'I am ninety-one years old.', 'prep', 'Maria', 'interview,numbers'],
    // Family
    ['Az édesanyám neve Maria Angelos volt.', "My mother's name was Maria Angelos.", 'prep', 'Maria', 'interview,family,possessive,past-tense'],
    ['Az édesapám neve George Angelos volt.', "My father's name was George Angelos.", 'prep', 'Maria', 'interview,family,possessive,past-tense'],
    ['Igen, van két öcsém.', 'Yes, I have two younger brothers.', 'prep', 'Maria', 'interview,family,numbers'],
    ['Az öcséim neve John és Peter.', "My brothers' names are John and Peter.", 'prep', 'Maria', 'interview,family,possessive'],
    // Occupation & daily life (from Polite Q&A page)
    ['Nyugdíjas vagyok.', 'I am retired.', 'prep', 'Maria', 'interview,occupation'],
    ['Az egyik orvos, a másik mérnök.', 'One is a doctor, the other an engineer.', 'prep', 'Maria', 'interview,occupation,family'],
    ['Los Angelesben élek.', 'I live in Los Angeles.', 'prep', 'All', 'interview,residence,inessive-ban-ben'],
    // Hobbies
    ['Szeretek olvasni és zenét hallgatni.', 'I like reading and listening to music.', 'prep', 'Maria', 'interview,hobbies,infinitive'],
    ['Ő szeretett kertészkedni.', 'She liked gardening.', 'prep', 'Maria', 'interview,hobbies,past-tense'],
    ['Ő szeretett sakkozni.', 'He liked playing chess.', 'prep', 'Maria', 'interview,hobbies,past-tense'],
    // More family from Polite Q&A
    ['A szüleim mindketten ezerkilencszázharmincnégyben születtek.', 'My parents were both born in 1934.', 'prep', 'Maria', 'interview,family,dates,past-tense'],
    ['Ők Onnokon születtek.', 'They were born in Onnok.', 'prep', 'Maria', 'interview,family,birthplace,past-tense'],
    ['Ők már nem élnek.', 'They are no longer alive.', 'prep', 'Maria', 'interview,family'],
    ['Az egyik testvérem New Yorkban él, a másik Floridában.', 'One of my brothers lives in New York, the other in Florida.', 'prep', 'Maria', 'interview,family,residence'],
    // Longer interview answers
    ['Magyar állampolgár szeretnék lenni, mert magyar származású vagyok, és szeretném megőrizni a családi kötődést és a nyelvet.', 'I would like to become a Hungarian citizen because I am of Hungarian origin, and I would like to preserve the family connection and the language.', 'prep', 'All', 'interview,origins,formal-register'],
    ['Jelenleg Los Angelesben lakom, Kaliforniában, 2015 óta.', 'I currently live in Los Angeles, California, since 2015.', 'prep', 'All', 'interview,residence,time-expressions'],
    // Standalone Hungarian sentences from the Q&A db (no English question needed)
    ['Nyugdijas orvos vagyok.', 'I am a retired doctor.', 'prep', 'All', 'interview,occupation'],
    ['Az édesanyám nővér volt, az édesapám pedig mérnök volt.', 'My mother was a nurse, and my father was an engineer.', 'prep', 'All', 'interview,family,occupation,past-tense'],
    ['Hol van a személyi igazolványa?', 'Where is your ID card?', 'prep', 'All', 'interview,documents,question-words,possessive'],
];

// History dates as practice phrases
$history_phrases = [
    ['Nyolcszázkilencvenötben volt a Honfoglalás.', 'The Hungarian Conquest was in 895.', 'prep', 'All', 'history,dates,numbers'],
    ['Ezerben volt az Államalapítás.', 'The founding of the Hungarian State was in 1000.', 'prep', 'All', 'history,dates'],
    ['Augusztus huszadikán ünnepeljük Szent István napját.', 'We celebrate Saint Stephen\'s Day on August 20th.', 'prep', 'All', 'history,dates,ordinals'],
    ['Ezerkétszáznegyvenegyedikben volt a Muhi csata.', 'The Battle of Muhi was in 1241.', 'prep', 'All', 'history,dates,numbers'],
    ['Ezerötszázhuszonhatban volt a Mohácsi csata.', 'The Battle of Mohács was in 1526.', 'prep', 'All', 'history,dates,numbers'],
    ['Március tizenötödikén ünnepeljük a Forradalmat.', 'We celebrate the Revolution on March 15th.', 'prep', 'All', 'history,dates,ordinals'],
    ['Ezerkilencszázhúszban írták alá a Trianoni békeszerződést.', 'The Treaty of Trianon was signed in 1920.', 'prep', 'All', 'history,dates,numbers,past-tense'],
    ['Ezerkilencszázötvenhatban volt a Forradalom.', 'The Hungarian Revolution was in 1956.', 'prep', 'All', 'history,dates,numbers'],
];

$all_phrases = array_merge($sentences, $vocab_phrases, $interview_phrases, $history_phrases);

// Ensure question_hu column exists (should already from index.php auto-migration)
$col = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'tags'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE hungarian_prep ADD COLUMN tags TEXT DEFAULT NULL AFTER `who`");
}

$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, category, `who`, tags) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), tags=VALUES(tags)");
foreach ($all_phrases as $p) {
    $stmt->bind_param('sssss', $p[0], $p[1], $p[2], $p[3], $p[4]);
    $stmt->execute();
    if ($conn->affected_rows > 0) {
        if (in_array($p, $sentences)) $counts['sentences']++;
        elseif (in_array($p, $vocab_phrases)) $counts['vocab']++;
        elseif (in_array($p, $interview_phrases)) $counts['interview']++;
        elseif (in_array($p, $history_phrases)) $counts['history']++;
    }
}
$stmt->close();
$results[] = "Sentences: {$counts['sentences']} | Vocab phrases: {$counts['vocab']} | Interview: {$counts['interview']} | History: {$counts['history']}";

// ============================================================
// 3. COMMON EXPRESSIONS → hungarian_prep
// ============================================================
$expressions = [
    // Ch1 — Introduction
    ['Bocsánat!', "I'm sorry. Pardon. Excuse me."],
    ['Elnézést! ', "I'm sorry. Pardon. Excuse me."],
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
$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, category, `who`, tags) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en)");
foreach ($expressions as $e) {
    $tags = 'common-expressions';
    $stmt->bind_param('sssss', $e[0], $e[1], $cat, $who, $tags);
    $stmt->execute();
    if ($conn->affected_rows > 0) $counts['expressions']++;
}
$stmt->close();
$results[] = "Common expressions: {$counts['expressions']} processed";

// ============================================================
// 4. DRILL GROUPS
// ============================================================
// Ensure tag_match column exists
$col = $conn->query("SHOW COLUMNS FROM drill_groups LIKE 'tag_match'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE drill_groups ADD COLUMN tag_match VARCHAR(500) DEFAULT NULL AFTER description");
}

// [name, description, tag_match (comma-separated patterns to match in phrase tags), source]
$drill_groups = [
    ['Question Words', 'mi/kit/kinek question word forms and cases', 'question-words', 'notion'],
    ['Possessive Endings', 'All person/number possessive suffixes', 'possessive', 'notion'],
    ['Numbers and Dates', 'Cardinal numbers, ordinals, months, dates', 'dates,numbers,ordinals', 'notion'],
    ['Vowel Harmony Cases', 'Case suffixes with vowel harmony variants', 'instrumental-val-vel,inessive-ban-ben', 'notion'],
    ['Weather and Adjectives', 'Weather adjectives with -s/-os/-es/-ös', 'weather-adjectives,adjectives', 'notion'],
    ['Demonstratives', 'ez/az/ezek/azok with articles', 'demonstratives', 'notion'],
    ['Verb Prefixes', 'be- ki- fel- le- el- meg- oda- rá-', 'verb-prefix', 'notion'],
    ['Interview - Basic Info', 'Name, age, birthplace, documents', 'interview,basic-info,greeting,documents', 'notion'],
    ['Interview - Family', 'Parents, siblings, marital status, children', 'interview,family', 'notion'],
    ['Interview - Occupation', 'Work, job, retirement', 'interview,occupation', 'notion'],
    ['Interview - Origins', 'Hungarian heritage, motivation for citizenship', 'interview,origins', 'notion'],
    ['Common Greetings & Expressions', 'Jó reggelt, Szia, Viszontlátásra, etc.', 'common-expressions', 'notion'],
    ['Shopping & Restaurant', 'Ordering, paying, asking prices', 'shopping,restaurant', 'notion'],
    ['Hungarian History', 'Key dates and events (895-1956)', 'history', 'notion'],
    ['Daily Activities', 'Verb prefixes in context: felkelek, bemegyek, etc.', 'daily,verb-prefix', 'notion'],
    ['val/-vel Assimilation', 'Instrumental case with consonant assimilation', 'instrumental-val-vel,assimilation', 'notion'],
    ['Past Tense', 'Sentences using past tense forms', 'past-tense', 'notion'],
    ['Formal Register', 'Formal/polite speech (Ön forms)', 'formal-register', 'notion'],
    ['Time Expressions', 'Telling time, durations, since when', 'time-expressions', 'notion'],
    ['Places & Location', 'Inessive, allative, direction suffixes', 'inessive-ban-ben,places', 'notion'],
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
        <li>Practice sentences: <span class="num"><?= $counts['sentences'] ?></span></li>
        <li>Vocabulary phrases: <span class="num"><?= $counts['vocab'] ?></span></li>
        <li>Interview Q&A: <span class="num"><?= $counts['interview'] ?></span></li>
        <li>History dates: <span class="num"><?= $counts['history'] ?></span></li>
        <li>Common expressions: <span class="num"><?= $counts['expressions'] ?></span></li>
        <li>Drill groups: <span class="num"><?= count($drill_groups) ?></span></li>
    </ul>
</div>

<p><a href="admin.php">← Back to Admin</a></p>
</body></html>
