#!/usr/bin/env python3
"""
POST all Notion data to Hug Coach AJAX endpoints on HostRocket.
Safe to re-run: endpoints use INSERT IGNORE / duplicate detection.
"""
import urllib.request
import urllib.parse
import json
import sys

BASE = "https://ambersight.com/hug/"
PHRASE_URL = BASE + "?ajax=1&action=save_phrase"
KNOWLEDGE_URL = BASE + "?ajax=1&action=save_knowledge"

stats = {"imported": 0, "skipped": 0, "errors": 0}

def post_phrase(question_hu, answer_en, answer_hu="", category="prep"):
    data = urllib.parse.urlencode({
        "question_hu": question_hu,
        "answer_en": answer_en,
        "answer_hu": answer_hu,
        "category": category,
    }).encode()
    try:
        req = urllib.request.Request(PHRASE_URL, data=data, method="POST")
        with urllib.request.urlopen(req, timeout=15) as resp:
            result = json.loads(resp.read())
            if result.get("error"):
                print(f"  ERROR: {result['error']} | {question_hu[:50]}")
                stats["errors"] += 1
            elif "Already" in result.get("msg", ""):
                stats["skipped"] += 1
            else:
                stats["imported"] += 1
    except Exception as e:
        print(f"  FAIL: {e} | {question_hu[:50]}")
        stats["errors"] += 1

def post_knowledge(category, title_hu, title_en, content_hu, content_en, key_fact):
    data = urllib.parse.urlencode({
        "category": category,
        "title_hu": title_hu,
        "title_en": title_en,
        "content_hu": content_hu,
        "content_en": content_en,
        "key_fact": key_fact,
    }).encode()
    try:
        req = urllib.request.Request(KNOWLEDGE_URL, data=data, method="POST")
        with urllib.request.urlopen(req, timeout=15) as resp:
            result = json.loads(resp.read())
            if result.get("error"):
                print(f"  ERROR: {result['error']} | {title_hu[:50]}")
                stats["errors"] += 1
            elif "Already" in result.get("msg", ""):
                stats["skipped"] += 1
            else:
                stats["imported"] += 1
    except Exception as e:
        print(f"  FAIL: {e} | {title_hu[:50]}")
        stats["errors"] += 1

