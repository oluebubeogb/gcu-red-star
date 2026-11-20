# The Red Star — Phases 1–6

The Voice of Government College Umuahia. Laravel 12 · PHP 8.4 · Inertia + Vue 3 (plain JS) · Tailwind · PostgreSQL · Redis.
Single Dockerfile for Coolify (nginx + php-fpm via serversideup/php, queue + scheduler supervised by s6).

**Container port: `3130`** · Health check: `GET /up`

## Coolify deploy
1. Create **PostgreSQL** and **Redis** resources in the same project/network. Note their internal hostnames.
2. New resource → **Application** from this repo → Build pack **Dockerfile** (not Compose).
3. **Ports exposes:** `3130`. Domain: e.g. `https://redstar.example.com`.
4. **Health check:** path `/up`, port `3130`.
5. **Persistent storage** (Storages → Add → Volume):
   - `/var/www/html/storage/app/magazines` — magazine PDFs, covers, page renders (private)
   - `/var/www/html/storage/app/public` — public uploads (avatars etc.)
   If uploads fail with permission errors: `chown -R 33:33 <host path>` once (www-data = uid 33).
6. **Environment** — copy `.env.example`, then set at minimum:
   `APP_KEY` (`php artisan key:generate --show`), `APP_URL`, `DATABASE_URL` (or `DB_URL` / discrete `DB_*`),
   `REDIS_URL` (or `REDIS_HOST` + `REDIS_PASSWORD`), `MAIL_HOST/MAIL_PORT/MAIL_USERNAME/MAIL_PASSWORD/MAIL_FROM_ADDRESS`,
   `ADMIN_USERNAMES`. Optional first admin: `SEED_ADMIN_EMAIL` + `SEED_ADMIN_PASSWORD`.
7. Deploy. Boot hook runs migrations, idempotent admin seed, `storage:link`, `optimize`.
8. Test mail from the container terminal: `php artisan redstar:mail-test you@example.com`.
   Mail host must be **DNS only (grey cloud)** on Cloudflare — proxied hosts time out on 587/465.

## Roles
- **Guests** (no account): read `public` editions, search the archive (and, later, Ask the archive).
- Account `category`: `student`, `old_boy`, `teacher`, `friend` ("Friend of GCU" — outsiders such as parents and
  well-wishers) are self-serve at signup; `editorial`, `admin` are granted in HQ. All verified members (friends included)
  can read members-only editions, bookmark, download and submit. Friend submissions carry a "Friend of GCU" badge in HQ.
- **Sets are named by year of ADMISSION** ("Class of 1975" = admitted 1975), stored in `users.admission_year`
  ("Year of admission (your set)"). `graduation_year` is kept but secondary.
- **Houses** are a fixed list in `App\Support\Roles::HOUSES` (Cozens, Erekosima, Extension, Fisher, Kent, New, Niger,
  Nile, School, Simpson, Wareham — each "… House"); used by signup, profile, HQ filters, demo data and `/houses`.
- `role`: `member` | `editor` | `admin`. Editors (role editor or category editorial) reach `/hq`.
- `ADMIN_USERNAMES="ada,obi@example.com"` — usernames or emails that are always admin.
- Email verification required (`MustVerifyEmail`) for dashboard, submissions, HQ.

## Storage abstraction
`App\Storage\MagazineStorage` (interface) → `FilesystemMagazineStorage` bound in `AppServiceProvider`.
Code stores keys only. Local disk `magazines` = `storage/app/magazines`, served via signed `/files/{key}` URLs.
Move to S3/R2 later: set `MAGAZINE_DISK=magazines_s3` + `AWS_*`, copy files, no code changes.

## Local dev
```
cp .env.example .env && php artisan key:generate
composer install && npm install
php artisan migrate --seed
composer run dev
```

## Phase 1 includes
Public site (Home, Editions, Edition stub, Archive, About, Contribute) · register/login/verify/reset ·
profile fields (username, category, graduation year, class, house) · member dashboard · submissions ·
Editorial HQ (counts, submission review, admin member/role management) · Edition + Submission models ·
storage abstraction · branded mail theme · Docker/Coolify setup · tests.

## Phase 2 includes
- **HQ editions** (`/hq/editions`): create/edit, upload PDF + optional cover via `MagazineStorage`
  (keys `editions/{id}/original.pdf`, `editions/{id}/cover-upload-*.{ext}`), reprocess, publish/unpublish, delete drafts.
  Workflow: `status` draft → published; `processing_status` pending → processing → ready | failed (error shown in HQ).
  Publishing requires `ready`. Uploading a new PDF moves the edition back to draft and re-queues it.
