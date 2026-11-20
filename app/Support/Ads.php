<?php
namespace App\Support;

use App\Models\Ad;
use App\Models\User;

/** Picks live ads for a slot: audience-aware, rotated by fewest impressions, counts impressions. */
final class Ads
{
    /** @param User|bool|null $viewer a user, or true for "a verified member" (digest), null for a guest */
    public static function pick(string $slot, User|bool|null $viewer, int $n = 1, bool $count = true): array
    {
        $member = $viewer === true || ($viewer instanceof User && $viewer->hasVerifiedEmail());
        $ads = Ad::live()->where('slot_key', $slot)
            ->whereIn('audience', $member ? ['all', 'members'] : ['all', 'guests'])
            ->orderBy('impressions')->orderBy('id')->limit($n)->get();
        if ($count && $ads->isNotEmpty()) Ad::whereIn('id', $ads->pluck('id'))->increment('impressions');
        return $ads->map->card()->all();
    }
}