# ============================================================
# 1. INTERVIEW Q&A (27 usable questions with answers)
# ============================================================
print("=== Interview Q&A ===")
interview = [
    ("Kérem, mondja el, mi a foglalkozása.", "Please tell me what your occupation is.", "[TK-confirm] a foglalkozásom.", "interview"),
    ("Kérem, mondja el, mi az édesanyja leánykori neve.", "Please tell me your mother's maiden name.", "Az édesanyám leánykori neve [TK-confirm].", "interview"),
    ("Kérem, mondja el, miért szeretne magyar állampolgár lenni.", "Please tell me why you would like to become a Hungarian citizen.", "Magyar állampolgár szeretnék lenni, mert magyar származású vagyok, és szeretném megőrizni a családi kötődést és a nyelvet.", "interview"),
    ("Kérem, mondja el, milyen gyakran beszél magyarul.", "Please tell me how often you speak Hungarian.", "Naponta beszélek magyarul. / Hetente többször beszélek magyarul.", "interview"),
    ("Kérem, mondja el, mi a teljes neve.", "Please tell me your full name.", "A teljes nevem [TK-confirm].", "interview"),
    ("Kérem, mondja el, mi az édesapja neve.", "Please tell me your father's name.", "Az édesapám neve [TK-confirm].", "interview"),
    ("Kérem, mondja el, mióta lakik ezen a címen.", "Please tell me since when you have been living at this address.", "[TK-confirm év] óta lakom ezen a címen.", "interview"),
    ("Kérem, mutassa be az okmányait. Van útlevele vagy személyi igazolványa?", "Please show me your documents. Do you have a passport or an ID card?", "Igen. Itt van az útlevelem.", "interview"),
    ("Kérem, mondja el, járt-e már Magyarországon. Mikor és meddig volt ott?", "Please tell me whether you have been to Hungary before.", "Igen, jártam Magyarországon.", "interview"),
    ("Kérem, mondja el, honnan származik a családja Magyarországon.", "Please tell me where your family comes from in Hungary.", "A családom [TK-confirm település]-ból származik.", "interview"),
    ("Kérem, mondja el, hol dolgozik.", "Please tell me where you work.", "[TK-confirm cég]-nél dolgozom.", "interview"),
    ("Kérem, mondja el, mikor és hol született.", "Please tell me when and where you were born.", "[TK-confirm dátum]-én születtem [TK-confirm város]-ban.", "interview"),
    ("Kérem, mondja el, van-e gyermeke, és hány.", "Please tell me whether you have any children.", "Igen, [TK-confirm szám] gyermekem van. / Nem, nincs gyermekem.", "interview"),
    ("Kérem, mondja el, hol lakik jelenleg.", "Please tell me where you currently live.", "Jelenleg [TK-confirm város]-ban lakom.", "interview"),
    ("Kérem, mondja el, házas-e.", "Please tell me whether you are married.", "Igen, házas vagyok. / Nem, nem vagyok házas.", "interview"),
    ("Hány éves?", "How old are you?", "Kilencvenegy éves vagyok.", "interview"),
    ("Mi a testvére neve?", "What is your sibling's name?", "Az öcséim neve John és Peter.", "interview"),
    ("Van testvére?", "Do you have any siblings?", "Igen, van két öcsém.", "interview"),
    ("Mikor született?", "When were you born?", "Ezerkilencszázharmincnégyben születtem.", "interview"),
    ("Mi az édesanyja neve?", "What is your mother's name?", "Az édesanyám neve Maria Angelos volt.", "interview"),
    ("Hol született?", "Where were you born?", "Medellínben, Kolumbiában születtem.", "interview"),
    ("Miben segíthetek?", "How can I help you?", "Állampolgársági interjúra jöttem.", "interview"),
    ("Mi a neve? / Hogy hívják?", "What is your name?", "A nevem Marlene Angelos.", "interview"),
    ("Mi az édesapja neve?", "What is your father's name?", "Az édesapám neve George Angelos volt.", "interview"),
    ("Elhozta az útlevelét?", "Did you bring your passport?", "Igen, elhoztam az útlevelemet.", "interview"),
    ("Hol lakik most, és mióta él ott?", "Where do you live now, and since when?", "Jelenleg Los Angelesben lakom, Kaliforniában, 2015 óta.", "interview"),
    ("Az Ön családja Magyarország melyik részéről származik, és mivel foglalkoztak a felmenők?", "Which part of Hungary does your family come from?", "Paternal nagyapám magyar származású volt.", "interview"),
]
for q, a_en, a_hu, cat in interview:
    post_phrase(q, a_en, a_hu, cat)
print(f"  Done: {stats}")

# Reset per-section
section_stats = dict(stats)

