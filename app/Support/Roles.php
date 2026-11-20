<?php
namespace App\Support;

final class Roles
{
    public const CATEGORIES = [
        'student' => 'Student',
        'old_boy' => 'Old Boy (alumnus)',
        'teacher' => 'Teacher / Staff',
        'friend' => 'Friend of GCU',
        'editorial' => 'Editorial team',
        'admin' => 'Administrator',
    ];
    /** Categories anyone can pick at sign-up; editorial/admin are granted by staff.
     *  `friend` = outsiders (parents, well-wishers) with normal member access. Visitors without an account are guests. */
    public const SELF_SERVE = ['student', 'old_boy', 'teacher', 'friend'];
    /** Categories that record a graduation year / class. */
    public const SCHOOL = ['student', 'old_boy'];

    public const ROLES = ['member', 'editor', 'admin'];

    /** The GCU houses — the single source of truth (signup, profile, HQ filters, demo data, house pages). */
    public const HOUSES = ['Cozens House', 'Erekosima House', 'Extension House', 'Fisher House', 'Kent House', 'New House',
        'Niger House', 'Nile House', 'School House', 'Simpson House', 'Wareham House'];

    /** "niger", "NIGER HOUSE", " Niger house " → "Niger House"; anything else → null. */
    public static function normalizeHouse(?string $value): ?string
    {
        $v = strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
        $v = trim(preg_replace('/\s*house$/', '', $v));
        if ($v === '') return null;
        foreach (self::HOUSES as $h) {
            if (strtolower(substr($h, 0, -6)) === $v) return $h;
        }
        return null;
    }

    public static function houseSlug(string $house): string { return \Illuminate\Support\Str::slug(substr($house, 0, -6)); }

    public static function houseFromSlug(string $slug): ?string
    {
        foreach (self::HOUSES as $h) if (self::houseSlug($h) === $slug) return $h;
        return null;
    }

    /** ADMIN_USERNAMES="ada,obi@example.com" — matches username or email, case-insensitive. */
    public static function adminIdentifiers(): array
    {
        return collect(explode(',', (string) config('redstar.admin_usernames')))
            ->map(fn ($v) => strtolower(trim($v)))->filter()->values()->all();
    }
}
