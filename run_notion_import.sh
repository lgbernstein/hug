#!/bin/bash
# Import Notion data to Hug Coach via AJAX endpoints
# Uses curl to avoid HostRocket WAF blocking Python urllib

BASE="https://ambersight.com/hug"
PHRASE="$BASE/?ajax=1&action=save_phrase"
KNOWLEDGE="$BASE/?ajax=1&action=save_knowledge"
UA="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)"
CT="application/x-www-form-urlencoded"

imported=0
skipped=0
errors=0

post_phrase() {
    local q="$1" ae="$2" ah="$3" cat="${4:-prep}"
    local result
    result=$(curl -s -X POST "$PHRASE" \
        -H "User-Agent: $UA" \
        -H "Content-Type: $CT" \
        --data-urlencode "question_hu=$q" \
        --data-urlencode "answer_en=$ae" \
        --data-urlencode "answer_hu=$ah" \
        --data-urlencode "category=$cat" 2>/dev/null)
    if echo "$result" | grep -q '"error"'; then
        echo "  ERROR: $result | ${q:0:50}"
        ((errors++))
    elif echo "$result" | grep -q 'Already'; then
        ((skipped++))
    else
        ((imported++))
    fi
}

post_knowledge() {
    local cat="$1" thu="$2" ten="$3" chu="$4" cen="$5" kf="$6"
    local result
    result=$(curl -s -X POST "$KNOWLEDGE" \
        -H "User-Agent: $UA" \
        -H "Content-Type: $CT" \
        --data-urlencode "category=$cat" \
        --data-urlencode "title_hu=$thu" \
        --data-urlencode "title_en=$ten" \
        --data-urlencode "content_hu=$chu" \
        --data-urlencode "content_en=$cen" \
        --data-urlencode "key_fact=$kf" 2>/dev/null)
    if echo "$result" | grep -q '"error"'; then
        echo "  ERROR: $result | ${thu:0:50}"
        ((errors++))
    elif echo "$result" | grep -q 'Already'; then
        ((skipped++))
    else
        ((imported++))
    fi
}

echo "=== 1. Interview Q&A (27 items) ==="
pre_imp=$imported; pre_skip=$skipped

post_phrase "Kérem, mondja el, mi a foglalkozása." "Please tell me what your occupation is." "[TK-confirm] a foglalkozásom." "interview"
post_phrase "Kérem, mondja el, mi az édesanyja leánykori neve." "Please tell me your mother's maiden name." "Az édesanyám leánykori neve [TK-confirm]." "interview"
post_phrase "Kérem, mondja el, miért szeretne magyar állampolgár lenni." "Please tell me why you would like to become a Hungarian citizen." "Magyar állampolgár szeretnék lenni, mert magyar származású vagyok, és szeretném megőrizni a családi kötődést és a nyelvet." "interview"
post_phrase "Kérem, mondja el, milyen gyakran beszél magyarul." "Please tell me how often you speak Hungarian." "Naponta beszélek magyarul." "interview"
post_phrase "Kérem, mondja el, mi a teljes neve." "Please tell me your full name." "A teljes nevem [TK-confirm]." "interview"
post_phrase "Kérem, mondja el, mi az édesapja neve." "Please tell me your father's name." "Az édesapám neve [TK-confirm]." "interview"
post_phrase "Kérem, mondja el, mióta lakik ezen a címen." "Please tell me since when you have been living at this address." "[TK-confirm év] óta lakom ezen a címen." "interview"
post_phrase "Kérem, mutassa be az okmányait. Van útlevele vagy személyi igazolványa?" "Please show me your documents. Do you have a passport or an ID card?" "Igen. Itt van az útlevelem." "interview"
post_phrase "Kérem, mondja el, járt-e már Magyarországon. Mikor és meddig volt ott?" "Please tell me whether you have been to Hungary before." "Igen, jártam Magyarországon." "interview"
post_phrase "Kérem, mondja el, honnan származik a családja Magyarországon." "Please tell me where your family comes from in Hungary." "A családom [TK-confirm település]-ból származik." "interview"
post_phrase "Kérem, mondja el, hol dolgozik." "Please tell me where you work." "[TK-confirm cég]-nél dolgozom." "interview"
post_phrase "Kérem, mondja el, mikor és hol született." "Please tell me when and where you were born." "[TK-confirm dátum]-én születtem [TK-confirm város]-ban." "interview"
post_phrase "Kérem, mondja el, van-e gyermeke, és hány." "Please tell me whether you have any children." "Igen, [TK-confirm szám] gyermekem van." "interview"
post_phrase "Kérem, mondja el, hol lakik jelenleg." "Please tell me where you currently live." "Jelenleg [TK-confirm város]-ban lakom." "interview"
post_phrase "Kérem, mondja el, házas-e." "Please tell me whether you are married." "Igen, házas vagyok. / Nem, nem vagyok házas." "interview"
post_phrase "Hány éves?" "How old are you?" "Kilencvenegy éves vagyok." "interview"
post_phrase "Mi a testvére neve?" "What is your sibling's name?" "Az öcséim neve John és Peter." "interview"
post_phrase "Van testvére?" "Do you have any siblings?" "Igen, van két öcsém." "interview"
post_phrase "Mikor született?" "When were you born?" "Ezerkilencszázharmincnégyben születtem." "interview"
post_phrase "Mi az édesanyja neve?" "What is your mother's name?" "Az édesanyám neve Maria Angelos volt." "interview"
post_phrase "Hol született?" "Where were you born?" "Medellínben, Kolumbiában születtem." "interview"
post_phrase "Miben segíthetek?" "How can I help you?" "Állampolgársági interjúra jöttem." "interview"
post_phrase "Mi a neve? / Hogy hívják?" "What is your name?" "A nevem Marlene Angelos." "interview"
post_phrase "Mi az édesapja neve?" "What is your father's name?" "Az édesapám neve George Angelos volt." "interview"
post_phrase "Elhozta az útlevelét?" "Did you bring your passport?" "Igen, elhoztam az útlevelemet." "interview"
post_phrase "Hol lakik most, és mióta él ott?" "Where do you live now, and since when?" "Jelenleg Los Angelesben lakom, Kaliforniában, 2015 óta." "interview"
post_phrase "Az Ön családja Magyarország melyik részéről származik, és mivel foglalkoztak a felmenők?" "Which part of Hungary does your family come from?" "Paternal nagyapám magyar származású volt." "interview"

