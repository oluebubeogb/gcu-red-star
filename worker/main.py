"""The Red Star PDF worker.

Picks up editions with processing_status='pending' (Redis list push from Laravel, plus a DB poll
fallback), renders cover + page previews as WebP into the shared magazines storage, extracts per-page
text (Tesseract OCR for scanned pages), writes edition_pages + edition_blocks, and sets the edition
to ready/failed. Postgres keeps edition_pages.search (tsvector) in sync via a generated column.
"""
import io
import json
import logging
import os
import signal
import statistics
import time
from pathlib import Path

import pymupdf as fitz
import psycopg
import redis
from PIL import Image

try:
    import pytesseract
except ImportError:  # OCR optional
    pytesseract = None

log = logging.getLogger("redstar-worker")

# Prefer DATABASE_URL / REDIS_URL (Coolify); discrete DB_* and REDIS_HOST still work.
DATABASE_URL = os.environ.get("DATABASE_URL") or os.environ.get("DB_URL") or ""
ROOT = Path(os.environ.get("MAGAZINES_ROOT", "/var/www/html/storage/app/magazines"))
QUEUE = os.environ.get("REDIS_PREFIX", "the-red-star-database-") + os.environ.get("REDSTAR_PDF_QUEUE", "redstar:pdf")
POLL_SECONDS = int(os.environ.get("POLL_SECONDS", "30"))
PAGE_WIDTH = int(os.environ.get("PAGE_WIDTH", "1400"))     # preview width in px
COVER_WIDTH = int(os.environ.get("COVER_WIDTH", "600"))
WEBP_QUALITY = int(os.environ.get("WEBP_QUALITY", "80"))
OCR_LANG = os.environ.get("OCR_LANG", "eng")
OCR_MIN_CHARS = int(os.environ.get("OCR_MIN_CHARS", "40"))  # below this a page is treated as scanned
STALE_MINUTES = int(os.environ.get("STALE_MINUTES", "30"))  # re-queue editions stuck in 'processing'

running = True


def db_dsn() -> str:
    if DATABASE_URL:
        return DATABASE_URL
    return "host={} port={} dbname={} user={} password={}".format(
        os.environ.get("DB_HOST", "postgres"), os.environ.get("DB_PORT", "5432"),
        os.environ.get("DB_DATABASE", "redstar"), os.environ.get("DB_USERNAME", "redstar"),
        os.environ.get("DB_PASSWORD", ""))


def redis_client():
    url = os.environ.get("REDIS_URL")
    if url:
        return redis.Redis.from_url(url)
    pw = os.environ.get("REDIS_PASSWORD")
    return redis.Redis(host=os.environ.get("REDIS_HOST", "redis"), port=int(os.environ.get("REDIS_PORT", "6379")),
                       password=None if pw in (None, "", "null") else pw)


def render(page, width: int) -> Image.Image:
    zoom = width / page.rect.width
    pix = page.get_pixmap(matrix=fitz.Matrix(zoom, zoom), alpha=False)
    return Image.frombytes("RGB", (pix.width, pix.height), pix.samples)


def save_webp(img: Image.Image, key: str) -> None:
    path = ROOT / key
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(".tmp")
    img.save(tmp, "WEBP", quality=WEBP_QUALITY, method=4)
    os.replace(tmp, path)


def blocks_from_page(page) -> tuple[str, list[tuple[str, str]]]:
    """Text in reading order + Comfort Reading blocks (headings detected by font size)."""
    data = page.get_text("dict", sort=True)
    raw = []
    for b in data.get("blocks", []):
        if b.get("type") != 0:
            continue
        text, sizes = "", []
        for line in b.get("lines", []):
            spans = [s for s in line.get("spans", []) if s.get("text", "").strip()]
            if not spans:
                continue
            part = "".join(s["text"] for s in spans).strip()
            sizes.extend(s["size"] for s in spans)
            # Re-join words hyphenated across line breaks ("maga-" + "zine").
            text = text[:-1] + part if text.endswith("-") else (f"{text} {part}" if text else part)
        if text:
            raw.append((text, max(sizes)))
    if not raw:
        return "", []
    body = statistics.median(s for _, s in raw)
    blocks = []
    for text, size in raw:
        kind = "heading" if size >= body * 1.3 and len(text) <= 160 else "paragraph"
        blocks.append((kind, text))
    return "\n\n".join(t for _, t in blocks), blocks


def ocr_page(page) -> str:
    if pytesseract is None:
        return ""
    img = render(page, int(page.rect.width * 300 / 72))  # ~300 dpi
    return pytesseract.image_to_string(img, lang=OCR_LANG).strip()


def paragraphs(text: str) -> list[tuple[str, str]]:
    parts = [" ".join(p.split()) for p in text.split("\n\n")]
    return [("paragraph", p) for p in parts if p]


