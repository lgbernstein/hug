<?php
// Anna's Vocab Sheets → Hug MySQL Import
// Source: Larry & Maria_Vocab (1) (1).docx — 22 lessons (Aug 2025 – Jan 2026)
// Curated vocabulary words + full sentences from Hungarian Solutions teacher Anna
// Safe to re-run: ON DUPLICATE KEY UPDATE

session_start();
$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$batch = 'anna_vocab_lessons_1-22';
$counts = ['vocab' => 0, 'sentences' => 0];

// ============================================================
// LESSON 1 (2025-08-15): Greetings, basics, nationalities
// ============================================================
$lesson1 = [
    // Vocabulary
    ['Jó', 'good', '', 'prep', 'All', 'anna-L1,greetings,beginner'],
    ['Jól', 'well', '', 'prep', 'All', 'anna-L1,greetings,beginner'],
    ['Kicsi', 'small', '', 'prep', 'All', 'anna-L1,adjective,beginner'],
    ['Nagy', 'big', '', 'prep', 'All', 'anna-L1,adjective,beginner'],
    ['Kicsit', 'a little bit', '', 'prep', 'All', 'anna-L1,adverb,beginner'],
    ['Ideges', 'nervous', '', 'prep', 'All', 'anna-L1,adjective,beginner'],
    ['Beteg', 'ill', '', 'prep', 'All', 'anna-L1,adjective,beginner'],
    ['Ön', 'you (formal)', '', 'prep', 'All', 'anna-L1,pronoun,beginner'],
    ['Is', 'also', '', 'prep', 'All', 'anna-L1,conjunction,beginner'],
    ['Ország', 'country', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Nemzetiség', 'nationality', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Nyelv', 'language', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['És', 'and', '', 'prep', 'All', 'anna-L1,conjunction,beginner'],
    ['De', 'but', '', 'prep', 'All', 'anna-L1,conjunction,beginner'],
    ['Mérnök', 'engineer', '', 'prep', 'All', 'anna-L1,occupation,beginner'],
    ['Orvos', 'doctor', '', 'prep', 'All', 'anna-L1,occupation,beginner'],
    ['Templom', 'church', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Iroda', 'office', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Iskola', 'school', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Kórház', 'hospital', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Ház', 'house', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Állatkert', 'zoo', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Állat', 'animal', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Anya', 'mother', '', 'prep', 'All', 'anna-L1,family,beginner'],
    ['Édesanya', 'mother (formal/sweet)', '', 'prep', 'All', 'anna-L1,family,beginner'],
    ['Apa', 'father', '', 'prep', 'All', 'anna-L1,family,beginner'],
    ['Édesapa', 'father (formal/sweet)', '', 'prep', 'All', 'anna-L1,family,beginner'],
    ['Kontinens', 'continent', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Város', 'city', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Bécs', 'Vienna', '', 'prep', 'All', 'anna-L1,geography,beginner'],
    ['Sok', 'many', '', 'prep', 'All', 'anna-L1,quantity,beginner'],
    ['Világ', 'world', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Szó', 'word', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    ['Sziget', 'island', '', 'prep', 'All', 'anna-L1,noun,beginner'],
    // Sentences
    ['Nagyon jó.', 'Very good.', '', 'prep', 'All', 'anna-L1,greetings,sentence,beginner'],
    ['Nagyon jól vagyok.', 'I am very well.', '', 'prep', 'All', 'anna-L1,greetings,sentence,beginner'],
];

// ============================================================
// LESSON 2 (2025-08-22): Descriptions, family, possessives
// ============================================================
$lesson2 = [
    ['Megvagyok.', 'So-so. / I\'m getting by.', '', 'prep', 'All', 'anna-L2,greetings,sentence,beginner'],
    ['Ott', 'there', '', 'prep', 'All', 'anna-L2,adverb,beginner'],
    ['Itt', 'here', '', 'prep', 'All', 'anna-L2,adverb,beginner'],
    ['Nő', 'woman', '', 'prep', 'All', 'anna-L2,noun,beginner'],
    ['Új', 'new', '', 'prep', 'All', 'anna-L2,adjective,beginner'],
    ['Újság', 'newspaper', '', 'prep', 'All', 'anna-L2,noun,beginner'],
    ['Újságíró', 'journalist', '', 'prep', 'All', 'anna-L2,occupation,beginner'],
    ['Szakáll', 'beard', '', 'prep', 'All', 'anna-L2,noun,beginner'],
    ['Ez', 'this', '', 'prep', 'All', 'anna-L2,pronoun,beginner'],
    ['Az', 'that', '', 'prep', 'All', 'anna-L2,pronoun,beginner'],
    ['Család', 'family', '', 'prep', 'All', 'anna-L2,family,beginner'],
    ['Férj', 'husband', '', 'prep', 'All', 'anna-L2,family,beginner'],
    ['Feleség', 'wife', '', 'prep', 'All', 'anna-L2,family,beginner'],
    ['Vonat', 'train', '', 'prep', 'All', 'anna-L2,noun,beginner'],
    ['Nincs', 'there is no / I don\'t have', '', 'prep', 'All', 'anna-L2,negation,beginner'],
    ['Nincsenek', 'there are no / they don\'t have', '', 'prep', 'All', 'anna-L2,negation,beginner'],
    ['Baj', 'problem', '', 'prep', 'All', 'anna-L2,noun,beginner'],
    // Sentences
    ['Nem, a szék nem barna, hanem kék.', 'No, the chair is not brown, but blue.', '', 'prep', 'All', 'anna-L2,hanem,sentence,beginner'],
    ['Nem, a ház nem nagy, hanem kicsi.', 'No, the house is not big, but small.', '', 'prep', 'All', 'anna-L2,hanem,sentence,beginner'],
    ['Van férjem.', 'I have a husband.', '', 'prep', 'All', 'anna-L2,possessive,sentence,beginner'],
    ['Van férje.', 'She/He has a husband.', '', 'prep', 'All', 'anna-L2,possessive,sentence,beginner'],
    ['Itt vagyok.', 'I am here.', '', 'prep', 'All', 'anna-L2,van,sentence,beginner'],
    ['Ő itt van.', 'She/He is here.', '', 'prep', 'All', 'anna-L2,van,sentence,beginner'],
    ['Szeretnék bemutatkozni.', 'I would like to introduce myself.', '', 'prep', 'All', 'anna-L2,interview,sentence,beginner'],
    ['Magyar származású vagyok.', 'I am of Hungarian origin.', '', 'prep', 'All', 'anna-L2,interview,sentence,essential'],
    ['A férjem magyar származású.', 'My husband is of Hungarian origin.', '', 'prep', 'Maria', 'anna-L2,interview,sentence,essential'],
];

// ============================================================
// LESSON 3 (2025-08-28): Languages, nationalities, interview basics
// ============================================================
$lesson3 = [
    ['Barát', 'friend / boyfriend', '', 'prep', 'All', 'anna-L3,noun,beginner'],
    ['Barátnő', 'female friend / girlfriend', '', 'prep', 'All', 'anna-L3,noun,beginner'],
    ['Sajnos', 'unfortunately', '', 'prep', 'All', 'anna-L3,adverb,beginner'],
    ['Bocsánat.', 'I am sorry.', '', 'prep', 'All', 'anna-L3,polite,sentence,beginner'],
    ['Elnézést.', 'Excuse me.', '', 'prep', 'All', 'anna-L3,polite,sentence,beginner'],
    ['Még', 'still', '', 'prep', 'All', 'anna-L3,adverb,beginner'],
    ['Testvér', 'sibling', '', 'prep', 'All', 'anna-L3,family,beginner'],
    ['Szülő', 'parent', '', 'prep', 'All', 'anna-L3,family,beginner'],
    // Sentences
    ['Milyen nyelven beszél?', 'What language do you speak?', '', 'prep', 'All', 'anna-L3,question,sentence,beginner'],
    ['Angolul beszélek.', 'I speak English.', '', 'prep', 'All', 'anna-L3,language,sentence,beginner'],
    ['Mi a neve?', 'What is your name? (formal)', '', 'prep', 'All', 'anna-L3,question,interview,sentence,beginner'],
    ['Hol él?', 'Where do you live?', '', 'prep', 'All', 'anna-L3,question,interview,sentence,beginner'],
    ['Californiában élek.', 'I live in California.', '', 'prep', 'All', 'anna-L3,interview,sentence,essential'],
    ['Mi a nemzetisége?', 'What is your nationality?', '', 'prep', 'All', 'anna-L3,question,interview,sentence,beginner'],
    ['Amerikai vagyok.', 'I am American.', '', 'prep', 'All', 'anna-L3,interview,sentence,essential'],
    ['A feleségem neve Maria.', 'My wife\'s name is Maria.', '', 'prep', 'Larry', 'anna-L3,interview,family,sentence,essential'],
    ['A fiam neve Tev.', 'My son\'s name is Tev.', '', 'prep', 'All', 'anna-L3,interview,family,sentence,essential'],
    ['A lányom neve Hannah.', 'My daughter\'s name is Hannah.', '', 'prep', 'All', 'anna-L3,interview,family,sentence,essential'],
    ['Van egy lányom és egy fiam.', 'I have a daughter and a son.', '', 'prep', 'All', 'anna-L3,interview,family,sentence,essential'],
    ['A szüleim már nem élnek.', 'My parents are no longer alive.', '', 'prep', 'All', 'anna-L3,interview,family,sentence,essential'],
    ['Az apai nagyapám magyar volt.', 'My paternal grandfather was Hungarian.', '', 'prep', 'Larry', 'anna-L3,interview,family,sentence,essential'],
    ['Van testvére?', 'Do you have a sibling? (formal)', '', 'prep', 'All', 'anna-L3,question,interview,sentence,beginner'],
    ['Igen, van egy húgom, Leslie.', 'Yes, I have a younger sister, Leslie.', '', 'prep', 'Larry', 'anna-L3,interview,family,sentence,essential'],
    ['A húgom neve Leslie.', 'My younger sister\'s name is Leslie.', '', 'prep', 'Larry', 'anna-L3,interview,family,sentence,essential'],
];

// ============================================================
// LESSON 4 (2025-09-04): Body, descriptions, possessives with -nak/-nek
// ============================================================
$lesson4 = [
    ['Gyerek', 'child', '', 'prep', 'All', 'anna-L4,noun,beginner'],
    ['Cipő', 'shoe(s)', '', 'prep', 'All', 'anna-L4,noun,beginner'],
    ['Szem', 'eye(s)', '', 'prep', 'All', 'anna-L4,body,beginner'],
    ['Fül', 'ear(s)', '', 'prep', 'All', 'anna-L4,body,beginner'],
    ['Orr', 'nose', '', 'prep', 'All', 'anna-L4,body,beginner'],
    ['Száj', 'mouth', '', 'prep', 'All', 'anna-L4,body,beginner'],
    ['Fog', 'tooth/teeth', '', 'prep', 'All', 'anna-L4,body,beginner'],
    ['Fogorvos', 'dentist', '', 'prep', 'All', 'anna-L4,occupation,beginner'],
    ['Kedves', 'nice, kind', '', 'prep', 'All', 'anna-L4,adjective,beginner'],
    ['Szürke', 'gray', '', 'prep', 'All', 'anna-L4,color,beginner'],
    ['Öreg', 'old', '', 'prep', 'All', 'anna-L4,adjective,beginner'],
    ['Fiatal', 'young', '', 'prep', 'All', 'anna-L4,adjective,beginner'],
    ['Érdekes', 'interesting', '', 'prep', 'All', 'anna-L4,adjective,beginner'],
    ['Dolgozik', 'work (verb)', '', 'prep', 'All', 'anna-L4,verb,beginner'],
    ['Cég', 'company', '', 'prep', 'All', 'anna-L4,noun,beginner'],
    ['Otthon', 'at home', '', 'prep', 'All', 'anna-L4,adverb,beginner'],
    ['Szabadidő', 'free time', '', 'prep', 'All', 'anna-L4,noun,beginner'],
    ['Állampolgárság', 'citizenship', '', 'prep', 'All', 'anna-L4,noun,interview,essential'],
    ['Nyugdíjas', 'retired', '', 'prep', 'All', 'anna-L4,occupation,interview,essential'],
    // Sentences
    ['Korábban orvos voltam, most nyugdíjas vagyok.', 'I used to be a doctor, now I am retired.', '', 'prep', 'All', 'anna-L4,interview,sentence,essential'],
    ['Igen, a nagypapám magyar volt.', 'Yes, my grandfather was Hungarian.', '', 'prep', 'Larry', 'anna-L4,interview,sentence,essential'],
    ['Belgyógyász szakorvos vagyok.', 'I am an internist specialist.', '', 'prep', 'All', 'anna-L4,interview,occupation,sentence,essential'],
    ['Mariának van férje.', 'Maria has a husband.', '', 'prep', 'All', 'anna-L4,nak-nek,possessive,sentence,beginner'],
    ['Larry-nek van felesége.', 'Larry has a wife.', '', 'prep', 'All', 'anna-L4,nak-nek,possessive,sentence,beginner'],
    ['Önnek van autója?', 'Do you have a car? (formal)', '', 'prep', 'All', 'anna-L4,nak-nek,possessive,sentence,beginner'],
    ['Van amerikai állampolgárságom.', 'I have American citizenship.', '', 'prep', 'All', 'anna-L4,interview,sentence,essential'],
    ['Amerikai állampolgár vagyok.', 'I am an American citizen.', '', 'prep', 'All', 'anna-L4,interview,sentence,essential'],
    ['Melyik egyetemen tanult?', 'Which university did you study at?', '', 'prep', 'All', 'anna-L4,question,interview,sentence,beginner'],
    ['A George Washington Egyetemen tanultam.', 'I studied at George Washington University.', '', 'prep', 'Larry', 'anna-L4,interview,sentence,essential'],
    ['A Minnesotai Állami Egyetemen tanultam.', 'I studied at Minnesota State University.', '', 'prep', 'Maria', 'anna-L4,interview,sentence,essential'],
    ['A fiam New Yorkban él.', 'My son lives in New York.', '', 'prep', 'All', 'anna-L4,interview,family,sentence,essential'],
    ['Lesz magyar állampolgárságuk is.', 'They will also have Hungarian citizenship.', '', 'prep', 'All', 'anna-L4,interview,sentence,essential'],
];

// ============================================================
// LESSON 5 (2025-09-18): Siblings, ages, pets, housing
// ============================================================
$lesson5 = [
    ['Belgyógyász', 'internist', '', 'prep', 'All', 'anna-L5,occupation,beginner'],
    ['Háziállat', 'pet', '', 'prep', 'All', 'anna-L5,noun,beginner'],
    ['Féltestvér', 'half sibling', '', 'prep', 'All', 'anna-L5,family,beginner'],
    ['Lakás', 'flat / apartment', '', 'prep', 'All', 'anna-L5,noun,beginner'],
    ['Lakik', 'live (in an apartment)', '', 'prep', 'All', 'anna-L5,verb,beginner'],
    ['Él', 'live (in a country/city)', '', 'prep', 'All', 'anna-L5,verb,beginner'],
    // Sentences
    ['Van testvére?', 'Do you have a sibling?', '', 'prep', 'All', 'anna-L5,question,interview,sentence,beginner'],
    ['Egy húgom van.', 'I have one younger sister.', '', 'prep', 'Larry', 'anna-L5,interview,family,sentence,essential'],
    ['Egy bátyám, egy húgom és két öcsém van.', 'I have one older brother, one younger sister, and two younger brothers.', '', 'prep', 'Maria', 'anna-L5,interview,family,sentence,essential'],
    ['Hány éves a lánya?', 'How old is your daughter?', '', 'prep', 'All', 'anna-L5,question,interview,sentence,beginner'],
    ['Hány éves a fia?', 'How old is your son?', '', 'prep', 'All', 'anna-L5,question,interview,sentence,beginner'],
    ['Van háziállata?', 'Do you have a pet? (formal)', '', 'prep', 'All', 'anna-L5,question,sentence,beginner'],
    ['Igen, van két macskám.', 'Yes, I have two cats.', '', 'prep', 'All', 'anna-L5,sentence,beginner'],
    ['Lakásban lakom.', 'I live in an apartment.', '', 'prep', 'All', 'anna-L5,sentence,beginner'],
    ['Budapesten élek.', 'I live in Budapest.', '', 'prep', 'All', 'anna-L5,location,sentence,beginner'],
];

// ============================================================
// LESSON 6 (2025-09-25): Dates, history, weather, -val/-vel
// ============================================================
$lesson6 = [
    ['Felhő', 'cloud', '', 'prep', 'All', 'anna-L6,weather,beginner'],
    ['Felhős', 'cloudy', '', 'prep', 'All', 'anna-L6,weather,beginner'],
    ['Tavasszal', 'in spring', '', 'prep', 'All', 'anna-L6,season,beginner'],
    ['Nyáron', 'in summer', '', 'prep', 'All', 'anna-L6,season,beginner'],
    ['Ősszel', 'in fall', '', 'prep', 'All', 'anna-L6,season,beginner'],
    ['Télen', 'in winter', '', 'prep', 'All', 'anna-L6,season,beginner'],
    ['Eső', 'rain', '', 'prep', 'All', 'anna-L6,weather,beginner'],
    ['Szél', 'wind', '', 'prep', 'All', 'anna-L6,weather,beginner'],
    ['Köd', 'fog', '', 'prep', 'All', 'anna-L6,weather,beginner'],
    ['Nap', 'sun / day', '', 'prep', 'All', 'anna-L6,weather,beginner'],
    ['Házas', 'married', '', 'prep', 'All', 'anna-L6,adjective,interview,beginner'],
    ['Egyedülálló', 'single', '', 'prep', 'All', 'anna-L6,adjective,beginner'],
    ['Elvált', 'divorced', '', 'prep', 'All', 'anna-L6,adjective,beginner'],
    ['Meghalt', 'died', '', 'prep', 'All', 'anna-L6,verb,beginner'],
    ['Elhunyt', 'passed away', '', 'prep', 'All', 'anna-L6,verb,beginner'],
    ['Rokon', 'relative', '', 'prep', 'All', 'anna-L6,family,beginner'],
    ['Unoka', 'grandchild', '', 'prep', 'All', 'anna-L6,family,beginner'],
    ['Sajt', 'cheese', '', 'prep', 'All', 'anna-L6,food,beginner'],
    ['Tej', 'milk', '', 'prep', 'All', 'anna-L6,food,beginner'],
    ['Leves', 'soup', '', 'prep', 'All', 'anna-L6,food,beginner'],
    ['Gyümölcs', 'fruit', '', 'prep', 'All', 'anna-L6,food,beginner'],
    ['Senki', 'no one / nobody', '', 'prep', 'All', 'anna-L6,pronoun,beginner'],
    // Sentences
    ['Milyen az idő?', 'What\'s the weather like?', '', 'prep', 'All', 'anna-L6,question,weather,sentence,beginner'],
    ['Felhős idő van.', 'It\'s cloudy weather.', '', 'prep', 'All', 'anna-L6,weather,sentence,beginner'],
    ['Hány fok van?', 'What\'s the temperature?', '', 'prep', 'All', 'anna-L6,question,weather,sentence,beginner'],
    ['1957. november 7-én születtem.', 'I was born on November 7, 1957.', '', 'prep', 'Larry', 'anna-L6,interview,date,sentence,essential'],
    ['1964. december 27-én születtem.', 'I was born on December 27, 1964.', '', 'prep', 'Maria', 'anna-L6,interview,date,sentence,essential'],
    ['A lányom 2000. január 19-én született.', 'My daughter was born on January 19, 2000.', '', 'prep', 'All', 'anna-L6,interview,date,sentence,essential'],
    ['A fiam 1998. március 28-án született.', 'My son was born on March 28, 1998.', '', 'prep', 'All', 'anna-L6,interview,date,sentence,essential'],
    ['Az első királyunk Szent István volt.', 'Our first king was Saint Stephen.', '', 'prep', 'All', 'anna-L6,history,culture,sentence,beginner'],
    ['Ez a kokárda piros-fehér-zöld.', 'This cockade is red-white-green.', '', 'prep', 'All', 'anna-L6,culture,sentence,beginner'],
];

// ============================================================
// LESSON 7 (2025-10-02): Time, places, directions, -ba/-be
// ============================================================
$lesson7 = [
    ['Dél', 'noon', '', 'prep', 'All', 'anna-L7,time,beginner'],
    ['Éjfél', 'midnight', '', 'prep', 'All', 'anna-L7,time,beginner'],
    ['Reggel', 'morning', '', 'prep', 'All', 'anna-L7,time,beginner'],
    ['Délelőtt', 'before noon', '', 'prep', 'All', 'anna-L7,time,beginner'],
    ['Délután', 'afternoon', '', 'prep', 'All', 'anna-L7,time,beginner'],
    ['Este', 'evening', '', 'prep', 'All', 'anna-L7,time,beginner'],
    ['Éjszaka', 'night', '', 'prep', 'All', 'anna-L7,time,beginner'],
    ['Hajnal', 'dawn', '', 'prep', 'All', 'anna-L7,time,beginner'],
    ['Férfi', 'man', '', 'prep', 'All', 'anna-L7,noun,beginner'],
    ['Szomorú', 'sad', '', 'prep', 'All', 'anna-L7,adjective,beginner'],
    ['Könnyű', 'easy / light', '', 'prep', 'All', 'anna-L7,adjective,beginner'],
    ['Nehéz', 'difficult / heavy', '', 'prep', 'All', 'anna-L7,adjective,beginner'],
    ['Kevés', 'a little', '', 'prep', 'All', 'anna-L7,quantity,beginner'],
    ['Néhány', 'a few', '', 'prep', 'All', 'anna-L7,quantity,beginner'],
    ['Konyha', 'kitchen', '', 'prep', 'All', 'anna-L7,noun,beginner'],
    ['Könyvtár', 'library', '', 'prep', 'All', 'anna-L7,noun,beginner'],
    ['Gyógyszertár', 'pharmacy', '', 'prep', 'All', 'anna-L7,noun,beginner'],
    ['Szótár', 'dictionary', '', 'prep', 'All', 'anna-L7,noun,beginner'],
    ['Egyetem', 'university', '', 'prep', 'All', 'anna-L7,noun,beginner'],
    ['Ember', 'person / people', '', 'prep', 'All', 'anna-L7,noun,beginner'],
    ['Bútor', 'furniture', '', 'prep', 'All', 'anna-L7,noun,beginner'],
    // Sentences
    ['Az anyám a konyhában van.', 'My mother is in the kitchen.', '', 'prep', 'All', 'anna-L7,ban-ben,sentence,beginner'],
    ['A lakásomban négy ember lakik.', 'Four people live in my apartment.', '', 'prep', 'All', 'anna-L7,ban-ben,sentence,beginner'],
];

// ============================================================
// LESSON 8 (2025-10-09): Conjugation, object -t, colors, clothing, food
// ============================================================
$lesson8 = [
    ['Olvas', 'read', '', 'prep', 'All', 'anna-L8,verb,beginner'],
    ['Gondolkodik', 'think', '', 'prep', 'All', 'anna-L8,verb,beginner'],
    ['Vár', 'wait', '', 'prep', 'All', 'anna-L8,verb,beginner'],
    ['Bolt', 'store', '', 'prep', 'All', 'anna-L8,noun,beginner'],
    ['Világos', 'light (color)', '', 'prep', 'All', 'anna-L8,adjective,beginner'],
    ['Sötét', 'dark', '', 'prep', 'All', 'anna-L8,adjective,beginner'],
    ['Vörös', 'red (hair, wine)', '', 'prep', 'All', 'anna-L8,color,beginner'],
    ['Vörösbor', 'red wine', '', 'prep', 'All', 'anna-L8,food,beginner'],
    ['Ruha', 'clothing / dress', '', 'prep', 'All', 'anna-L8,clothing,beginner'],
    ['Csizma', 'boots', '', 'prep', 'All', 'anna-L8,clothing,beginner'],
    ['Sapka', 'hat', '', 'prep', 'All', 'anna-L8,clothing,beginner'],
    ['Sál', 'scarf', '', 'prep', 'All', 'anna-L8,clothing,beginner'],
    ['Póló', 'T-shirt', '', 'prep', 'All', 'anna-L8,clothing,beginner'],
    ['Kesztyű', 'gloves', '', 'prep', 'All', 'anna-L8,clothing,beginner'],
    ['Papucs', 'slippers', '', 'prep', 'All', 'anna-L8,clothing,beginner'],
    ['Étel', 'meal / food', '', 'prep', 'All', 'anna-L8,food,beginner'],
    ['Étterem', 'restaurant', '', 'prep', 'All', 'anna-L8,noun,beginner'],
    ['Drága', 'expensive', '', 'prep', 'All', 'anna-L8,adjective,beginner'],
    ['Olcsó', 'cheap', '', 'prep', 'All', 'anna-L8,adjective,beginner'],
    ['Mozi', 'cinema', '', 'prep', 'All', 'anna-L8,noun,beginner'],
    ['Színház', 'theatre', '', 'prep', 'All', 'anna-L8,noun,beginner'],
    ['Mindig', 'always', '', 'prep', 'All', 'anna-L8,adverb,beginner'],
    ['Fontos', 'important', '', 'prep', 'All', 'anna-L8,adjective,beginner'],
    ['Kedvenc', 'favorite', '', 'prep', 'All', 'anna-L8,adjective,beginner'],
    // Sentences
    ['Szeretem a férjemet.', 'I love my husband.', '', 'prep', 'All', 'anna-L8,definite-conj,sentence,beginner'],
    ['Szeretem Budapestet.', 'I love Budapest.', '', 'prep', 'All', 'anna-L8,definite-conj,sentence,beginner'],
    ['Mennyibe kerül ez a könyv?', 'How much does this book cost?', '', 'prep', 'All', 'anna-L8,shopping,sentence,beginner'],
    ['Nem olyan jól.', 'Not that well.', '', 'prep', 'All', 'anna-L8,sentence,beginner'],
];

// ============================================================
// LESSON 9 (2025-10-16): Occupations, interview Q&A, daily vocabulary
// ============================================================
$lesson9 = [
    ['Aláír', 'sign (verb)', '', 'prep', 'All', 'anna-L9,verb,interview,beginner'],
    ['Aláírás', 'signature', '', 'prep', 'All', 'anna-L9,noun,interview,beginner'],
    ['Gyógyszer', 'medicine', '', 'prep', 'All', 'anna-L9,noun,beginner'],
    ['Naptár', 'calendar', '', 'prep', 'All', 'anna-L9,noun,beginner'],
    ['Levél', 'letter', '', 'prep', 'All', 'anna-L9,noun,beginner'],
    ['Rendőr', 'policeman', '', 'prep', 'All', 'anna-L9,occupation,beginner'],
    ['Szakács', 'cook', '', 'prep', 'All', 'anna-L9,occupation,beginner'],
    ['Üzletember', 'businessman', '', 'prep', 'All', 'anna-L9,occupation,beginner'],
    ['Pék', 'baker', '', 'prep', 'All', 'anna-L9,occupation,beginner'],
    ['Pékség', 'bakery', '', 'prep', 'All', 'anna-L9,noun,beginner'],
    ['Tűzoltó', 'firefighter', '', 'prep', 'All', 'anna-L9,occupation,beginner'],
    ['Fodrász', 'hairdresser', '', 'prep', 'All', 'anna-L9,occupation,beginner'],
    ['Szemüveges', 'wearing glasses', '', 'prep', 'All', 'anna-L9,adjective,beginner'],
    ['Idős', 'old (polite)', '', 'prep', 'All', 'anna-L9,adjective,beginner'],
    ['Nappali', 'living room', '', 'prep', 'All', 'anna-L9,noun,beginner'],
    ['Ebéd', 'lunch', '', 'prep', 'All', 'anna-L9,food,beginner'],
    // Sentences
    ['Mi a foglalkozása?', 'What is your occupation? (formal)', '', 'prep', 'All', 'anna-L9,question,interview,sentence,beginner'],
    ['Milyen az idő?', 'What\'s the weather like?', '', 'prep', 'All', 'anna-L9,question,weather,sentence,beginner'],
    ['Belgyógyász szakorvos vagyok. Embereket gyógyítok.', 'I am an internist specialist. I heal people.', '', 'prep', 'All', 'anna-L9,interview,sentence,essential'],
    ['Szereti a munkáját?', 'Do you like your work? (formal)', '', 'prep', 'All', 'anna-L9,question,interview,sentence,beginner'],
    ['Igen, szeretem a munkámat.', 'Yes, I like my work.', '', 'prep', 'All', 'anna-L9,interview,sentence,beginner'],
    ['Nyugdíjas orvos vagyok. Szerettem az embereken segíteni.', 'I am a retired doctor. I liked to help people.', '', 'prep', 'All', 'anna-L9,interview,sentence,essential'],
];

// ============================================================
// LESSON 10 (2025-10-23): Interview practice, formal Q&A
// ============================================================
$lesson10 = [
    ['Tél', 'winter', '', 'prep', 'All', 'anna-L10,season,beginner'],
    ['Nyár', 'summer', '', 'prep', 'All', 'anna-L10,season,beginner'],
    ['Ősz', 'fall/autumn', '', 'prep', 'All', 'anna-L10,season,beginner'],
    ['Tavasz', 'spring', '', 'prep', 'All', 'anna-L10,season,beginner'],
    ['Jelenleg', 'currently', '', 'prep', 'All', 'anna-L10,adverb,interview,beginner'],
    // Sentences
    ['Itt van az útlevele?', 'Is your passport here? (formal)', '', 'prep', 'All', 'anna-L10,interview,sentence,beginner'],
    ['Igen, itt van az útlevelem.', 'Yes, here is my passport.', '', 'prep', 'All', 'anna-L10,interview,sentence,beginner'],
    ['Mikor született?', 'When were you born?', '', 'prep', 'All', 'anna-L10,question,interview,sentence,beginner'],
    ['Hol végezte tanulmányait?', 'Where did you complete your studies?', '', 'prep', 'All', 'anna-L10,question,interview,sentence,beginner'],
    ['Jelenleg Californiában lakom.', 'I currently live in California.', '', 'prep', 'All', 'anna-L10,interview,sentence,essential'],
    ['Nyugdíjas orvos vagyok.', 'I am a retired doctor.', '', 'prep', 'All', 'anna-L10,interview,sentence,essential'],
    ['A szüleim sajnos már nem élnek, de az édesanyám tanár volt, az édesapám pedig ügyvéd.', 'My parents unfortunately are no longer alive, but my mother was a teacher, my father was a lawyer.', '', 'prep', 'Larry', 'anna-L10,interview,sentence,essential'],
    ['Az édesanyám nővér volt, az édesapám pedig mérnök volt.', 'My mother was a nurse, my father was an engineer.', '', 'prep', 'Maria', 'anna-L10,interview,sentence,essential'],
    ['Hány testvére van?', 'How many siblings do you have? (formal)', '', 'prep', 'All', 'anna-L10,question,interview,sentence,beginner'],
    ['Négy testvérem van.', 'I have four siblings.', '', 'prep', 'Maria', 'anna-L10,interview,sentence,essential'],
    ['Le tudja vezetni magyar származását?', 'Can you trace your Hungarian ancestry?', '', 'prep', 'All', 'anna-L10,question,interview,sentence,essential'],
    ['Az apai nagypapám, Bernstein Edward magyar volt.', 'My paternal grandfather, Bernstein Edward, was Hungarian.', '', 'prep', 'Larry', 'anna-L10,interview,sentence,essential'],
    ['A férjem, Larry magyar származású.', 'My husband, Larry, is of Hungarian origin.', '', 'prep', 'Maria', 'anna-L10,interview,sentence,essential'],
];

// ============================================================
// LESSON 11 (2025-10-30): Daily routine, food vocabulary
// ============================================================
$lesson11 = [
    ['Napirend', 'daily routine', '', 'prep', 'All', 'anna-L11,noun,beginner'],
    ['Felkel', 'wake up / get up', '', 'prep', 'All', 'anna-L11,verb,beginner'],
    ['Hazaér', 'get home', '', 'prep', 'All', 'anna-L11,verb,beginner'],
    ['Elfoglalt', 'busy', '', 'prep', 'All', 'anna-L11,adjective,beginner'],
    ['Általában', 'usually', '', 'prep', 'All', 'anna-L11,adverb,beginner'],
    ['Soha', 'never', '', 'prep', 'All', 'anna-L11,adverb,beginner'],
    ['Néha', 'sometimes', '', 'prep', 'All', 'anna-L11,adverb,beginner'],
    ['Sétál', 'walk / stroll', '', 'prep', 'All', 'anna-L11,verb,beginner'],
    ['Rántotta', 'scrambled eggs', '', 'prep', 'All', 'anna-L11,food,beginner'],
    ['Tükörtojás', 'fried egg', '', 'prep', 'All', 'anna-L11,food,beginner'],
    ['Főtt tojás', 'boiled egg', '', 'prep', 'All', 'anna-L11,food,beginner'],
    ['Gabonapehely', 'cereal', '', 'prep', 'All', 'anna-L11,food,beginner'],
    ['Hal', 'fish', '', 'prep', 'All', 'anna-L11,food,beginner'],
    ['Hús', 'meat', '', 'prep', 'All', 'anna-L11,food,beginner'],
    ['Vaj', 'butter', '', 'prep', 'All', 'anna-L11,food,beginner'],
    ['Marhapörkölt', 'beef stew', '', 'prep', 'All', 'anna-L11,food,culture,beginner'],
    ['Túrós csusza', 'cottage cheese pasta', '', 'prep', 'All', 'anna-L11,food,culture,beginner'],
    ['Rántott hús', 'Wiener Schnitzel', '', 'prep', 'All', 'anna-L11,food,culture,beginner'],
    ['Lecsó', 'Hungarian ratatouille', '', 'prep', 'All', 'anna-L11,food,culture,beginner'],
    ['Ásványvíz', 'mineral water', '', 'prep', 'All', 'anna-L11,food,beginner'],
    // Sentences
    ['Hány órakor?', 'At what time?', '', 'prep', 'All', 'anna-L11,question,time,sentence,beginner'],
    ['Általában 3 órát tanulok magyarul.', 'I usually study Hungarian for 3 hours.', '', 'prep', 'All', 'anna-L11,routine,sentence,beginner'],
    ['Szeretek elmosogatni.', 'I like to do the dishes.', '', 'prep', 'Larry', 'anna-L11,routine,sentence,beginner'],
    ['Nem szeretek porszívózni.', 'I don\'t like to vacuum.', '', 'prep', 'Larry', 'anna-L11,routine,sentence,beginner'],
];

// ============================================================
// LESSON 12 (2025-11-06): Past tense, definite conjugation, interview deep dive
// ============================================================
$lesson12 = [
    ['Összeházasodik', 'get married', '', 'prep', 'All', 'anna-L12,verb,beginner'],
    ['Nős', 'married (for men)', '', 'prep', 'All', 'anna-L12,adjective,beginner'],
    // Sentences
    ['1957-ben születtem.', 'I was born in 1957.', '', 'prep', 'Larry', 'anna-L12,interview,past-tense,sentence,essential'],
    ['Jelenleg Californiában élek.', 'I currently live in California.', '', 'prep', 'All', 'anna-L12,interview,sentence,essential'],
    ['Házas vagyok.', 'I am married.', '', 'prep', 'All', 'anna-L12,interview,sentence,essential'],
    ['Két gyerekem van.', 'I have two children.', '', 'prep', 'All', 'anna-L12,interview,sentence,essential'],
    ['Meséljen a munkájáról?', 'Tell me about your work? (formal)', '', 'prep', 'All', 'anna-L12,question,interview,sentence,beginner'],
    ['Belgyógyász szakorvos vagyok. Otthon dolgozom. Orvosokkal konzultálok.', 'I am an internist. I work from home. I consult with doctors.', '', 'prep', 'All', 'anna-L12,interview,sentence,essential'],
    ['Az apai nagyapám, Bernstein Edward Magyarországon, Polenában született 1901-ben.', 'My paternal grandfather, Bernstein Edward, was born in Polena, Hungary in 1901.', '', 'prep', 'Larry', 'anna-L12,interview,sentence,essential'],
    ['1920 előtt, a Trianon előtt Polena Magyarország része volt, de ma már Ukrajna része.', 'Before 1920, before Trianon, Polena was part of Hungary, but today it is part of Ukraine.', '', 'prep', 'Larry', 'anna-L12,interview,history,sentence,essential'],
    ['Szeretek Californiában élni.', 'I like living in California.', '', 'prep', 'All', 'anna-L12,interview,sentence,beginner'],
    ['Mert sok park, étterem van ott. És közel van az óceánhoz.', 'Because there are many parks, restaurants there. And it\'s close to the ocean.', '', 'prep', 'All', 'anna-L12,interview,sentence,beginner'],
    ['Mivel tölti a szabadidejét?', 'How do you spend your free time? (formal)', '', 'prep', 'All', 'anna-L12,question,interview,sentence,beginner'],
    ['Szeretek a számítógépen dolgozni, kertészkedni és teniszezni.', 'I like to work on the computer, garden, and play tennis.', '', 'prep', 'Larry', 'anna-L12,interview,sentence,essential'],
    ['Járt már Magyarországon?', 'Have you been to Hungary?', '', 'prep', 'All', 'anna-L12,question,interview,sentence,essential'],
    ['Igen, voltam Magyarországon 2025 decemberében.', 'Yes, I was in Hungary in December 2025.', '', 'prep', 'All', 'anna-L12,interview,sentence,essential'],
];

// ============================================================
// LESSON 13 (2025-11-20): Comparatives, definite review
// ============================================================
$lesson13 = [
    ['Remél', 'hope', '', 'prep', 'All', 'anna-L13,verb,beginner'],
    ['Főleg', 'especially', '', 'prep', 'All', 'anna-L13,adverb,beginner'],
    ['Szívesen', 'gladly / you\'re welcome', '', 'prep', 'All', 'anna-L13,adverb,beginner'],
    ['Szív', 'heart', '', 'prep', 'All', 'anna-L13,noun,beginner'],
    ['Fordít', 'translate', '', 'prep', 'All', 'anna-L13,verb,beginner'],
    ['Korán', 'early', '', 'prep', 'All', 'anna-L13,adverb,beginner'],
    ['Sorozat', 'series', '', 'prep', 'All', 'anna-L13,noun,beginner'],
    ['Dal', 'song', '', 'prep', 'All', 'anna-L13,noun,beginner'],
    ['Cikk', 'article', '', 'prep', 'All', 'anna-L13,noun,beginner'],
    ['Hallgat', 'listen to', '', 'prep', 'All', 'anna-L13,verb,beginner'],
    ['Szerencsés', 'lucky', '', 'prep', 'All', 'anna-L13,adjective,beginner'],
    ['Szerencsére', 'fortunately', '', 'prep', 'All', 'anna-L13,adverb,beginner'],
    // Sentences
    ['Jó - jobb - legjobb', 'good - better - best', '', 'prep', 'All', 'anna-L13,comparative,sentence,beginner'],
    ['Rossz - rosszabb - legrosszabb', 'bad - worse - worst', '', 'prep', 'All', 'anna-L13,comparative,sentence,beginner'],
    ['Szép - szebb - legszebb', 'beautiful - more beautiful - most beautiful', '', 'prep', 'All', 'anna-L13,comparative,sentence,beginner'],
];

// ============================================================
// LESSON 14 (2025-12-04): Full interview practice, family details
// ============================================================
$lesson14 = [
    ['Unokatestvér', 'cousin', '', 'prep', 'All', 'anna-L14,family,beginner'],
    ['Ezért', 'that\'s why / therefore', '', 'prep', 'All', 'anna-L14,conjunction,beginner'],
    ['Büszke', 'proud', '', 'prep', 'All', 'anna-L14,adjective,interview,beginner'],
    ['Gyökereim', 'my roots', '', 'prep', 'All', 'anna-L14,noun,interview,beginner'],
    ['Költözik', 'move (to somewhere)', '', 'prep', 'All', 'anna-L14,verb,beginner'],
    ['Jelent', 'mean (verb)', '', 'prep', 'All', 'anna-L14,verb,beginner'],
    ['Egyszerű', 'simple', '', 'prep', 'All', 'anna-L14,adjective,beginner'],
    ['Kérdés', 'question', '', 'prep', 'All', 'anna-L14,noun,beginner'],
    ['Kérelem', 'application / request', '', 'prep', 'All', 'anna-L14,noun,interview,beginner'],
    // Sentences
    ['Az USA-ban, California államban, Laguna Niguelben élek.', 'I live in the USA, in the state of California, in Laguna Niguel.', '', 'prep', 'All', 'anna-L14,interview,sentence,essential'],
    ['A szüleim már nem élnek.', 'My parents are no longer alive.', '', 'prep', 'All', 'anna-L14,interview,sentence,essential'],
    ['Házas vagyok. Két gyerekem van.', 'I am married. I have two children.', '', 'prep', 'All', 'anna-L14,interview,sentence,essential'],
    ['Hány testvére van?', 'How many siblings do you have?', '', 'prep', 'All', 'anna-L14,question,interview,sentence,beginner'],
    ['Vannak unokatestvérei?', 'Do you have cousins?', '', 'prep', 'All', 'anna-L14,question,interview,sentence,beginner'],
    ['Sok unokatestvérem van.', 'I have many cousins.', '', 'prep', 'All', 'anna-L14,interview,sentence,beginner'],
    ['Miért szeretne magyar állampolgár lenni?', 'Why would you like to become a Hungarian citizen?', '', 'prep', 'All', 'anna-L14,question,interview,sentence,essential'],
    ['Mert a nagypapám magyar volt. És büszke vagyok a magyar gyökereimre.', 'Because my grandfather was Hungarian. And I am proud of my Hungarian roots.', '', 'prep', 'Larry', 'anna-L14,interview,sentence,essential'],
    ['Mert a férjem magyar származású. Ezért én is szeretnék magyar állampolgár lenni.', 'Because my husband is of Hungarian origin. That\'s why I also want to be a Hungarian citizen.', '', 'prep', 'Maria', 'anna-L14,interview,sentence,essential'],
    ['Mikor végezte el az egyetemet?', 'When did you finish university?', '', 'prep', 'All', 'anna-L14,question,interview,sentence,beginner'],
    ['1990-ben végeztem el az egyetemet.', 'I finished university in 1990.', '', 'prep', 'Larry', 'anna-L14,interview,sentence,essential'],
    ['1992-ben végeztem el az egyetemet.', 'I finished university in 1992.', '', 'prep', 'Maria', 'anna-L14,interview,sentence,essential'],
    ['1986-tól 1990-ig tanultam a George Washington Egyetemen.', 'I studied at George Washington University from 1986 to 1990.', '', 'prep', 'Larry', 'anna-L14,interview,sentence,essential'],
    ['1988-tól 1992-ig tanultam a Minnesotai Állami Egyetemen.', 'I studied at Minnesota State University from 1988 to 1992.', '', 'prep', 'Maria', 'anna-L14,interview,sentence,essential'],
    ['Hogy hívták az édesanyját?', 'What was your mother\'s name?', '', 'prep', 'All', 'anna-L14,question,interview,sentence,beginner'],
    ['Az édesanyám neve Marlene volt.', 'My mother\'s name was Marlene.', '', 'prep', 'Larry', 'anna-L14,interview,sentence,essential'],
    ['Az édesapám neve Robert volt.', 'My father\'s name was Robert.', '', 'prep', 'Larry', 'anna-L14,interview,sentence,essential'],
    ['Az édesanyám 2022-ben hunyt el.', 'My mother passed away in 2022.', '', 'prep', 'Larry', 'anna-L14,interview,sentence,essential'],
    ['Az édesapám 2021-ben hunyt el.', 'My father passed away in 2021.', '', 'prep', 'Larry', 'anna-L14,interview,sentence,essential'],
];

// ============================================================
// LESSON 15 (2026-01-08): Hobbies, szeret + infinitive, culture Q&A
// ============================================================
$lesson15 = [
    // Sentences — interview Q&A
    ['Mi a kedvenc magyar szava?', 'What is your favorite Hungarian word?', '', 'prep', 'All', 'anna-L15,question,interview,sentence,beginner'],
    ['A kedvenc magyar szavam a csütörtök.', 'My favorite Hungarian word is Thursday.', '', 'prep', 'All', 'anna-L15,interview,sentence,beginner'],
    ['Ki Magyarország miniszterelnöke?', 'Who is Hungary\'s prime minister?', '', 'prep', 'All', 'anna-L15,question,culture,sentence,essential'],
    ['Magyarország miniszterelnöke Orbán Viktor.', 'Hungary\'s prime minister is Viktor Orbán.', '', 'prep', 'All', 'anna-L15,culture,sentence,essential'],
    ['Ki Magyarország köztársasági elnöke?', 'Who is Hungary\'s president?', '', 'prep', 'All', 'anna-L15,question,culture,sentence,essential'],
    ['Sulyok Tamás.', 'Tamás Sulyok.', '', 'prep', 'All', 'anna-L15,culture,sentence,essential'],
    ['Ki Budapest főpolgármestere?', 'Who is Budapest\'s mayor?', '', 'prep', 'All', 'anna-L15,question,culture,sentence,essential'],
    ['Karácsony Gergely.', 'Gergely Karácsony.', '', 'prep', 'All', 'anna-L15,culture,sentence,essential'],
    ['Mikor költözött a magyar felmenője az USA-ba?', 'When did your Hungarian ancestor move to the USA?', '', 'prep', 'All', 'anna-L15,question,interview,sentence,essential'],
    ['1920-ban.', 'In 1920.', '', 'prep', 'Larry', 'anna-L15,interview,sentence,essential'],
    ['Mi volt a magyar felmenőjének a neve?', 'What was your Hungarian ancestor\'s name?', '', 'prep', 'All', 'anna-L15,question,interview,sentence,essential'],
    ['A neve Bernstein Edward volt.', 'His name was Bernstein Edward.', '', 'prep', 'Larry', 'anna-L15,interview,sentence,essential'],
    ['Ki volt magyar a családjában?', 'Who was Hungarian in your family?', '', 'prep', 'All', 'anna-L15,question,interview,sentence,essential'],
    ['A férjem nagypapája magyar volt.', 'My husband\'s grandfather was Hungarian.', '', 'prep', 'Maria', 'anna-L15,interview,sentence,essential'],
    ['Miért tanul magyarul?', 'Why are you learning Hungarian?', '', 'prep', 'All', 'anna-L15,question,interview,sentence,essential'],
    ['Mert magyar származású vagyok, és szeretnék magyar állampolgár lenni.', 'Because I am of Hungarian origin, and I want to become a Hungarian citizen.', '', 'prep', 'Larry', 'anna-L15,interview,sentence,essential'],
    ['Mi a családi neve?', 'What is your family name?', '', 'prep', 'All', 'anna-L15,question,interview,sentence,beginner'],
    ['A családi nevem Bernstein.', 'My family name is Bernstein.', '', 'prep', 'Larry', 'anna-L15,interview,sentence,essential'],
    ['Mit tud Magyarországról?', 'What do you know about Hungary?', '', 'prep', 'All', 'anna-L15,question,interview,sentence,essential'],
    ['Budapest szép város. Az ételek finomak, sok kávézó és étterem van. Az épületek nagyon szépek mindenhol.', 'Budapest is a beautiful city. The food is delicious, there are many cafés and restaurants. The buildings are very beautiful everywhere.', '', 'prep', 'All', 'anna-L15,interview,sentence,essential'],
    ['Mit eszik reggelire?', 'What do you eat for breakfast?', '', 'prep', 'All', 'anna-L15,question,sentence,beginner'],
    ['Tojást és kenyeret eszem reggelire. És kávét iszom.', 'I eat eggs and bread for breakfast. And I drink coffee.', '', 'prep', 'All', 'anna-L15,sentence,beginner'],
    // Hobbies
    ['Szeretek könyvet olvasni, tévézni és zenét hallgatni.', 'I like to read books, watch TV, and listen to music.', '', 'prep', 'All', 'anna-L15,hobby,sentence,beginner'],
    ['Szeretek sétálni és teniszezni.', 'I like to walk and play tennis.', '', 'prep', 'All', 'anna-L15,hobby,sentence,beginner'],
    ['Mi az Ön hobbija?', 'What is your hobby? (formal)', '', 'prep', 'All', 'anna-L15,question,interview,sentence,beginner'],
    ['Szokott sportolni?', 'Do you usually exercise?', '', 'prep', 'All', 'anna-L15,question,sentence,beginner'],
    ['Igen, szoktam teniszezni.', 'Yes, I usually play tennis.', '', 'prep', 'All', 'anna-L15,hobby,sentence,beginner'],
    ['Milyen filmeket szokott nézni?', 'What kind of movies do you usually watch?', '', 'prep', 'All', 'anna-L15,question,sentence,beginner'],
    ['Elismételné legyen szíves lassabban?', 'Could you please repeat it slower?', '', 'prep', 'All', 'anna-L15,polite,interview,sentence,essential'],
];

// ============================================================
// LESSON 16 (2026-01-22): Past tense, interview deep Q&A, Budapest
// ============================================================
$lesson16 = [
    // Sentences — interview Q&A
    ['Melyik felmenője volt magyar?', 'Which ancestor of yours was Hungarian?', '', 'prep', 'All', 'anna-L16,question,interview,sentence,essential'],
    ['Az apai nagyapám magyar volt.', 'My paternal grandfather was Hungarian.', '', 'prep', 'Larry', 'anna-L16,interview,sentence,essential'],
    ['A férjem nagyapja magyar volt.', 'My husband\'s grandfather was Hungarian.', '', 'prep', 'Maria', 'anna-L16,interview,sentence,essential'],
    ['Munkács mellett.', 'Near Munkács.', '', 'prep', 'Larry', 'anna-L16,interview,sentence,essential'],
    ['Ez a város ma már Ukrajna része.', 'This city is now part of Ukraine.', '', 'prep', 'Larry', 'anna-L16,interview,sentence,essential'],
    ['Miért emigrált a nagyapja Amerikába?', 'Why did your grandfather emigrate to America?', '', 'prep', 'All', 'anna-L16,question,interview,sentence,essential'],
    ['Trianon és az antiszemitizmus miatt, mert zsidó volt.', 'Because of Trianon and antisemitism, because he was Jewish.', '', 'prep', 'Larry', 'anna-L16,interview,sentence,essential'],
    ['Járt már Magyarországon?', 'Have you been to Hungary before?', '', 'prep', 'All', 'anna-L16,question,interview,sentence,essential'],
    ['Igen, 2025 decemberében jártam Budapesten.', 'Yes, I was in Budapest in December 2025.', '', 'prep', 'All', 'anna-L16,interview,sentence,essential'],
    ['Hogy tetszik Önnek Budapest?', 'How do you like Budapest? (formal)', '', 'prep', 'All', 'anna-L16,question,interview,sentence,essential'],
    ['Nagyon tetszik Budapest. Budapest szép város.', 'I really like Budapest. Budapest is a beautiful city.', '', 'prep', 'All', 'anna-L16,interview,sentence,essential'],
    ['Szeretnék magyar állampolgár lenni, mert szeretném megőrizni a családom kultúráját.', 'I want to become a Hungarian citizen because I want to preserve my family\'s culture.', '', 'prep', 'All', 'anna-L16,interview,sentence,essential'],
    ['Mit gondol a magyar nyelvről?', 'What do you think about the Hungarian language?', '', 'prep', 'All', 'anna-L16,question,interview,sentence,essential'],
    ['Szép nyelv, de nehéz. Nagyon logikus.', 'It\'s a beautiful language, but difficult. Very logical.', '', 'prep', 'All', 'anna-L16,interview,sentence,essential'],
    ['Milyen az idő ma Laguna Niguelben?', 'What\'s the weather like today in Laguna Niguel?', '', 'prep', 'All', 'anna-L16,question,weather,interview,sentence,beginner'],
    ['Hány betű van a magyar ábécében?', 'How many letters are in the Hungarian alphabet?', '', 'prep', 'All', 'anna-L16,question,culture,sentence,essential'],
    ['44 betű van.', 'There are 44 letters.', '', 'prep', 'All', 'anna-L16,culture,sentence,essential'],
    ['Milyen színű a magyar zászló?', 'What color is the Hungarian flag?', '', 'prep', 'All', 'anna-L16,question,culture,sentence,essential'],
    ['Piros-fehér-zöld.', 'Red-white-green.', '', 'prep', 'All', 'anna-L16,culture,sentence,essential'],
    ['Hogy hívják a magyar tengert?', 'What is the "Hungarian sea" called?', '', 'prep', 'All', 'anna-L16,question,culture,sentence,essential'],
    ['A neve Balaton. De a Balaton egy tó.', 'Its name is Balaton. But Balaton is a lake.', '', 'prep', 'All', 'anna-L16,culture,sentence,essential'],
];

// ============================================================
// Merge all lessons and insert
// ============================================================
$allLessons = array_merge(
    $lesson1, $lesson2, $lesson3, $lesson4, $lesson5, $lesson6,
    $lesson7, $lesson8, $lesson9, $lesson10, $lesson11, $lesson12,
    $lesson13, $lesson14, $lesson15, $lesson16
);

$stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_en, answer_hu, category, `who`, tags, import_batch) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_en=VALUES(answer_en), answer_hu=VALUES(answer_hu), tags=VALUES(tags)");

foreach ($allLessons as $r) {
    $stmt->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $batch);
    $stmt->execute();
    if (strpos($r[5], 'sentence') !== false) {
        $counts['sentences']++;
    } else {
        $counts['vocab']++;
    }
}
$stmt->close();
$conn->close();

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Anna's Vocab Sheets Import Complete</h2>";
echo "<p>Batch: <code>$batch</code></p>";
echo "<ul>";
echo "<li>Vocabulary words: {$counts['vocab']}</li>";
echo "<li>Full sentences: {$counts['sentences']}</li>";
echo "<li>Total: " . count($allLessons) . "</li>";
echo "</ul>";
echo "<p>Tagged: <code>anna-LN</code> + topic + <code>beginner/essential</code></p>";
echo "<p>Covers lessons 1-16 (Aug 2025 – Jan 2026)</p>";
echo "<p>Safe to re-run.</p>";
?>