echo "  Interview: $((imported-pre_imp)) new, $((skipped-pre_skip)) skipped"

echo ""
echo "=== 2. Citizenship Words (36 items) ==="
pre_imp=$imported; pre_skip=$skipped

post_phrase "nagykövetség" "embassy" "A magyar nagykövetségen voltam."
post_phrase "konzulátus" "consulate" ""
post_phrase "kérelem" "application / request" "Állampolgársági kérelmet adtam be."
post_phrase "űrlap / nyomtatvány" "form" "Ki kell töltenie az űrlapot."
post_phrase "aláírás" "signature" "Ide kérem az aláírását."
post_phrase "aláír" "to sign" "Kérem, írja alá!"
post_phrase "útlevél" "passport" "Van érvényes útlevele?"
post_phrase "személyi igazolvány" "ID card" ""
post_phrase "jogosítvány / vezetői engedély" "driver's license" "Van amerikai jogosítványom."
post_phrase "születési anyakönyvi kivonat" "birth certificate" "Be kell mutatnia a születési anyakönyvi kivonatot."
post_phrase "házassági anyakönyvi kivonat" "marriage certificate" ""
post_phrase "válási papír / ítélet" "divorce decree" "Van válási ítélete?"
post_phrase "állampolgárság" "citizenship" "Magyar állampolgárságot kérek."
post_phrase "állampolgár" "citizen" "Amerikai állampolgár vagyok."
post_phrase "útlevélkérelem" "passport application" ""
post_phrase "pecsét" "stamp / seal" "Kérem a pecsétet ide!"
post_phrase "hivatal" "office / bureau" "Az okmányirodában dolgozik."
post_phrase "okmány" "document / ID" ""
post_phrase "okmányiroda" "document office" ""
post_phrase "nyilatkozat" "declaration / statement" "Aláírtam a nyilatkozatot."
post_phrase "bizonyítvány" "certificate / diploma" ""
post_phrase "hiteles másolat" "certified copy" ""
post_phrase "fordítás" "translation" "Hivatalos fordítást kérek."
post_phrase "fordító" "translator" "A fordító aláírta a dokumentumot."
post_phrase "hivatalos" "official / formal" "Hivatalos dokumentum."
post_phrase "érvényes" "valid" "Az útlevele még érvényes."
post_phrase "lejárt" "expired" "A jogosítványom lejárt."
post_phrase "irat / dokumentum" "record / document" ""
post_phrase "jelentkezés" "application / registration" ""
post_phrase "kérelmező" "applicant" ""
post_phrase "tanú" "witness" ""
post_phrase "állandó lakcím" "permanent address" ""
post_phrase "ideiglenes lakcím" "temporary address" ""
post_phrase "születési hely" "place of birth" ""
post_phrase "keltezés" "date of issue / issuance" ""

