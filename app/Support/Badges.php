<?php
namespace App\Support;

use App\Models\User;

/** Contributor badges: student accounts with accepted submissions. Friends (and others) get counts, not student badges. */
final class Badges
{
    private const LEVELS = [10 => 'Star Student Contributor', 5 => 'Student Writer', 1 => 'Student Contributor'];

    public static function for(User $user): array
    {
        $accepted = $user->submissions()->where('status', 'accepted')->count();
        $badges = [];
        if ($user->category === 'student') {
            foreach (self::LEVELS as $min => $label) {
                if ($accepted >= $min) { $badges[] = $label; break; }
            }
        }
        return ['accepted' => $accepted, 'badges' => $badges];
    }
}