# ============================================================
# 2. CITIZENSHIP WORDS (36 items)
# ============================================================
print("\n=== Citizenship Interview Words ===")
prev = dict(stats)
citizenship = [
    ("nagykövetség", "embassy", "A magyar nagykövetségen voltam."),
    ("konzulátus", "consulate", ""),
    ("kérelem", "application / request", "Állampolgársági kérelmet adtam be."),
    ("űrlap / nyomtatvány", "form", "Ki kell töltenie az űrlapot."),
    ("aláírás", "signature", "Ide kérem az aláírását."),
    ("aláír", "to sign", "Kérem, írja alá!"),
    ("útlevél", "passport", "Van érvényes útlevele?"),
    ("személyi igazolvány", "ID card", ""),
    ("jogosítvány / vezetői engedély", "driver's license", "Van amerikai jogosítványom."),
    ("születési anyakönyvi kivonat", "birth certificate", "Be kell mutatnia a születési anyakönyvi kivonatot."),
    ("házassági anyakönyvi kivonat", "marriage certificate", ""),
    ("válási papír / ítélet", "divorce decree", "Van válási ítélete?"),
    ("állampolgárság", "citizenship", "Magyar állampolgárságot kérek."),
    ("állampolgár", "citizen", "Amerikai állampolgár vagyok."),
    ("útlevélkérelem", "passport application", ""),
    ("dátum", "date", "Mi a mai dátum?"),
    ("pecsét", "stamp / seal", "Kérem a pecsétet ide!"),
    ("hivatal", "office / bureau", "Az okmányirodában dolgozik."),
    ("okmány", "document / ID", ""),
    ("okmányiroda", "document office", ""),
    ("nyilatkozat", "declaration / statement", "Aláírtam a nyilatkozatot."),
    ("bizonyítvány", "certificate / diploma", ""),
    ("hiteles másolat", "certified copy", ""),
    ("fordítás", "translation", "Hivatalos fordítást kérek."),
    ("fordító", "translator", "A fordító aláírta a dokumentumot."),
    ("hivatalos", "official / formal", "Hivatalos dokumentum."),
    ("érvényes", "valid", "Az útlevele még érvényes."),
    ("lejárt", "expired", "A jogosítványom lejárt."),
    ("irat / dokumentum", "record / document", ""),
    ("jelentkezés", "application / registration", ""),
    ("kérelmező", "applicant", ""),
    ("tanú", "witness", ""),
    ("állandó lakcím", "permanent address", "Appears on all Hungarian IDs."),
    ("ideiglenes lakcím", "temporary address", ""),
    ("születési hely", "place of birth", ""),
    ("keltezés", "date of issue / issuance", ""),
]
for q, a_en, a_hu in citizenship:
    post_phrase(q, a_en, a_hu)
new = stats["imported"] - prev["imported"]
skip = stats["skipped"] - prev["skipped"]
print(f"  Citizenship words: {new} new, {skip} skipped")

# ============================================================
# 3. SENTENCES TO PRACTICE (13 items)
# ============================================================
print("\n=== Sentences to Practice ===")
prev = dict(stats)
sentences = [
    ("Korábban orvos voltam, most nyugdíjas vagyok.", "I used to be a doctor, now I am retired."),
    ("Március tizenötödikén ünneplünk.", "We celebrate on March 15th."),
    ("Január elsején születtem.", "I was born on January 1st."),
    ("Annával beszélek.", "I am speaking with Anna."),
    ("Ez a ház nagy.", "This house is big."),
    ("Hány könyv van az asztalon?", "How many books are on the table?"),
    ("Ez a negyedik feladat.", "This is the fourth task."),
    ("Mennyi pénz kell?", "How much money is needed?"),
    ("Busszal megyek.", "I am going by bus."),
    ("Napos az idő.", "The weather is sunny."),
    ("Ő a hatodik.", "They are sixth."),
    ("Esős idő van.", "It is rainy weather."),
    ("Az a könyv érdekes.", "That book is interesting."),
]
for q, a_en in sentences:
    post_phrase(q, a_en)
new = stats["imported"] - prev["imported"]
skip = stats["skipped"] - prev["skipped"]
print(f"  Sentences: {new} new, {skip} skipped")

# ============================================================
# 4. VOCABULARY (7 items)
# ============================================================
print("\n=== Vocabulary ===")
prev = dict(stats)
vocab = [
    ("megye", "county", "Budapest megyében."),
    ("foglalkozik", "to work as, to be occupied with", "Mivel foglalkozik az édesapja?"),
    ("család", "family", "Az Ön családja melyik részről származik?"),
    ("lakik", "lives (resides)", "Los Angelesben lakom 2015 óta."),
    ("mióta", "since when", "Mióta él ott?"),
    ("született", "was born", "1990. 05. 14-én születtem Budapesten."),
]
for q, a_en, a_hu in vocab:
    post_phrase(q, a_en, a_hu)
