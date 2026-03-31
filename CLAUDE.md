# Hug Coach — Project Context

Hungarian language learning app for simplified naturalization (egyszerűsített honosítás) interview prep.
Users: Larry and Maria. Hosted on HostRocket shared hosting.

## Mission — The Five Learning Pillars

Everything in this app serves exactly five learning goals. Every feature, block type, and UI decision must trace back to one or more of these. If it doesn't serve a pillar, it doesn't belong.

| Pillar | Goal | Why it matters for the interview |
|--------|------|----------------------------------|
| **A. Vocabulary** | Learn and retain Hungarian words | Foundation — can't form sentences without words |
| **B. Sentence Recognition** | Understand spoken Hungarian sentences | The interview is oral — you must parse what the examiner says in real time. Covers sentence structure, vocab in context, and auditory recognition |
| **C. Pronunciation** | Speak clearly enough to be understood | Graded on intelligibility, not perfection. Must be good enough that a native speaker doesn't have to guess |
| **D. Knowledge** | Learn Hungarian history, geography, culture, government | Factual content the examiner will ask about — dates, rivers, cities, constitution, national symbols |
| **E. Grammar** | Internalize Hungarian grammar patterns | Hungarian is agglutinative — suffixes, conjugation, vowel harmony, and word order affect everything. Grammar unlocks the ability to construct novel sentences, not just repeat memorized ones |

### How Pillars Map to Block Types

Each pillar is served by one or more dedicated block types. Blocks are self-contained sessions with their own interaction pattern — never mixed into grab-bag interleaved sessions.

| Block Type | Pillars Served | Interaction Pattern | Duration |
|-----------|---------------|-------------------|----------|
| **Phrase Drill** | A, B, C | Show phrase → Listen → Repeat → Score → Next (rapid cycle) | ~10 min, 15-20 phrases |
| **Grammar Lesson** | E | Concept → Examples → Quiz → Advanced → Final Quiz (scaffolded) | ~8-12 min |
| **Knowledge Cards** | D | Show fact → Flip to test → Score → Next | ~5-8 min, 8-10 cards |
| **Interview Sim** | B, C, D, E | AI asks → You answer in Hungarian → Feedback → Next | ~15 min, 6-8 questions |
| **Scenario Drill** | A, B, C | Themed phrase set (e.g. "Greeting & Small Talk") — same as phrase drill but topical | ~10 min |

### Pedagogical Principles

These are non-negotiable. Every block type must follow evidence-based learning methods:

1. **Spaced repetition (SRS)** — Items return at increasing intervals based on recall success. Applies to all content types.
2. **Scaffolding** — New concepts are introduced simply before complexity is added. Grammar lessons use the full scaffold (concept → simple → quiz → advanced → quiz). Other block types use lighter scaffolds appropriate to their content.
3. **Active recall** — The learner must produce or recognize the answer, not just read it. No passive "read and move on" screens.
4. **Interleaving across blocks, not within** — The daily plan interleaves different block *types* (phrase → grammar → knowledge → phrase). But within a single block, content is focused on one type. This gives the spacing benefit without the cognitive whiplash of mixed-bag sessions.
5. **Desirable difficulty** — Questions should be challenging but achievable. SRS difficulty multipliers and the "due for review" system handle this automatically.

### Daily Plan Structure

The Today view sequences typed blocks, not mixed bags:

```
🎤 Phrase Drill — 12 due reviews + 3 new        (10 min)
📖 Grammar — Accusative case -t suffix            (10 min)
☕ Break                                           (5 min)
🧠 Knowledge — History & Geography                (8 min)
🎤 Scenario Drill — Greeting & Small Talk         (10 min)
☕ Break                                           (5 min)
💬 Interview Sim — Who Are You?                   (15 min)
📖 Grammar — Possession suffixes                  (10 min)
☕ Break                                           (5 min)
🔗 External — Pimsleur / Drops / HungarianPod101 / etc.  (10-30 min)
...
```

## Hosting & Deployment

- **Host:** HostRocket shared hosting (no SSH shell for PHP; FTP/cPanel only)
- **Local dev copies:** `/Users/larrybernstein/Documents/Hug/`
- **DB access:** phpMyAdmin (no CLI access)
- **Constraint:** Plain PHP, no Composer, no npm, no build step. Everything in one `index.php` + helper PHP endpoints.

## File Structure

| File | Role |
|------|------|
| `index.php` | Main app — all HTML/CSS/JS in one file (v4.3+) |
| `eval.php` | Grades pronunciation or interview answers via Gemini |
| `translate.php` | Translates Hungarian ↔ English via Gemini |
| `phonetic.php` | Returns English phonetic sound guide for Hungarian text |
| `record.php` | Records SRS pass/fail, upserts `study_history` table |
| `.env` | `GEMINI_KEY`, `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` |

## Gemini API