echo "  Citizenship: $((imported-pre_imp)) new, $((skipped-pre_skip)) skipped"

echo ""
echo "=== 3. Sentences to Practice (13 items) ==="
pre_imp=$imported; pre_skip=$skipped

post_phrase "Március tizenötödikén ünneplünk." "We celebrate on March 15th."
post_phrase "Január elsején születtem." "I was born on January 1st."
post_phrase "Annával beszélek." "I am speaking with Anna."
post_phrase "Ez a ház nagy." "This house is big."
post_phrase "Hány könyv van az asztalon?" "How many books are on the table?"
post_phrase "Ez a negyedik feladat." "This is the fourth task."
post_phrase "Mennyi pénz kell?" "How much money is needed?"
post_phrase "Busszal megyek." "I am going by bus."
post_phrase "Napos az idő." "The weather is sunny."
post_phrase "Ő a hatodik." "They are sixth."
post_phrase "Esős idő van." "It is rainy weather."
post_phrase "Az a könyv érdekes." "That book is interesting."

echo "  Sentences: $((imported-pre_imp)) new, $((skipped-pre_skip)) skipped"

echo ""
echo "=== 4. Vocabulary (6 items) ==="
pre_imp=$imported; pre_skip=$skipped

post_phrase "megye" "county" "Budapest megyében."
post_phrase "foglalkozik" "to work as, to be occupied with" "Mivel foglalkozik az édesapja?"
post_phrase "család" "family" "Az Ön családja melyik részről származik?"
post_phrase "lakik" "lives (resides)" "Los Angelesben lakom 2015 óta."
post_phrase "mióta" "since when" "Mióta él ott?"
post_phrase "született" "was born" "1990. 05. 14-én születtem Budapesten."

echo "  Vocabulary: $((imported-pre_imp)) new, $((skipped-pre_skip)) skipped"

echo ""
echo "=== 5. Historical Dates → knowledge_cards (12 items) ==="
pre_imp=$imported; pre_skip=$skipped

post_knowledge "history" "Honfoglalás" "Conquest of the Carpathian Basin" "A magyarok bejönnek a Kárpát-medencébe, Árpád vezetésével." "Honfoglalás — [hon-fohg-lah-lahsh]" "895"
post_knowledge "history" "Államalapítás" "Foundation of the Hungarian State" "Szent István király megkoronázása." "Államalapítás — [ahl-lah-mah-lah-pee-tahsh]" "1000. augusztus 20."
post_knowledge "history" "Tatárjárás" "Mongol Invasion" "A tatárok lerombolták az ország nagy részét." "Tatárjárás — [tah-tahr-yaː-rahsh]" "1241–1242"
post_knowledge "history" "Mohácsi csata" "Battle of Mohács" "Magyar vereség a törökök ellen." "Mohácsi csata — [mo-haa-chi cha-tah]" "1526. augusztus 29."
post_knowledge "history" "Buda török megszállása" "Capture of Buda by the Turks" "Magyarország középső része török uralom alá került." "Buda török megszállása — [boo-dah tö-rök meg-szál-lá-sha]" "1541"
post_knowledge "history" "Buda visszafoglalása" "Liberation of Buda" "A keresztény seregek felszabadították Budát." "Buda visszafoglalása — [boo-dah vis-sah-fohg-lah-lah-sha]" "1686"
post_knowledge "history" "Forradalom és szabadságharc" "Revolution and War of Independence" "Kossuth és Petőfi vezették, szabadságot követeltek." "Forradalom és szabadságharc — [for-ra-dah-lom eesh sa-bod-shaːg-harts]" "1848. március 15."
post_knowledge "history" "Kiegyezés" "Austro-Hungarian Compromise" "Létrejött az Osztrák-Magyar Monarchia." "Kiegyezés — [kee-egg-yeh-zaysh]" "1867"
post_knowledge "history" "Trianoni béke" "Treaty of Trianon" "Magyarország elveszti területeinek kétharmadát." "Trianoni béke — [tree-ɒ-no-ni bay-keh]" "1920. június 4."
post_knowledge "history" "Forradalom (1956)" "Revolution against Soviet rule" "Felkelés a kommunista hatalom ellen." "Forradalom — [for-ra-dah-lom]" "1956. október 23."
post_knowledge "history" "Köztársaság kikiáltása" "Proclamation of the Republic" "Magyarország demokratikus állammá válik." "Köztársaság kikiáltása — [kø-staːr-shah-shahg kee-kee-ahl-tah-sha]" "1989. október 23."
post_knowledge "history" "EU-csatlakozás" "EU accession" "Magyarország belép az Európai Unióba." "EU-csatlakozás — [ay-oo cha-tloh-ko-zaːsh]" "2004. május 1."