new = stats["imported"] - prev["imported"]
skip = stats["skipped"] - prev["skipped"]
print(f"  Vocab: {new} new, {skip} skipped")

# ============================================================
# 5. HISTORICAL DATES → knowledge_cards
# ============================================================
print("\n=== Historical Dates → knowledge_cards ===")
prev = dict(stats)
history = [
    ("history", "Honfoglalás", "Conquest of the Carpathian Basin", "A magyarok bejönnek a Kárpát-medencébe, Árpád vezetésével.", "Honfoglalás — [hon-fohg-lah-lahsh]", "895"),
    ("history", "Államalapítás", "Foundation of the Hungarian State", "Szent István király megkoronázása.", "Államalapítás — [ahl-lah-mah-lah-pee-tahsh]", "1000. augusztus 20."),
    ("history", "Tatárjárás", "Mongol Invasion", "A tatárok lerombolták az ország nagy részét.", "Tatárjárás — [tah-tahr-yaː-rahsh]", "1241–1242"),
    ("history", "Mohácsi csata", "Battle of Mohács", "Magyar vereség a törökök ellen.", "Mohácsi csata — [mo-haa-chi cha-tah]", "1526. augusztus 29."),
    ("history", "Buda török megszállása", "Capture of Buda by the Turks", "Magyarország középső része török uralom alá került.", "Buda török megszállása — [boo-dah tö-rök meg-szál-lá-sha]", "1541"),
    ("history", "Buda visszafoglalása", "Liberation of Buda", "A keresztény seregek felszabadították Budát.", "Buda visszafoglalása — [boo-dah vis-sah-fohg-lah-lah-sha]", "1686"),
    ("history", "Forradalom és szabadságharc", "Revolution and War of Independence", "Kossuth és Petőfi vezették, szabadságot követeltek.", "Forradalom és szabadságharc — [for-ra-dah-lom eesh sa-bod-shaːg-harts]", "1848. március 15."),
    ("history", "Kiegyezés", "Austro-Hungarian Compromise", "Létrejött az Osztrák-Magyar Monarchia.", "Kiegyezés — [kee-egg-yeh-zaysh]", "1867"),
    ("history", "Trianoni béke", "Treaty of Trianon", "Magyarország elveszti területeinek kétharmadát.", "Trianoni béke — [tree-ɒ-no-ni bay-keh]", "1920. június 4."),
    ("history", "Forradalom (1956)", "Revolution against Soviet rule", "Felkelés a kommunista hatalom ellen.", "Forradalom — [for-ra-dah-lom]", "1956. október 23."),
    ("history", "Köztársaság kikiáltása", "Proclamation of the Republic", "Magyarország demokratikus állammá válik.", "Köztársaság kikiáltása — [kø-staːr-shah-shahg kee-kee-ahl-tah-sha]", "1989. október 23."),
    ("history", "EU-csatlakozás", "EU accession", "Magyarország belép az Európai Unióba.", "EU-csatlakozás — [ay-oo cha-tloh-ko-zaːsh]", "2004. május 1."),
]
for cat, t_hu, t_en, c_hu, c_en, kf in history:
    post_knowledge(cat, t_hu, t_en, c_hu, c_en, kf)
new = stats["imported"] - prev["imported"]
skip = stats["skipped"] - prev["skipped"]
print(f"  Knowledge cards: {new} new, {skip} skipped")