- **PDF worker** (`worker/`, separate Coolify resource): see `worker/README.md`. Laravel pushes `{"edition_id":N}` to the
  Redis list `REDSTAR_PDF_QUEUE` on upload; the worker also polls the DB for pending editions.
- **Reader** (`/editions/{slug}?page=N`): original-page viewer (WebP previews via signed URLs, arrow keys / page picker)
  and Comfort Reading (reflowed text blocks, default on mobile). Only published editions are visible; `access=members`
  editions need a verified account.
- **Search** (`/search?q=`): Postgres full-text (`edition_pages.search` generated tsvector + GIN, `websearch_to_tsquery`,
  ranked, highlighted snippets) over published editions. SQLite (tests) falls back to `LIKE`.
- **Bookmarks** (`/bookmarks`, edition + page) and **PDF downloads** (`/editions/{slug}/download` → 15-minute signed URL)
  for verified members.
- **Photo submissions**: kind `photo` requires an image (jpg/png/webp, 10 MB), stored as `submissions/{user}/…`; shown in HQ.

New migration: `2026_11_20_000100_phase2_reader_tables` (`edition_pages`, `edition_blocks`, `bookmarks`,
`editions.processing_error/processed_at`, `submissions.photo_path`). New env: `REDSTAR_PDF_QUEUE`, `REDSTAR_MAX_PDF_KB`.

## Phase 3 includes
- **Demo data** (HQ → admins): "Seed demo data" creates 1–25 (default 25) demo members with Nigerian names, mixed
  categories/houses/classes/years, verified `…@demo.redstar.invalid` emails, random unusable passwords, sample
  submissions and bookmarks. Re-seeding replaces. "Remove demo data" deletes only rows with `is_demo = true`
  (users, submissions, bookmarks). Disabled when `APP_ENV=production` unless `REDSTAR_ALLOW_DEMO=true`.
  HQ Members has category + real/demo filters. (Demo house names are placeholders — edit `App\Support\DemoData`.)
- **Typo-tolerant search** (`App\Support\ArchiveSearch`): full-text first; if nothing matches, "did you mean" corrects
  each word against `search_words` (materialized view of published-edition vocabulary, pg_trgm similarity) and reruns;
  then a trigram word-similarity fallback on page text. Matching edition titles are listed too. Trigram GIN indexes on
  page text, edition titles and user name/username (speeds HQ member search). `search_words` is refreshed on
  publish/unpublish. SQLite (tests) uses LIKE + levenshtein.
- **Slow-connection performance**: signed file URLs are stable for ~6 h and versioned by file mtime, so page images are
  served `private, max-age=604800, immutable`; next page is prefetched, images lazy/async-decoded; fonts load
  non-blocking; pages are already code-split per route.
- **Offline**: `public/sw.js` + "Save for offline" in the reader caches the edition page (incl. Comfort Reading text),
  all page images and JS/CSS; offline navigation to a saved edition works. Web manifest added.
- **Sharing**: WhatsApp buttons (wa.me) on the edition and per reader page; Open Graph/Twitter meta rendered server-side
  (`og:image` = stable `/editions/{slug}/cover` redirect).

New migration: `2026_11_20_000300_phase3_demo_friend_fuzzy` (`is_demo` on users/submissions/bookmarks; Postgres:
`CREATE EXTENSION pg_trgm`, trigram indexes, `search_words` view). The DB user must be allowed to create the
extension (database owner is enough on PG13+; otherwise run `CREATE EXTENSION pg_trgm;` once as superuser).
New env: `REDSTAR_ALLOW_DEMO` (default false).

## Phase 4 includes
- **Houses dropdown** (signup + profile, validated), HQ members filter by house; migration normalises existing free-text
  houses ("niger", "NIGER HOUSE" → "Niger House"; unrecognised values → null).
- **People tagging** on edition pages (`page_tags`): tag a member by username, yourself ("That's me"), or a free-text
  name for someone without an account — which that person can later **claim** ("This is me"). Tagged members get a
  database notification (`/notifications`, unread badge) and an email unless they turned it off. Tags show in the reader
  and on profiles. Editors can remove any tag; members can remove tags of themselves (and free-text tags they added).
- **Sets** (`/sets`, `/sets/{admission_year}`) and **Houses** (`/houses`, `/houses/{slug}`): public members, tagged
  pages mentioning them, and set/house news (announcements targeted at a set or house).
