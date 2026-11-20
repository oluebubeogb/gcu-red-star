<?php
namespace App\Support;

use App\Models\Announcement;
use App\Models\Edition;
use App\Models\FeaturedMember;
use App\Models\User;

/** Content + recipients for the monthly digest email. */
final class Digest
{
    public static function recipients()
    {
        return User::where('digest_opt_in', true)->whereNotNull('email_verified_at')->where('is_demo', false);
    }

    public static function content(): array
    {
        $since = now()->subMonth();
        $feature = FeaturedMember::current();
        $event = Homecoming::upcomingEvent();
        $set = Homecoming::upcomingSet();
        return [
            'editions' => Edition::published()->where('published_at', '>=', $since)->latest('published_at')->get(['title', 'slug', 'year', 'summary']),
            'announcements' => Announcement::live()->where('published_at', '>=', $since)->latest('published_at')->limit(5)->get(['title', 'body']),
            'feature' => $feature ? ['name' => $feature->user->name, 'username' => $feature->user->username, 'blurb' => $feature->blurb,
                'public' => $feature->user->show_in_directory] : null,
            // Digest slot: one live ad (rotated, impression counted per send batch, not per recipient).
            'ad' => Ads::pick('digest', true, 1)[0] ?? null,
            'homecoming' => ['set' => $set, 'year' => $set + Homecoming::YEARS, 'event' => $event?->card()],
        ];
    }
}