# Also add history as practice phrases
print("\n=== History Practice Phrases ===")
prev = dict(stats)
history_phrases = [
    ("Nyolcszázkilencvenötben volt a Honfoglalás.", "The Hungarian Conquest was in 895."),
    ("Ezerben volt az Államalapítás.", "The founding of the Hungarian State was in 1000."),
    ("Augusztus huszadikán ünnepeljük Szent István napját.", "We celebrate Saint Stephen's Day on August 20th."),
    ("Ezerkétszáznegyvenegyedikben volt a Muhi csata.", "The Battle of Muhi was in 1241."),
    ("Ezerötszázhuszonhatban volt a Mohácsi csata.", "The Battle of Mohács was in 1526."),
    ("Március tizenötödikén ünnepeljük a Forradalmat.", "We celebrate the Revolution on March 15th."),
    ("Ezerkilencszázhúszban írták alá a Trianoni békeszerződést.", "The Treaty of Trianon was signed in 1920."),
    ("Ezerkilencszázötvenhatban volt a Forradalom.", "The Hungarian Revolution was in 1956."),
    ("Ezerkilencszáznyolcvankilencben kikiáltották a Köztársaságot.", "The Republic was proclaimed in 1989."),
    ("Kétezer-négyben Magyarország belépett az Európai Unióba.", "Hungary joined the European Union in 2004."),
]
for q, a_en in history_phrases:
    post_phrase(q, a_en)
new = stats["imported"] - prev["imported"]
skip = stats["skipped"] - prev["skipped"]
print(f"  History phrases: {new} new, {skip} skipped")