- **Public profiles** (`/members/{username}`): set, house, contributor badges, tagged pages. Privacy toggle
  `show_in_directory` (profile → "Show me on my public profile, set and house pages"); hidden profiles 404 except to
  the member and editors. Email is never shown.
- **Homecoming / Golden Jubilee** (`/homecoming`, home-page banner): jubilee set = year − 50 by admission year
  (2026 → Class of 1976); after this year's homecoming has ended it moves to next year's set. Countdown when the event has
  a date, the jubilee set's members, join/update-profile call to action, and a past-homecomings archive. `events` are
  managed in HQ → Community; the real 12–14 Dec 2025 GCUOBA Homecoming & AGM (Class of 1975) is seeded by the migration.
- **HQ → Community** (editors): homecoming events (with cover), announcements (general / set / house),
  **Old Boy of the month** (pick by username + blurb; shown on the home page and in the digest), "Send test digest to me".
- **Monthly digest**: `php artisan redstar:digest` (scheduled 1st of each month 09:00 Africa/Lagos via the container's
  scheduler; `--dry-run` counts recipients). Queued to verified, non-demo members with `digest_opt_in` (checkbox at
  signup, toggle in profile). Signed one-click unsubscribe link + `List-Unsubscribe` header.
- **Contributor badges**: student accounts with accepted submissions get Student Contributor (1+) / Student Writer (5+) /
  Star Student Contributor (10+). Others, including friends, see their accepted count but no student badge.

New migrations: `2026_11_20_000400_create_notifications_table`, `2026_11_20_000500_phase4_community`
(`users.admission_year/show_in_directory/digest_opt_in/notify_email`, `page_tags`, `events`, `announcements`,
`featured_members`, house normalisation, 2025 homecoming seed). No new env vars (digest uses the existing MAIL_* and
queue/scheduler; keep `REDSTAR_RUN_WORKERS=true` or add a Coolify scheduled task for `schedule:run`).

## Phase 5 — AI (Replicate only)
All AI goes through the Replicate HTTP API (`app/Ai/Replicate.php`: `POST /v1/models/{owner}/{name}/predictions`, or
`/v1/predictions` with `version` when a slug contains `:`; `Prefer: wait` then polling `urls.get`). No other providers.
Without `REPLICATE_API_TOKEN` every AI feature is hidden and the site behaves exactly as Phase 4.

**Setup (Coolify):** add `REPLICATE_API_TOKEN=r8_...` to the app's Environment, redeploy. The queue worker and
scheduler (`REDSTAR_RUN_WORKERS=true`) must be running — all AI work is queued except *Ask the archive* and the
cached query embedding used by `/search` (short timeout, falls back to normal search).
Then HQ → **AI** → *Rebuild AI index* once to index existing editions.

| Feature | What it does | Trigger |
|---|---|---|
| Semantic search | Pages chunked (~1200 chars) + embedded; `/search` fuses full-text/fuzzy with vector ranking (RRF) | Sweep every minute after the PDF worker finishes; on publish; HQ button |
| Ask the archive | `/ask` + box on search. Answers ONLY from retrieved chunks, cites edition+page (`?page=N`), else “I couldn't find that in the archive.” Guests: public editions; verified members: + members-only | Live (throttled, cached 72h, logged → HQ top questions) |
| Summaries & tags | Edition + page-cluster summaries with people/places/events/years; entity chips link to search; member-name matches → tag suggestions (never auto-created) | After indexing; HQ review (draft → approve) unless auto-publish |
| OCR cleanup | Cleaned text stored separately (`cleaned_text`), original kept. Approved text feeds Comfort Reading + search | Pages flagged as poor OCR, after indexing; HQ side-by-side approve |
| Audio editions | TTS per page → `editions/{id}/audio/{n}.mp3` via MagazineStorage; player in reader | Only when an editor clicks in HQ → edition → AI |
| Editor's assistant | Spam/abuse score, suggested headline, proofreading suggestions on submissions — never applied | On submit; “Re-run” in HQ |