echo "  Knowledge cards: $((imported-pre_imp)) new, $((skipped-pre_skip)) skipped"

echo ""
echo "=== 6. History Practice Phrases (10 items) ==="
pre_imp=$imported; pre_skip=$skipped

post_phrase "Nyolcszázkilencvenötben volt a Honfoglalás." "The Hungarian Conquest was in 895."
post_phrase "Ezerben volt az Államalapítás." "The founding of the Hungarian State was in 1000."
post_phrase "Augusztus huszadikán ünnepeljük Szent István napját." "We celebrate Saint Stephen's Day on August 20th."
post_phrase "Ezerkétszáznegyvenegyedikben volt a Muhi csata." "The Battle of Muhi was in 1241."
post_phrase "Ezerötszázhuszonhatban volt a Mohácsi csata." "The Battle of Mohács was in 1526."
post_phrase "Március tizenötödikén ünnepeljük a Forradalmat." "We celebrate the Revolution on March 15th."
post_phrase "Ezerkilencszázhúszban írták alá a Trianoni békeszerződést." "The Treaty of Trianon was signed in 1920."
post_phrase "Ezerkilencszázötvenhatban volt a Forradalom." "The Hungarian Revolution was in 1956."
post_phrase "Ezerkilencszáznyolcvankilencben kikiáltották a Köztársaságot." "The Republic was proclaimed in 1989."
post_phrase "Kétezer-négyben Magyarország belépett az Európai Unióba." "Hungary joined the European Union in 2004."

echo "  History phrases: $((imported-pre_imp)) new, $((skipped-pre_skip)) skipped"

echo ""
echo "=== 7. Common Expressions (97 items) ==="
pre_imp=$imported; pre_skip=$skipped