# ============================================================
# 6. COMMON EXPRESSIONS (92 items)
# ============================================================
print("\n=== Common Expressions ===")
prev = dict(stats)
expressions = [
    ("Bocsánat!", "I'm sorry. Pardon. Excuse me."),
    ("Elnézést!", "I'm sorry. Pardon. Excuse me."),
    ("Elnézést, nem értem.", "I'm sorry, I don't understand."),
    ("Fogalmam sincs.", "I have no idea."),
    ("Kérdezhetek valamit?", "Can I ask you something?"),
    ("Köszönöm.", "Thank you."),
    ("Külföldi vagyok.", "I'm a foreigner."),
    ("Lassabban, legyen szíves!", "Slower, please."),
    ("Nem beszélek magyarul.", "I don't speak Hungarian."),
    ("Nem tudom.", "I don't know."),
    ("Sajnos, nem értem.", "I'm sorry, I don't understand."),
    ("Szívesen.", "You are welcome."),
    ("Tessék!", "Here you are."),
    ("Világos minden?", "Is everything clear?"),
    ("Jó reggelt kívánok!", "Good morning."),
    ("Jó napot kívánok!", "Good afternoon."),
    ("Jó estét kívánok!", "Good evening."),
    ("Jó éjszakát kívánok!", "Good night."),
    ("Szervusz!", "Hi. Hello. Bye. (informal)"),
    ("Szia!", "Hi. Hello. Bye. (informal)"),
    ("Viszontlátásra!", "Goodbye!"),
    ("Nagyon örülök.", "Very nice to meet you."),
    ("Semmi baj.", "No problem."),
    ("Tényleg?", "Really?"),
    ("Lehet.", "Maybe. It's possible."),
    ("És Ön?", "And you? (formal)"),
    ("Hány éves vagy?", "How old are you?"),
    ("Hol élsz?", "Where do you live?"),
    ("Milyen nemzetiségű vagy?", "What nationality are you?"),
    ("Milyen nyelven beszélsz?", "What language do you speak?"),
    ("Miért tanulsz magyarul?", "Why are you learning Hungarian?"),
    ("Budapesten élek.", "I live in Budapest."),
    ("Egy kicsit tudok oroszul.", "I can speak a little Russian."),
    ("Elég jól beszélek németül.", "I can speak German quite well."),
    ("Jól vagyok.", "I'm well."),
    ("Megvagyok.", "I'm OK."),
    ("Minden rendben van.", "Everything is fine."),
    ("Persze.", "Of course. Sure."),
    ("Rendben. Jó.", "All right. Good."),
    ("Segítesz?", "Can you help me?"),
    ("Tudsz segíteni?", "Can you help me?"),
    ("Jó munkát kívánok!", "Enjoy your work!"),
    ("Mennyibe kerül ez a szék?", "How much does this chair cost?"),
    ("Ki az a magas férfi?", "Who is that tall man?"),
    ("Most megyek, mert vár a főnök.", "I have to go now, my boss is waiting."),
    ("Elnézést, van itt a közelben étterem?", "Excuse me, is there a restaurant nearby?"),
    ("Hány óra van?", "What time is it?"),
    ("Egy óra van.", "It's one o'clock."),
    ("Fél egy van.", "It's half past twelve."),
    ("Háromnegyed egy van.", "It's quarter to one."),
    ("Közel van.", "It's near."),
    ("Messze van a vár?", "Is the castle far?"),
    ("Milyen nap van ma?", "What day is it today?"),
    ("Nem csinálok semmit.", "I'm not doing anything."),
    ("Megyünk együtt moziba?", "Shall we go to the cinema together?"),
    ("Magyarországon nincsenek magas hegyek.", "There are no high mountains in Hungary."),
    ("Jó étvágyat!", "Enjoy your meal!"),
    ("Fizetni szeretnék.", "I'd like to pay."),
    ("Készpénzzel fizetek.", "I'll pay cash."),
    ("Mennyit fizetek?", "How much do I owe you?"),
    ("Mennyibe kerül a szőlő?", "How much do the grapes cost?"),
    ("Még valamit?", "Anything else?"),
    ("Köszönöm, csak körülnézek.", "I am just looking around, thank you."),
    ("Köszönöm, mást nem kérek.", "That will be all, thank you."),
    ("Szabad ez az asztal?", "Is this table free?"),
    ("Ebédelni szeretnék.", "I would like to have lunch."),
    ("Egészségesen táplálkozom.", "I eat healthy."),
    ("Asztalt szeretnék foglalni két személyre.", "I'd like to book a table for two."),
    ("Esik az eső.", "It's raining."),
    ("Esik a hó.", "It's snowing."),
    ("Fúj a szél.", "The wind is blowing."),
    ("Süt a nap.", "The sun is shining."),
    ("Hideg van.", "It's cold."),
    ("Meleg van.", "It's hot."),
    ("Imádok úszni.", "I love swimming."),
    ("Mindennap főzök.", "I cook every day."),
    ("Külföldre utazom.", "I'm travelling abroad."),
    ("Igen, ráérek.", "Yes, I have time."),
    ("Ráérsz pénteken?", "Are you free on Friday?"),
    ("Rendszeresen járok uszodába.", "I go swimming regularly."),
    ("Sajnos nem tudok táncolni.", "Unfortunately I can't dance."),
    ("Felkelek.", "I get up."),
    ("Bemegyek az irodába.", "I go into the office."),
    ("Kijövök az irodából.", "I come out of the office."),
    ("Kimegyek az utcára.", "I go out to the street."),
    ("Lefekszem.", "I go to bed."),
    ("Leülök.", "I sit down."),
    ("Szeptember óta tanulok magyarul.", "I've been learning Hungarian since September."),
    ("Hiányzik a barátom.", "I miss my friend."),
    ("Fontos, amit csinálok.", "What I do is important."),
    ("Van egy testvérem.", "I have one sibling."),
    ("Nagyon szeretem az édesanyámat.", "I love my mother very much."),
    ("Jó környéken lakunk.", "We live in a nice neighbourhood."),
    ("Lakást bérelek.", "I'm renting a flat."),
    ("Saját lakásom van.", "I've got my own flat."),
    ("Nincs háziállatom.", "I don't have a pet."),
    ("Amikor kicsi voltam, szerettem biciklizni.", "When I was little, I loved riding my bike."),
    ("Minden barátomat meghívtam.", "I invited all my friends."),
]
for q, a_en in expressions:
    post_phrase(q, a_en)
new = stats["imported"] - prev["imported"]
skip = stats["skipped"] - prev["skipped"]
print(f"  Expressions: {new} new, {skip} skipped")

# ============================================================
# FINAL SUMMARY
# ============================================================
print("\n" + "=" * 50)
print(f"TOTAL: {stats['imported']} imported, {stats['skipped']} skipped (duplicates), {stats['errors']} errors")
print("=" * 50)
