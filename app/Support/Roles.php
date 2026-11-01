<?php
namespace App\Support;

final class Roles
{
    public const CATEGORIES = [
        'student' => 'Student',
        'old_boy' => 'Old Boy (alumnus)',
        'teacher' => 'Teacher / Staff',
        'editorial' => 'Editorial team',
        'admin' => 'Administrator',
    ];
    /** Categories anyone can pick at sign-up; editorial/admin are granted by staff. */
    public const SELF_SERVE = ['student', 'old_boy', 'teacher'];

    public const ROLES = ['member', 'editor', 'admin'];

    /** ADMIN_USERNAMES="ada,obi@example.com" — matches username or email, case-insensitive. */
    public static function adminIdentifiers(): array
    {
        return collect(explode(',', (string) config('redstar.admin_usernames')))
            ->map(fn ($v) => strtolower(trim($v)))->filter()->values()->all();
    }
}