post_phrase "Bocsánat!" "I'm sorry. Pardon. Excuse me."
post_phrase "Elnézést!" "I'm sorry. Pardon. Excuse me."
post_phrase "Elnézést, nem értem." "I'm sorry, I don't understand."
post_phrase "Fogalmam sincs." "I have no idea."
post_phrase "Kérdezhetek valamit?" "Can I ask you something?"
post_phrase "Köszönöm." "Thank you."
post_phrase "Külföldi vagyok." "I'm a foreigner."
post_phrase "Lassabban, legyen szíves!" "Slower, please."
post_phrase "Nem beszélek magyarul." "I don't speak Hungarian."
post_phrase "Nem tudom." "I don't know."
post_phrase "Sajnos, nem értem." "I'm sorry, I don't understand."
post_phrase "Szívesen." "You are welcome."
post_phrase "Tessék!" "Here you are."
post_phrase "Világos minden?" "Is everything clear?"
post_phrase "Jó reggelt kívánok!" "Good morning."
post_phrase "Jó napot kívánok!" "Good afternoon."
post_phrase "Jó estét kívánok!" "Good evening."
post_phrase "Jó éjszakát kívánok!" "Good night."
post_phrase "Szervusz!" "Hi. Hello. Bye. (informal)"
post_phrase "Szia!" "Hi. Hello. Bye. (informal)"
post_phrase "Viszontlátásra!" "Goodbye!"
post_phrase "Nagyon örülök." "Very nice to meet you."
post_phrase "Semmi baj." "No problem."
post_phrase "Tényleg?" "Really?"
post_phrase "Lehet." "Maybe. It's possible."
post_phrase "És Ön?" "And you? (formal)"
post_phrase "Hány éves vagy?" "How old are you?"
post_phrase "Hol élsz?" "Where do you live?"
post_phrase "Milyen nemzetiségű vagy?" "What nationality are you?"
post_phrase "Milyen nyelven beszélsz?" "What language do you speak?"
post_phrase "Miért tanulsz magyarul?" "Why are you learning Hungarian?"
post_phrase "Budapesten élek." "I live in Budapest."
post_phrase "Egy kicsit tudok oroszul." "I can speak a little Russian."
post_phrase "Elég jól beszélek németül." "I can speak German quite well."
post_phrase "Jól vagyok." "I'm well."
post_phrase "Megvagyok." "I'm OK."
post_phrase "Minden rendben van." "Everything is fine."
post_phrase "Persze." "Of course. Sure."
post_phrase "Rendben. Jó." "All right. Good."
post_phrase "Segítesz?" "Can you help me?"
post_phrase "Tudsz segíteni?" "Can you help me?"
post_phrase "Jó munkát kívánok!" "Enjoy your work!"
post_phrase "Mennyibe kerül ez a szék?" "How much does this chair cost?"
post_phrase "Ki az a magas férfi?" "Who is that tall man?"
post_phrase "Most megyek, mert vár a főnök." "I have to go now, my boss is waiting."
post_phrase "Elnézést, van itt a közelben étterem?" "Excuse me, is there a restaurant nearby?"
post_phrase "Hány óra van?" "What time is it?"
post_phrase "Egy óra van." "It's one o'clock."
post_phrase "Fél egy van." "It's half past twelve."
post_phrase "Háromnegyed egy van." "It's quarter to one."
post_phrase "Közel van." "It's near."
post_phrase "Messze van a vár?" "Is the castle far?"
post_phrase "Milyen nap van ma?" "What day is it today?"
post_phrase "Nem csinálok semmit." "I'm not doing anything."
post_phrase "Megyünk együtt moziba?" "Shall we go to the cinema together?"
post_phrase "Magyarországon nincsenek magas hegyek." "There are no high mountains in Hungary."
post_phrase "Jó étvágyat!" "Enjoy your meal!"
post_phrase "Fizetni szeretnék." "I'd like to pay."
post_phrase "Készpénzzel fizetek." "I'll pay cash."
post_phrase "Mennyit fizetek?" "How much do I owe you?"
post_phrase "Mennyibe kerül a szőlő?" "How much do the grapes cost?"
post_phrase "Még valamit?" "Anything else?"
post_phrase "Köszönöm, csak körülnézek." "I am just looking around, thank you."
post_phrase "Köszönöm, mást nem kérek." "That will be all, thank you."
post_phrase "Szabad ez az asztal?" "Is this table free?"
post_phrase "Ebédelni szeretnék." "I would like to have lunch."
post_phrase "Egészségesen táplálkozom." "I eat healthy."
post_phrase "Asztalt szeretnék foglalni két személyre." "I'd like to book a table for two."
post_phrase "Esik az eső." "It's raining."
post_phrase "Esik a hó." "It's snowing."
post_phrase "Fúj a szél." "The wind is blowing."
post_phrase "Süt a nap." "The sun is shining."
post_phrase "Hideg van." "It's cold."
post_phrase "Meleg van." "It's hot."
post_phrase "Imádok úszni." "I love swimming."
post_phrase "Mindennap főzök." "I cook every day."
post_phrase "Külföldre utazom." "I'm travelling abroad."
post_phrase "Igen, ráérek." "Yes, I have time."
post_phrase "Ráérsz pénteken?" "Are you free on Friday?"
post_phrase "Rendszeresen járok uszodába." "I go swimming regularly."
post_phrase "Sajnos nem tudok táncolni." "Unfortunately I can't dance."
post_phrase "Felkelek." "I get up."
post_phrase "Bemegyek az irodába." "I go into the office."
post_phrase "Kijövök az irodából." "I come out of the office."
post_phrase "Kimegyek az utcára." "I go out to the street."
post_phrase "Lefekszem." "I go to bed."
post_phrase "Leülök." "I sit down."
post_phrase "Szeptember óta tanulok magyarul." "I've been learning Hungarian since September."
post_phrase "Hiányzik a barátom." "I miss my friend."
post_phrase "Fontos, amit csinálok." "What I do is important."
post_phrase "Van egy testvérem." "I have one sibling."
post_phrase "Nagyon szeretem az édesanyámat." "I love my mother very much."
post_phrase "Jó környéken lakunk." "We live in a nice neighbourhood."
post_phrase "Lakást bérelek." "I'm renting a flat."
post_phrase "Saját lakásom van." "I've got my own flat."
post_phrase "Nincs háziállatom." "I don't have a pet."
post_phrase "Amikor kicsi voltam, szerettem biciklizni." "When I was little, I loved riding my bike."
post_phrase "Minden barátomat meghívtam." "I invited all my friends."

echo "  Expressions: $((imported-pre_imp)) new, $((skipped-pre_skip)) skipped"

echo ""
echo "=================================================="
echo "TOTAL: $imported imported, $skipped skipped, $errors errors"
echo "=================================================="