def process(conn, edition_id: int) -> None:
    with conn.cursor() as cur:
        cur.execute(
            "UPDATE editions SET processing_status='processing', processing_error=NULL, updated_at=now() "
            "WHERE id=%s AND processing_status='pending' AND pdf_path IS NOT NULL RETURNING pdf_path, cover_path",
            (edition_id,))
        row = cur.fetchone()
        conn.commit()
    if not row:
        return  # already taken, not pending, or no PDF
    pdf_key, cover_key = row
    log.info("edition %s: processing %s", edition_id, pdf_key)
    try:
        doc = fitz.open(ROOT / pdf_key)
        pages, blocks = [], []
        for i, page in enumerate(doc, start=1):
            text, page_blocks = blocks_from_page(page)
            ocr = False
            if len(text.strip()) < OCR_MIN_CHARS and page.get_images():
                ocr_text = ocr_page(page)
                if len(ocr_text) > len(text.strip()):
                    text, page_blocks, ocr = ocr_text, paragraphs(ocr_text), True
            img = render(page, PAGE_WIDTH)
            key = f"editions/{edition_id}/pages/{i}.webp"
            save_webp(img, key)
            if i == 1 and not cover_key:
                cover = img.copy()
                cover.thumbnail((COVER_WIDTH, COVER_WIDTH * 2))
                cover_key = f"editions/{edition_id}/cover.webp"
                save_webp(cover, cover_key)
            pages.append((edition_id, i, text.replace("\x00", ""), ocr, key, img.width, img.height))
            blocks.extend((edition_id, i, pos, k, t.replace("\x00", "")) for pos, (k, t) in enumerate(page_blocks))
        count = doc.page_count
        doc.close()
        with conn.cursor() as cur:
            cur.execute("DELETE FROM edition_blocks WHERE edition_id=%s", (edition_id,))
            cur.execute("DELETE FROM edition_pages WHERE edition_id=%s", (edition_id,))
            cur.executemany(
                "INSERT INTO edition_pages (edition_id, page_number, text, ocr, image_key, width, height, created_at, updated_at) "
                "VALUES (%s, %s, %s, %s, %s, %s, %s, now(), now())", pages)
            if blocks:
                cur.executemany(
                    "INSERT INTO edition_blocks (edition_id, page_number, position, type, text) VALUES (%s, %s, %s, %s, %s)", blocks)
            cur.execute(
                "UPDATE editions SET processing_status='ready', page_count=%s, cover_path=%s, processed_at=now(), updated_at=now() WHERE id=%s",
                (count, cover_key, edition_id))
        conn.commit()
        log.info("edition %s: ready (%s pages)", edition_id, count)
    except Exception as e:  # noqa: BLE001 — any failure is reported to HQ
        conn.rollback()
        log.exception("edition %s: failed", edition_id)
        with conn.cursor() as cur:
            cur.execute("UPDATE editions SET processing_status='failed', processing_error=%s, updated_at=now() WHERE id=%s",
                        (f"{type(e).__name__}: {e}"[:2000], edition_id))
        conn.commit()


def pending_ids(conn) -> list[int]:
    with conn.cursor() as cur:
        # Recover editions stuck in 'processing' (worker crashed mid-run).
        cur.execute("UPDATE editions SET processing_status='pending' WHERE processing_status='processing' "
                    "AND updated_at < now() - make_interval(mins => %s)", (STALE_MINUTES,))
        cur.execute("SELECT id FROM editions WHERE processing_status='pending' AND pdf_path IS NOT NULL ORDER BY updated_at LIMIT 20")
        ids = [r[0] for r in cur.fetchall()]
    conn.commit()
    return ids


def main() -> None:
    logging.basicConfig(level=os.environ.get("LOG_LEVEL", "INFO"), format="[redstar-worker] %(levelname)s %(message)s")
    signal.signal(signal.SIGTERM, lambda *_: globals().update(running=False))
    log.info("storage root %s, queue %s, OCR %s", ROOT, QUEUE, "on" if pytesseract else "off")
    r = redis_client()
    last_poll = 0.0
    conn = None
    while running:
        try:
            if conn is None or conn.closed:
                conn = psycopg.connect(db_dsn())
            if time.time() - last_poll >= POLL_SECONDS:
                for eid in pending_ids(conn):
                    process(conn, eid)
                last_poll = time.time()
            try:
                item = r.blpop([QUEUE], timeout=min(POLL_SECONDS, 10))
            except redis.RedisError as e:
                log.warning("redis unavailable (%s); relying on DB poll", e)
                item = None
                time.sleep(5)
            if item:
                eid = int(json.loads(item[1]).get("edition_id"))
                process(conn, eid)
        except psycopg.Error as e:
            log.error("database error: %s", e)
            try:
                conn and conn.close()
            except Exception:  # noqa: BLE001
                pass
            conn = None
            time.sleep(5)
        except (ValueError, TypeError) as e:
            log.warning("bad queue item: %s", e)
    log.info("stopping")


if __name__ == "__main__":
    main()