**Env vars** (all optional except the token):
```
REPLICATE_API_TOKEN=                 # required for any AI
REDSTAR_AI_ENABLED=true              # master switch
REDSTAR_AI_SEARCH=true REDSTAR_AI_ASK=true REDSTAR_AI_SUMMARIES=true
REDSTAR_AI_OCR=true REDSTAR_AI_AUDIO=true REDSTAR_AI_ASSISTANT=true
REDSTAR_AI_AUTO_PUBLISH=false        # summaries skip HQ review when true (also toggle in HQ)
REDSTAR_AI_LLM_MODEL=meta/meta-llama-3-8b-instruct      # summaries, OCR cleanup, assistant
REDSTAR_AI_ASK_MODEL=meta/meta-llama-3-70b-instruct     # Ask the archive (better grounding)
REDSTAR_AI_EMBED_MODEL=ibm-granite/granite-embedding-278m-multilingual   # 768 dims
REDSTAR_AI_TTS_MODEL=minimax/speech-02-turbo
REDSTAR_AI_TTS_VOICE=English_Deep-VoicedGentleman
REDSTAR_AI_PGVECTOR=true             # false forces the JSON + PHP cosine fallback
REDSTAR_AI_EMBED_DIMS=768            # must match the embed model; vector column size is set at migration time
REDSTAR_AI_SEMANTIC_MIN=0.35         # ignore weaker semantic matches
REDSTAR_AI_DAILY_CAP=2000            # hard cap on Replicate calls per day
REDSTAR_AI_ASK_GUEST_PER_HOUR=10  REDSTAR_AI_ASK_USER_PER_HOUR=30
REDSTAR_AI_CACHE_HOURS=72  REDSTAR_AI_CHUNK_CHARS=1200  REDSTAR_AI_TIMEOUT=60
```
HQ → AI also has per-feature switches (stored in `settings`), call counter vs cap, index stats and top questions.

