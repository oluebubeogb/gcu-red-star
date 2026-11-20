<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditionPage extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['ocr' => 'boolean'];
    /** Text used by Comfort Reading, search and AI: the approved cleaned OCR text, else the original. */
    public function effectiveText(): string
    {
        return (string) ($this->cleaned_status === 'approved' && $this->cleaned_text ? $this->cleaned_text : $this->text);
    }

    /** OCR'd, or extracted text that looks garbled (few letters / many odd symbols). */
    public function needsCleanup(): bool
    {
        $t = trim((string) $this->text);
        if ($t === '') return false;
        if ($this->ocr) return true;
        $letters = preg_match_all('/\pL/u', $t);
        return mb_strlen($t) > 80 && $letters / max(1, mb_strlen($t)) < 0.6;
    }

    public function edition() { return $this->belongsTo(Edition::class); }
}
