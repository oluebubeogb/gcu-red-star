<?php
namespace App\Support;

use App\Storage\MagazineStorage;

/** Local covers, page renders, avatars and a simple PDF — nothing is fetched from the web. */
final class SeedArt
{
    public static function storage(): MagazineStorage { return app(MagazineStorage::class); }

    public static function font(string $which = 'sans'): ?string
    {
        $file = match ($which) {
            'bold' => 'DejaVuSans-Bold.ttf',
            'serif' => 'DejaVuSerif.ttf',
            default => 'DejaVuSans.ttf',
        };
        $path = resource_path('fonts/'.$file);
        return is_file($path) ? $path : null;
    }

    public static function putImage(string $key, \GdImage $im, int $quality = 72): string
    {
        ob_start();
        if (function_exists('imagewebp')) imagewebp($im, null, $quality);
        else imagejpeg($im, null, $quality);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);
        self::storage()->put($key, $bytes);
        return $key;
    }

    public static function cover(string $key, string $title, int $year, string $kicker = 'The Red Star'): string
    {
        $w = 900; $h = 1200;
        $im = imagecreatetruecolor($w, $h);
        $maroon = imagecolorallocate($im, 122, 28, 28);
        $cream = imagecolorallocate($im, 247, 240, 230);
        $gold = imagecolorallocate($im, 196, 149, 78);
        $ink = imagecolorallocate($im, 36, 22, 18);
        imagefilledrectangle($im, 0, 0, $w, $h, $maroon);
        imagefilledrectangle($im, 36, 36, $w - 36, $h - 36, $cream);
        imagefilledrectangle($im, 36, 36, $w - 36, 200, $maroon);
        self::text($im, $kicker, 28, $w / 2, 90, $gold, 'bold', true);
        self::text($im, 'Government College Umuahia', 16, $w / 2, 150, $cream, 'sans', true);
        // A simple 8-point star
        self::star($im, (int) ($w / 2), 340, 70, $maroon);
        self::text($im, $title, 42, $w / 2, 500, $ink, 'bold', true, 720);
        self::text($im, (string) $year, 28, $w / 2, 980, $maroon, 'serif', true);
        self::text($im, 'The Voice of Government College Umuahia', 14, $w / 2, 1060, $ink, 'sans', true);
        return self::putImage($key, $im, 78);
    }

    public static function page(string $key, string $title, string $body, int $page, int $year, ?string $kicker = null): array
    {
        $w = 800; $h = 1130;
        $im = imagecreatetruecolor($w, $h);
        $cream = imagecolorallocate($im, 250, 245, 236);
        $maroon = imagecolorallocate($im, 122, 28, 28);
        $ink = imagecolorallocate($im, 36, 22, 18);
        $rule = imagecolorallocate($im, 200, 180, 160);
        imagefilledrectangle($im, 0, 0, $w, $h, $cream);
        imagefilledrectangle($im, 0, 0, $w, 64, $maroon);
        self::text($im, 'THE RED STAR  ·  GCU  ·  '.$year, 13, $w / 2, 40, imagecolorallocate($im, 247, 240, 230), 'sans', true);
        $y = 110;
        if ($kicker) { self::text($im, mb_strtoupper($kicker), 12, 64, $y, $maroon, 'bold'); $y += 28; }
        $y = self::text($im, $title, 26, 64, $y, $ink, 'bold', false, 670) + 16;
        imageline($im, 64, $y, $w - 64, $y, $rule);
        $y += 28;
        self::text($im, $body, 14, 64, $y, $ink, 'serif', false, 670, 22);
        self::text($im, (string) $page, 12, $w / 2, $h - 36, $maroon, 'sans', true);
        self::putImage($key, $im, 70);
        return ['width' => $w, 'height' => $h];
    }

    public static function avatar(string $key, string $initials, string $house = ''): string
    {
        $palette = [
            'Cozens House' => [122, 28, 28], 'Erekosima House' => [30, 80, 60], 'Extension House' => [80, 50, 120],
            'Fisher House' => [30, 70, 120], 'Kent House' => [140, 80, 20], 'New House' => [20, 90, 100],
            'Niger House' => [90, 30, 50], 'Nile House' => [20, 90, 140], 'School House' => [50, 50, 50],
            'Simpson House' => [100, 40, 20], 'Wareham House' => [40, 80, 40],
        ];
        $rgb = $palette[$house] ?? [122, 28, 28];
        $s = 400;
        $im = imagecreatetruecolor($s, $s);
        $bg = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
        $fg = imagecolorallocate($im, 247, 240, 230);
        imagefilledrectangle($im, 0, 0, $s, $s, $bg);
        imageellipse($im, $s / 2, $s / 2, $s - 24, $s - 24, $fg);
        self::text($im, mb_strtoupper($initials), 96, $s / 2, (int) ($s / 2 + 36), $fg, 'bold', true);
        return self::putImage($key, $im, 80);
    }

    public static function logo(string $key, string $name): string
    {
        $w = 640; $h = 240;
        $im = imagecreatetruecolor($w, $h);
        $cream = imagecolorallocate($im, 247, 240, 230);
        $maroon = imagecolorallocate($im, 122, 28, 28);
        imagefilledrectangle($im, 0, 0, $w, $h, $cream);
        imagerectangle($im, 8, 8, $w - 9, $h - 9, $maroon);
        self::text($im, $name, 28, $w / 2, (int) ($h / 2 + 10), $maroon, 'bold', true, 560);
        return self::putImage($key, $im, 80);
    }

    /** A small multi-page PDF (Helvetica) so the edition can be downloaded. */
    public static function pdf(string $key, string $title, int $year, array $pages): string
    {
        $objs = [];
        $objs[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $contentIds = [];
        $pageIds = [];
        // We'll number: 1 catalog, 2 pages, then pairs of page + content
        $n = count($pages) ?: 1;
        $id = 3;
        foreach (array_values($pages) as $i => $p) {
            $pageIds[] = $id;
            $contentIds[] = $id + 1;
            $kids[] = $id.' 0 R';
            $id += 2;
        }
        $objs[] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$n.' >>';
        foreach ($pageIds as $i => $pid) {
            $cid = $contentIds[$i];
            $objs[$pid - 1] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents $cid 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >> /F2 << /Type /Font /Subtype /Type1 /BaseFont /Times-Bold >> >> >> >>";
            $head = self::pdfEscape(($pages[$i]['title'] ?? $title));
            $body = self::wrap($pages[$i]['body'] ?? '', 86);
            $stream = "BT /F2 16 Tf 50 790 Td (".self::pdfEscape($title.'  ·  '.$year).") Tj ET\n";
            $stream .= "BT /F2 18 Tf 50 750 Td ($head) Tj ET\n";
            $y = 720;
            foreach ($body as $line) {
                $stream .= "BT /F1 11 Tf 50 $y Td (".self::pdfEscape($line).") Tj ET\n";
                $y -= 16;
                if ($y < 60) break;
            }
            $stream .= "BT /F1 10 Tf 300 36 Td (".($i + 1).") Tj ET\n";
            $objs[$cid - 1] = '<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream";
        }
        ksort($objs);
        $out = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objs as $i => $body) {
            $offsets[$i + 1] = strlen($out);
            $out .= ($i + 1)." 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $count = count($objs) + 1;
        $out .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $out .= "trailer << /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        self::storage()->put($key, $out);
        return $key;
    }

    /** @return int bottom y of the written block */
    private static function text(\GdImage $im, string $text, int $size, int $x, int $y, int $color, string $font = 'sans', bool $center = false, int $maxWidth = 0, int $leading = 0): int
    {
        $file = function_exists('imagettftext') ? self::font($font) : null;
        $leading = $leading ?: (int) round($size * 1.45);
        $lines = $maxWidth && $file ? self::wrapGd($text, $file, $size, $maxWidth) : (preg_split('/\n/', $text) ?: [$text]);
        foreach ($lines as $line) {
            if ($file) {
                $box = imagettfbbox($size, 0, $file, $line);
                $w = $box[2] - $box[0];
                $drawX = $center ? (int) ($x - $w / 2) : $x;
                imagettftext($im, $size, 0, $drawX, $y, $color, $file, $line);
            } else {
                $drawX = $center ? (int) ($x - strlen($line) * 5) : $x;
                imagestring($im, 5, max(0, $drawX), $y - 12, $line, $color);
            }
            $y += $leading;
        }
        return $y;
    }

    private static function wrapGd(string $text, string $font, int $size, int $maxWidth): array
    {
        $out = [];
        foreach (preg_split('/\n+/', trim($text)) as $para) {
            $line = '';
            foreach (preg_split('/\s+/', $para) as $word) {
                $try = trim("$line $word");
                $box = imagettfbbox($size, 0, $font, $try);
                if ($line !== '' && ($box[2] - $box[0]) > $maxWidth) { $out[] = $line; $line = $word; }
                else $line = $try;
            }
            if ($line !== '') $out[] = $line;
            $out[] = '';
        }
        return $out;
    }

    private static function wrap(string $text, int $width): array
    {
        $out = [];
        foreach (preg_split('/\n+/', trim($text)) as $para) {
            $out = array_merge($out, explode("\n", wordwrap($para, $width, "\n", true)), ['']);
        }
        return $out;
    }

    private static function pdfEscape(string $s): string
    {
        $s = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s) ?: $s;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private static function star(\GdImage $im, int $cx, int $cy, int $r, int $color): void
    {
        $pts = [];
        for ($i = 0; $i < 8; $i++) {
            $a = deg2rad(-90 + $i * 45);
            $rad = $i % 2 === 0 ? $r : (int) ($r * 0.4);
            $pts[] = (int) ($cx + cos($a) * $rad);
            $pts[] = (int) ($cy + sin($a) * $rad);
        }
        imagefilledpolygon($im, $pts, $color);
    }
}
