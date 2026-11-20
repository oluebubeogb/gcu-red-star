<?php
namespace App\Support;

use App\Models\Bookmark;
use App\Models\Edition;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** HQ demo data. Every record is flagged is_demo=true so purge() never touches real data. */
final class DemoData
{
    private const BOYS = ['Chukwuemeka', 'Obinna', 'Ikenna', 'Chinedu', 'Nnamdi', 'Ifeanyi', 'Kelechi', 'Uchenna', 'Chidi', 'Emeka',
        'Somtochukwu', 'Ebuka', 'Tobenna', 'Kenechukwu', 'Oluwaseun', 'Tunde', 'Babatunde', 'Musa', 'Ibrahim', 'Efosa', 'Ekene', 'Chibuike'];
    private const OTHERS = ['Ngozi', 'Adaeze', 'Chiamaka', 'Funmilayo', 'Amina', 'Nkechi', 'Ogechi', 'Ifeoma', 'Yetunde', 'Chioma'];
    private const SURNAMES = ['Okafor', 'Nwosu', 'Eze', 'Okonkwo', 'Uchendu', 'Nwachukwu', 'Okeke', 'Obi', 'Onyekachi', 'Ibe', 'Anyanwu',
        'Kalu', 'Nwankwo', 'Agu', 'Ogbonna', 'Adeyemi', 'Balogun', 'Bello', 'Osagie', 'Ezeani', 'Iwu', 'Mbah', 'Orji'];
    private const CLASSES = ['JSS1', 'JSS2', 'JSS3', 'SS1', 'SS2', 'SS3'];
    private const CATEGORIES = ['student', 'student', 'student', 'old_boy', 'old_boy', 'old_boy', 'teacher', 'friend', 'friend'];
    private const TITLES = [
        'article' => ['Life in the boarding house', 'Our new science laboratory', 'Why reading still matters', 'Inter-house sports recap'],
        'poem' => ['Ode to the Umuahia hills', 'Morning assembly', 'Harmattan dust'],
        'letter' => ['A letter from the class of 1985', 'Memories of the old dining hall', 'To the boys of today'],
        'news' => ['Debate team wins state finals', 'Founders\' Day preparations begin', 'Old boys donate library books'],
    ];

    public static function allowed(): bool
    {
        return ! app()->environment('production') || config('redstar.allow_demo');
    }

    public static function count(): int { return User::where('is_demo', true)->count(); }

    /** Replaces any existing demo data with $count fresh demo users (1–25). */
    public static function seed(int $count): int
    {
        $count = max(1, min(25, $count));
        self::purge();
        $pages = Edition::published()->whereNotNull('page_count')->get(['id', 'page_count']);

        DB::transaction(function () use ($count, $pages) {
            for ($i = 0; $i < $count; $i++) {
                $category = self::CATEGORIES[array_rand(self::CATEGORIES)];
                $first = in_array($category, ['teacher', 'friend'], true) && random_int(0, 1)
                    ? self::OTHERS[array_rand(self::OTHERS)] : self::BOYS[array_rand(self::BOYS)];
                $last = self::SURNAMES[array_rand(self::SURNAMES)];
                $username = Str::lower('demo_'.$first.'_'.Str::random(4));
                $user = new User();
                $user->forceFill([
                    'name' => "$first $last",
                    'username' => $username,
                    'email' => "$username@demo.redstar.invalid", // .invalid never delivers
                    'password' => Hash::make(Str::random(48)),    // random: demo accounts can't be logged into
                    'category' => $category,
                    'role' => 'member',
                    // GCU sets are named by year of ADMISSION; graduation ≈ admission + 6.
                    'admission_year' => $adm = match ($category) {
                        'student' => now()->year - random_int(0, 5),
                        'old_boy' => random_int(1960, now()->year - 7),
                        default => null,
                    },
                    'graduation_year' => $adm ? $adm + 6 : null,
                    'show_in_directory' => true,
                    'current_class' => $category === 'student' ? self::CLASSES[array_rand(self::CLASSES)] : null,
                    'house' => in_array($category, ['student', 'old_boy'], true) ? Roles::HOUSES[array_rand(Roles::HOUSES)] : null,
                    'email_verified_at' => now(),
                    'is_demo' => true,
                ])->save();

                for ($n = random_int(0, 2); $n > 0; $n--) {
                    $kind = array_rand(self::TITLES);
                    $title = self::TITLES[$kind][array_rand(self::TITLES[$kind])];
                    Submission::forceCreate([
                        'user_id' => $user->id, 'kind' => $kind, 'title' => $title, 'is_demo' => true,
                        'body' => "[Demo] $title — sample text written for testing the editorial workflow.",
                        'status' => ['submitted', 'in_review', 'accepted', 'declined'][random_int(0, 3)],
                    ]);
                }
                if ($pages->isNotEmpty() && random_int(0, 1)) {
                    $e = $pages->random();
                    Bookmark::forceCreate(['user_id' => $user->id, 'edition_id' => $e->id, 'is_demo' => true,
                        'page_number' => random_int(1, max(1, $e->page_count))]);
                }
            }
        });
        return $count;
    }

    /** Deletes only is_demo rows. Real users' submissions/bookmarks are never selected. */
    public static function purge(): int
    {
        return DB::transaction(function () {
            Bookmark::where('is_demo', true)->delete();
            Submission::where('is_demo', true)->delete();
            return User::where('is_demo', true)->delete();
        });
    }
}
