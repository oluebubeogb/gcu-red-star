# PDF processing worker (Phase 2 — stub)

Separate Python service (PyMuPDF + Tesseract OCR) that will:

1. Pick up `editions` rows with `processing_status = pending` (Redis queue `redstar:pdf`).
2. Read the original via the same storage keys the app uses (`storage/app/magazines/...` volume, or S3 later).
3. Render covers + page previews (WebP), extract text per page, OCR scanned pages.
4. Write `edition_pages` (text + tsvector) and Comfort Reading article blocks; set status `ready`/`failed`.

Deploy as its own Coolify resource sharing the `magazines` volume. Not built in Phase 1.
