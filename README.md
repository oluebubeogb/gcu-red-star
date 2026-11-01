# The Red Star — Phase 1 (Foundation)

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
   `APP_KEY` (`php artisan key:generate --show`), `APP_URL`, `DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD`,
   `REDIS_HOST` (+ `REDIS_PASSWORD`), `MAIL_HOST/MAIL_PORT/MAIL_USERNAME/MAIL_PASSWORD/MAIL_FROM_ADDRESS`,
   `ADMIN_USERNAMES`. Optional first admin: `SEED_ADMIN_EMAIL` + `SEED_ADMIN_PASSWORD`.
7. Deploy. Boot hook runs migrations, idempotent admin seed, `storage:link`, `optimize`.
8. Test mail from the container terminal: `php artisan redstar:mail-test you@example.com`.
   Mail host must be **DNS only (grey cloud)** on Cloudflare — proxied hosts time out on 587/465.

## Roles
- Account `category`: `student`, `old_boy`, `teacher` (self-serve at signup), `editorial`, `admin` (granted in HQ).
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

## Deferred (Phase 2+)
PDF upload + processing worker (`worker/` is a stub), covers/page renders, OCR, full-text search,
Comfort Reading + original-page reader, bookmarks, downloads, edition publishing workflow, S3 migration.