**pgvector:** the migration enables `vector` if the Postgres server has it (stock Coolify Postgres images usually
don't). To get it, switch the Postgres resource image to `pgvector/pgvector:pg17` (same major version as now; data volume is reused), then run once in psql as the `postgres` superuser:
`CREATE EXTENSION vector; ALTER TABLE edition_chunks ADD COLUMN embedding vector(768); CREATE INDEX edition_chunks_embedding_hnsw ON edition_chunks USING hnsw (embedding vector_cosine_ops);`
then HQ → *Rebuild AI index*. Without pgvector everything still works: vectors are kept as JSON and ranked in PHP over a
bounded candidate set (fine for a school archive of a few thousand pages).

**Cost notes:** Replicate bills per token / per second / per character depending on the model — check each model page.
Embeddings are very cheap and re-indexing reuses embeddings for unchanged text. The 8B LLM is the cheap workhorse; the
70B model (Ask only) costs several times more per token — set `REDSTAR_AI_ASK_MODEL` to the 8B model to save money.
TTS is the most expensive per edition (billed by characters), so it is click-only and skips pages that already have
audio. Identical questions are cached, Ask is rate-limited per IP/user, and `REDSTAR_AI_DAILY_CAP` stops all calls for
the day once reached.

**Migration** `2026_11_20_000600_phase5_ai`: `settings`, `edition_chunks` (+ optional `embedding vector(768)` + HNSW),
`ai_summaries`, `edition_audio`, `ask_logs`; `editions.ai_indexed_at`; `edition_pages.cleaned_text/cleaned_status`
(the `search` tsvector now prefers approved cleaned text); `submissions.ai_review/ai_reviewed_at`.

## Phase 6 — Payments & revenue (Paystack or Monnify)
Set `PAYMENT_METHOD=paystack` (default) or `PAYMENT_METHOD=monnify`. Amounts are stored in **kobo** (₦1 = 100 kobo).
Paid features are enabled only when the *selected* gateway's keys are present; otherwise they are hidden and their
POST routes return 503. Free features (basic directory listings, bulk upload) still work. Switching the env var
applies to new checkouts; already-started payments are verified with the gateway stored on that `payments` row.

**Paystack setup** (`PAYMENT_METHOD=paystack`)
1. Paystack Dashboard → Settings → API Keys & Webhooks. Copy the **test** keys first (`sk_test_…`, `pk_test_…`).
2. Coolify: `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY`, redeploy.
3. Webhook URL: `https://<your-domain>/payments/webhook`. Test and live mode each have their own field; set both.
4. Test with Paystack's test cards, then swap to live keys (`sk_live_…` / `pk_live_…`). HQ → Commerce shows TEST vs LIVE.

**Monnify setup** (`PAYMENT_METHOD=monnify`)
1. Monnify dashboard → Developers / API Keys: API Key, Secret Key, and Contract Code. Start on **sandbox**.
2. Coolify: `MONNIFY_API_KEY`, `MONNIFY_SECRET_KEY`, `MONNIFY_CONTRACT_CODE`. Leave `MONNIFY_BASE_URL` unset for
   sandbox (`https://sandbox.monnify.com`). For live, set `MONNIFY_BASE_URL=https://api.monnify.com` and use live keys.
3. Webhook URL (Developer → Webhook URLs → Transaction Completion): `https://<your-domain>/payments/webhook/monnify`.
   HQ → Commerce shows the exact URL for the active gateway.
4. Official docs: the `monnify-signature` header is sent in **production only**, not on sandbox. In live mode we
   require a valid HMAC-SHA512 of the raw body with the secret key. In sandbox, if the header is missing we confirm
   the payment via Monnify's verify API rather than trusting the webhook body. You may also whitelist Monnify's
   webhook IP (`35.242.133.146`).

**Flow:** a record is created (sponsorship / listing / ad / print order) + a `payments` row with a unique reference
and `gateway` → initialize checkout (Paystack: amount in kobo; Monnify: amount in Naira, contract code, customer
name/email) → redirect → back on `/payments/callback` we verify via the provider API. The matching webhook does the
same (`charge.success` for Paystack; `SUCCESSFUL_TRANSACTION` / `paymentStatus=PAID` for Monnify). Both call one
idempotent routine (row lock, no-op once `success`, amount and currency must match), so a payment is fulfilled exactly
once whichever arrives first. Webhooks are CSRF-exempt.

| Stream | Public | HQ (Commerce) |
|---|---|---|
| Sponsorships | `/sponsor`: packages; sponsor as set (admission year), house, individual or company; edition or Homecoming; logo upload | Define packages (name, price, perks, target); approve/rename/hide sponsors and remove logos. Credited on the edition / Homecoming page only when paid **and** approved |
| Business directory | `/directory` (filter by category, state, search; featured first; links to owner profile + set); members manage listings at `/my/businesses` | Approve/reject with a note (every edit re-enters review). Featured = paid N months (1/3/6/12), extends from current expiry, expires automatically. Monthly price editable |
| Ads & notices | `/advertise`: homepage banner, edition sidebar, monthly digest slot, congratulatory/obituary notices; audience everyone / members / guests | Approve creatives; slot prices/periods; impressions, clicks, CTR. Live = paid + approved + within dates; rotation = fewest impressions first; clicks go through `/ads/{id}/click` |
| Printed copies | "Order a printed copy" on editions in print: quantity, delivery (Nigerian state) or pickup at school | Mark editions in print with price + stock; order statuses paid → printing → shipped/ready → delivered (cancel returns stock); tracking note. Stock is decremented when payment succeeds. Emails on payment and each status change |
| Revenue | — | `/hq/revenue`: totals by stream, last 12 months, recent payments, CSV export |

**Bulk upload (archive push):** HQ → Editions → *Bulk upload*. Choose many PDFs (the browser uploads them one request per
file, with per-file progress), or copy PDFs to `storage/app/magazines/imports/<folder>/` on the volume and import the
folder (files are **moved** into `editions/{id}/`). Optional CSV `filename,title,date,volume,issue,members_only`; files
without a row get a title from the file name and a year if one appears in it. Each file becomes a draft edition queued
for the worker; the progress table refreshes every 10s. "Publish automatically when ready" is applied by the
`redstar:auto-publish` scheduler task (every minute).

**Env vars:** `PAYMENT_METHOD=paystack|monnify` (default paystack). For Paystack: `PAYSTACK_SECRET_KEY`,
`PAYSTACK_PUBLIC_KEY`. For Monnify: `MONNIFY_API_KEY`, `MONNIFY_SECRET_KEY`, `MONNIFY_CONTRACT_CODE`, optional
`MONNIFY_BASE_URL`. Optional `REDSTAR_FEATURED_MONTH_KOBO=500000` (HQ can override). Ad slot prices are seeded
(banner ₦30,000, sidebar ₦15,000, digest ₦10,000 per 30 days; notices ₦5,000 per 14 days) and editable in HQ.

**Migrations** `2026_11_20_000700_phase6_commerce` and `000710_phase6_payment_gateway`: `payments` (unique `reference`, `gateway`, morph to the paid record),
`sponsorship_packages`, `sponsorships`, `businesses`, `ad_slots` (+ 4 default slots), `ads`, `print_orders`;
`editions.print_available/print_price_kobo/print_stock/issue_date/import_batch/auto_publish`.

## Deferred (Phase 7+)
S3/R2 for the worker (app side is config-only; the worker reads/writes the local volume today), article-level
segmentation across pages, edition-level search filters (year/decade), deleting page images of re-processed PDFs
with fewer pages.
