<?php
namespace App\Support;

use App\Models\Event;
use Carbon\CarbonInterface;

/**
 * GCUOBA Homecoming: each year the set admitted 50 years earlier celebrates its golden jubilee
 * (e.g. 2025 → Class of 1975, 2026 → Class of 1976). Sets are keyed by year of ADMISSION.
 */
final class Homecoming
{
    public const YEARS = 50;

    public static function jubileeSetFor(int $year): int { return $year - self::YEARS; }

    /** The next set to celebrate: this year's set, or next year's once this year's homecoming is over. */
    public static function upcomingSet(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $set = self::jubileeSetFor($now->year);
        $event = Event::published()->where('kind', 'homecoming')->where('set_year', $set)->first();
        return $event && $event->isPast() ? $set + 1 : $set;
    }

    public static function upcomingEvent(?CarbonInterface $now = null): ?Event
    {
        return Event::published()->where('kind', 'homecoming')->where('set_year', self::upcomingSet($now))->first();
    }
}
