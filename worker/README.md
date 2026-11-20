# The Red Star — PDF processing worker (Phase 2)

Python service (PyMuPDF + Pillow + Tesseract OCR) that turns uploaded edition PDFs into readable, searchable pages.

## What it does
1. Waits on the Redis list `${REDIS_PREFIX}${REDSTAR_PDF_QUEUE}` (default `the-red-star-database-redstar:pdf`;
   Laravel pushes `{"edition_id": N}` on upload/reprocess) and polls the DB every `POLL_SECONDS` for
   `editions.processing_status = 'pending'` as a fallback. Editions stuck in `processing` > `STALE_MINUTES` are re-queued.
2. Claims the edition atomically (`pending → processing`), opens `MAGAZINES_ROOT/<pdf_path>`.
3. For each page: extracts text (reading order) and Comfort Reading blocks (headings by font size); if a page has
   < `OCR_MIN_CHARS` characters and contains images it is OCR'd at ~300 dpi with Tesseract (`OCR_LANG`).
4. Renders `editions/{id}/pages/{n}.webp` (`PAGE_WIDTH`, default 1400 px) and, if no cover was uploaded,
   `editions/{id}/cover.webp` from page 1.
5. Replaces `edition_pages` / `edition_blocks` rows, sets `page_count`, `cover_path`, `processed_at`,
   `processing_status = 'ready'` — or `failed` with `processing_error` (shown in HQ; editors can “Reprocess”).
   `edition_pages.search` (tsvector) is a generated column, so Postgres indexes text automatically.

## Coolify deploy
1. New resource → **Application** from this repo → Build pack **Dockerfile**, **Base directory `/worker`**
   (Dockerfile `worker/Dockerfile`). No ports / no domain; disable the HTTP health check.
2. **Persistent storage:** mount the **same volume** the app uses for `/var/www/html/storage/app/magazines`
   at the same path in the worker (or set `MAGAZINES_ROOT` to wherever you mount it). The worker runs as uid/gid 33
   (www-data) so the app can read what it writes. If the volume was created by root: `chown -R 33:33 <host path>`.
3. **Environment** (same Postgres/Redis resources as the app):
   - `DATABASE_URL=postgresql://user:pass@postgres-host:5432/redstar` (preferred; or `DB_URL` / `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD`)
   - `REDIS_URL=redis://:password@redis-host:6379` (preferred; or `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`)
   - `REDIS_PREFIX` — must equal the app's Redis prefix. App default is `Str::slug(APP_NAME)-database-`
     = `the-red-star-database-`; if you set `REDIS_PREFIX` on the app, copy it here.
   - Optional: `REDSTAR_PDF_QUEUE` (default `redstar:pdf`), `POLL_SECONDS` (30), `PAGE_WIDTH` (1400), `COVER_WIDTH` (600),
     `WEBP_QUALITY` (80), `OCR_LANG` (`eng`), `OCR_MIN_CHARS` (40), `STALE_MINUTES` (30), `LOG_LEVEL`.
4. Deploy after the app has run its migrations. Upload a PDF in HQ → status goes pending → processing → ready.

## Local run
```
pip install -r requirements.txt   # plus: apt install tesseract-ocr
DATABASE_URL=postgresql://redstar:redstar@127.0.0.1/redstar REDIS_URL=redis://127.0.0.1:6379 \
MAGAZINES_ROOT=../storage/app/magazines python main.py
```

Note: the worker reads/writes the local volume only. When the app moves to `MAGAZINE_DISK=magazines_s3`,
the worker needs an S3 adapter (not built yet).
