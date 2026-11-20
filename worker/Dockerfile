# The Red Star PDF worker — separate Coolify resource (no ports).
FROM python:3.12-slim-bookworm
RUN apt-get update \
 && apt-get install -y --no-install-recommends tesseract-ocr tesseract-ocr-eng \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY main.py .
# Run as www-data's uid/gid (33) so files it writes are readable by the Laravel app on the shared volume.
RUN groupadd -g 33 -o app && useradd -u 33 -g 33 -o -M -s /usr/sbin/nologin app
USER 33:33
ENV PYTHONUNBUFFERED=1 MAGAZINES_ROOT=/var/www/html/storage/app/magazines
CMD ["python", "main.py"]