- **Model:** `gemini-2.5-flash-lite:generateContent`
- **Endpoint:** `https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash-lite:generateContent?key=KEY`
- **maxOutputTokens:** `2048` (required — default is too low, causes truncated JSON)
- **temperature:** `0.3` for eval, `0.1` for phonetic
- **CURLOPT_SSL_VERIFYPEER:** `false` (HostRocket shared hosting quirk)

## MySQL Database Tables

| Table | Purpose |
|-------|---------|
| `hungarian_prep` | Main phrase bank — `question_hu`, `answer_en`, `category` |
| `user_bios` | Personal facts — `subject_name` (Maria/Larry), `fact_label_hu`, `fact_value_hu` |
| `study_history` | SRS tracking — `phrase`, `who`, `pass_count`, `fail_count`, `last_seen`, `next_review` |

**Important:** `user_bios.fact_label_hu` contains English labels (legacy). Only `fact_value_hu` is Hungarian.
`hungarian_prep` is the correct table for ALL and PHRASES modes.

## Category Filter Logic

| UI Button | `cat` param | SQL source |
|-----------|-------------|-----------|
| ALL | `all` | `hungarian_prep` only |
| PHRASES | `prep` | `hungarian_prep` only |
| PERSONAL | `bios` | `user_bios` WHERE subject_name = who AND fact_value_hu LIKE '%?' |

**Never mix `user_bios` into ALL or PHRASES** — labels are in English.

## App Modes

- **Pronunciation mode:** User reads a Hungarian phrase aloud; graded on intelligibility
- **Interview mode:** AI asks a question; user answers in Hungarian; graded on content + intelligibility
- **Listen mode:** Question text is blurred; click to reveal; re-blurs on next question

## Eval Strictness Standard

Matches real B1 simplified naturalization interview (post-2020 reform):
- **Strict on:** correct content, intelligibility, staying in Hungarian
- **Forgiving on:** grammar mechanics (wrong case endings, conjugation, word order)
- **Auto-fail:** answering in English, factually wrong answers, completely garbled, silence

## Key JS Architecture

- **Single-file SPA** — all logic in `index.php` `<script>` block
- **Web Speech API:** `continuous=true`, `lang=hu-HU`, echo guard 700ms
- **VAD constants:** `VAD_THRESHOLD=8`, `VAD_SILENCE=1200ms`
- **`isPractice` flag** — routes `recognition.onresult` to practice textarea vs eval. Reset in `recognition.onend` (bug fix — never leaks).
- **`showPlaybackWhenReady` flag** — bridges async `mediaRecorder.onstop` and eval result handler (playback button timing fix)
- **`questionAttempted` flag** — only first attempt counts for SRS and session stats
- **`targetQ` / `targetA`** — PHP-injected, synced on every `nextQuestion()`

## SRS Intervals (record.php)

| Pass streak | Next review |
|-------------|-------------|
| 1 | 3 days |
| 2 | 7 days |
| 3 | 14 days |
| 4+ | 21 days |
| Fail | 1 day (resets streak) |

## Users

- `who` param: `Maria`, `Larry`, or `All`
- Stored in `localStorage` as `hugWho`
- Affects SRS tracking and personal bio questions

## Design System

- **Framework:** Tailwind CSS (CDN)
- **Theme:** Dark slate (`bg-slate-900`, `bg-slate-800`)
- **Accent:** Indigo/violet (`bg-indigo-600`, `bg-violet-500`)
- **Pass:** Green (`text-green-400`, `bg-green-900`)
- **Fail:** Red (`text-red-400`, `bg-red-900`)
- **Font:** System sans-serif via Tailwind default
- **Layout:** Mobile-first, single column, max-w-lg centered

## Feature List (v4.3)

1. Pronunciation mode — read phrase aloud, graded by Gemini
2. Interview mode — answer question in Hungarian, graded on content
3. Voice Activity Detection (VAD) — auto-stops mic after silence
4. MediaRecorder playback — hear your own recording on RETRY
5. Practice textarea — type answer, get instant TTS + translation
6. Translate button — inline Hungarian↔English translation
7. Phonetic hint — English sound guide per word
8. Listen mode — blurred text, click to reveal
9. Reveal answer — `<details>` accordion shows expected answer
10. SRS (spaced repetition) — weighted question selection, DB-backed
11. Category filter — ALL / PHRASES / PERSONAL, localStorage-persisted
12. Session summary — modal after 10 questions with pass/fail/streak
13. Who selector — Maria / Larry / All, affects bios + SRS
14. Mode toggle — Pronunciation / Interview

## Common Gotchas

- Always check `study_history` SQL has a fallback (`$conn->query()` can return false if table missing on first load)
- `localStorage.getItem('hugCat') === 'bios'` must be auto-cleared on page load (legacy value)
- `mediaRecorder.onstop` is async — never rely on `lastRecordingBlob` being set synchronously in `onresult`
- HostRocket PHP version: confirm before using syntax newer than PHP 7.4
- `CURLOPT_TIMEOUT => 15` on eval, `10` on phonetic — Gemini can be slow on HostRocket
