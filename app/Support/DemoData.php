<?php
namespace App\Support;

use App\Models\Ad;
use App\Models\Announcement;
use App\Models\Bookmark;
use App\Models\Business;
use App\Models\Edition;
use App\Models\Event;
use App\Models\FeaturedMember;
use App\Models\PageTag;
use App\Models\Sponsorship;
use App\Models\SponsorshipPackage;
use App\Models\Submission;
use App\Models\User;
use App\Storage\MagazineStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * HQ one-click seed. Every record is flagged is_demo so purge() never touches real data.
 * Frontend copy talks about "seeded content"; this class name is internal only.
 */
final class DemoData
{
    /** First names of the 25 Nigerian men used in the roster (two share "Chukwuemeka"). */
    public const FIRST_NAMES = [
        'Chukwuemeka', 'Chukwuemeka', 'Obinna', 'Ikenna', 'Chinedu', 'Nnamdi', 'Ifeanyi', 'Kelechi',
        'Uchenna', 'Chidi', 'Somtochukwu', 'Ebuka', 'Tobenna', 'Kenechukwu', 'Oluwaseun', 'Tunde',
        'Babatunde', 'Musa', 'Ibrahim', 'Efosa', 'Ekene', 'Chibuike', 'Okechukwu', 'Chukwudi', 'Ndubuisi',
    ];

    public const SURNAMES = [
        'Okafor', 'Nwosu', 'Eze', 'Okonkwo', 'Uchendu', 'Nwachukwu', 'Okeke', 'Obi', 'Onyekachi', 'Ibe',
        'Anyanwu', 'Kalu', 'Nwankwo', 'Agu', 'Adeyemi', 'Balogun', 'Bello', 'Osagie', 'Ezeani', 'Iwu',
        'Mbah', 'Orji', 'Okoro', 'Uche', 'Onyema',
    ];

    private const CLASSES = ['JSS1', 'JSS2', 'JSS3', 'SS1', 'SS2', 'SS3'];
    private const CATEGORIES = ['old_boy', 'old_boy', 'student', 'student', 'teacher', 'friend', 'old_boy', 'student'];
    public const PASSWORD = 'P@ssw0rd';

    public static function allowed(): bool
    {
        return ! app()->environment('production') || config('redstar.allow_demo');
    }

    public static function count(): int { return User::where('is_demo', true)->count(); }

    /** Replaces any existing seeded data with $count fresh members (1–25) and archive content. */
    public static function seed(int $count): int
    {
        $count = max(1, min(25, $count));
        self::purge();

        DB::transaction(function () use ($count) {
            $users = self::seedUsers($count);
            $editions = self::seedEditions($users->first());
            self::seedCommunity($users, $editions);
            self::seedCommerce($users, $editions);
        });
        ArchiveSearch::refreshVocabulary();
        return $count;
    }

    /** Deletes only is_demo rows and the files they created. Real records are never selected. */
    public static function purge(): int
    {
        return DB::transaction(function () {
            $keys = self::seededKeys();
            Bookmark::where('is_demo', true)->delete();
            PageTag::where('is_demo', true)->delete();
            Submission::where('is_demo', true)->delete();
            Ad::where('is_demo', true)->delete();
            Sponsorship::where('is_demo', true)->delete();
            Business::where('is_demo', true)->delete();
            FeaturedMember::where('is_demo', true)->delete();
            Announcement::where('is_demo', true)->delete();
            Event::where('is_demo', true)->delete();
            Edition::where('is_demo', true)->get()->each->delete();
            SponsorshipPackage::where('is_demo', true)->delete();
            $n = User::where('is_demo', true)->delete();
            $storage = app(MagazineStorage::class);
            foreach ($keys as $key) {
                try { $storage->delete($key); } catch (\Throwable) {}
            }
            self::forgetPrefix('seed');
            return $n;
        });
    }

    private static function seedUsers(int $count)
    {
        $usedEmails = [];
        $usedNames = [];
        $users = collect();
        $houses = Roles::HOUSES;
        $oldBoyYears = [1975, 1976, 1962, 1968, 1981, 1990, 1998, 2005, 2012, 1965, 1987];

        for ($i = 0; $i < $count; $i++) {
            $first = self::FIRST_NAMES[$i];
            $last = self::SURNAMES[$i];
            $category = $i === 0 ? 'old_boy' : ($i === 1 && $count > 1 ? 'old_boy' : self::CATEGORIES[$i % count(self::CATEGORIES)]);
            $school = in_array($category, Roles::SCHOOL, true);
            $adm = null;
            if ($category === 'old_boy') {
                $adm = $i === 0 ? 1975 : ($i === 1 ? 1976 : $oldBoyYears[$i % count($oldBoyYears)]);
            } elseif ($category === 'student') {
                $adm = now()->year - ($i % 6);
            }
            $house = ($school || $category === 'teacher') ? $houses[$i % count($houses)] : null;
            $email = self::emailFor($first, $last, $usedEmails);
            $username = self::usernameFor($first, $last, $usedNames);
            $initials = mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
            $avatar = SeedArt::avatar("seed/avatars/{$username}.webp", $initials, $house ?? '');

            $user = new User();
            $user->forceFill([
                'name' => "$first $last",
                'username' => $username,
                'email' => $email,
                'password' => self::PASSWORD,
                'category' => $category,
                'role' => $category === 'teacher' && $i === 4 ? 'editor' : 'member',
                'admission_year' => $adm,
                'graduation_year' => $adm ? $adm + 6 : null,
                'show_in_directory' => true,
                'current_class' => $category === 'student' ? self::CLASSES[$i % 6] : null,
                'house' => $house,
                'bio' => self::bio($first, $category, $adm, $house),
                'pronouns' => 'he/him',
                'avatar_path' => $avatar,
                'digest_opt_in' => true,
                'notify_email' => true,
                'email_verified_at' => now(),
                'is_demo' => true,
            ])->save();
            $users->push($user);
        }
        return $users;
    }

    /** firstname.gcu@collab.name.ng, or firstname.lastname.gcu@… when the first name is already taken. */
    public static function emailFor(string $first, string $last, array &$used): string
    {
        $f = Str::lower($first);
        $l = Str::lower($last);
        $candidates = ["$f.gcu@collab.name.ng", "$f.$l.gcu@collab.name.ng"];
        foreach ($candidates as $email) {
            if (! isset($used[$email]) && ! User::where('email', $email)->exists()) {
                $used[$email] = true;
                return $email;
            }
        }
        $i = 2;
        do {
            $email = "$f.$l$i.gcu@collab.name.ng";
            $i++;
        } while (isset($used[$email]) || User::where('email', $email)->exists());
        $used[$email] = true;
        return $email;
    }

    private static function usernameFor(string $first, string $last, array &$used): string
    {
        $base = Str::slug("$first-$last") ?: 'oldboy';
        $name = $base;
        $i = 2;
        while (isset($used[$name]) || User::where('username', $name)->exists()) {
            $name = $base.$i;
            $i++;
        }
        $used[$name] = true;
        return $name;
    }

    private static function bio(string $first, string $category, ?int $year, ?string $house): string
    {
        $h = $house ? " a son of $house," : '';
        return match ($category) {
            'old_boy' => "$first is an Old Boy of Government College Umuahia, admitted in $year.$h He still talks about morning assembly on the parade ground, and he returns whenever the College calls her sons home.",
            'student' => "$first is a current student of Government College Umuahia$h in the Class of $year. He reads The Red Star in the library after prep and hopes to write for it before he leaves.",
            'teacher' => "$first teaches at Government College Umuahia".($house ? " and looks after $house" : '').". He says the best hours of his week are still those spent with the boys in class and on the field.",
            'friend' => "$first is a friend of Government College Umuahia. He has stood with the College for years, and he is glad to walk with Old Boys and students as they keep the archive alive.",
            default => "$first belongs to the community of Government College Umuahia. He is proud to be counted among her sons and friends.",
        };
    }

    private static function seedEditions(User $uploader)
    {
        $created = collect();
        foreach (self::editionBlueprints() as $spec) {
            $slug = $spec['slug'];
            $cover = SeedArt::cover("seed/editions/{$slug}/cover.webp", $spec['title'], $spec['year'], 'The Red Star');
            $pdfPages = [];
            $edition = Edition::create([
                'title' => $spec['title'],
                'slug' => $slug,
                'year' => $spec['year'],
                'volume' => $spec['volume'],
                'issue' => $spec['issue'],
                'summary' => $spec['summary'],
                'cover_path' => $cover,
                'access' => $spec['access'],
                'status' => 'published',
                'published_at' => $spec['published_at'],
                'processing_status' => 'ready',
                'processed_at' => now(),
                'page_count' => count($spec['pages']),
                'uploaded_by' => $uploader->id,
                'print_available' => $spec['print'] ?? false,
                'print_price_kobo' => ($spec['print'] ?? false) ? 500000 : null,
                'print_stock' => ($spec['print'] ?? false) ? 40 : 0,
                'issue_date' => $spec['published_at']->toDateString(),
                'is_demo' => true,
            ]);
            foreach ($spec['pages'] as $i => $page) {
                $n = $i + 1;
                $img = SeedArt::page("seed/editions/{$slug}/page-{$n}.webp", $page['title'], $page['body'], $n, $spec['year'], $page['kicker']);
                $edition->pages()->create([
                    'page_number' => $n,
                    'text' => $page['title']."\n\n".$page['body'],
                    'ocr' => false,
                    'image_key' => "seed/editions/{$slug}/page-{$n}.webp",
                    'width' => $img['width'],
                    'height' => $img['height'],
                ]);
                $edition->blocks()->create(['page_number' => $n, 'position' => 1, 'type' => 'heading', 'text' => $page['title']]);
                $pos = 2;
                foreach (preg_split('/\n\s*\n/', trim($page['body'])) as $para) {
                    if (trim($para) === '') continue;
                    $edition->blocks()->create(['page_number' => $n, 'position' => $pos++, 'type' => 'paragraph', 'text' => trim($para)]);
                }
                $pdfPages[] = $page;
            }
            $edition->forceFill(['pdf_path' => SeedArt::pdf("seed/editions/{$slug}/edition.pdf", $spec['title'], $spec['year'], $pdfPages)])->save();
            $created->push($edition);
        }
        return $created;
    }

    private static function seedCommunity($users, $editions): void
    {
        $statuses = ['submitted', 'in_review', 'accepted', 'declined'];
        $pieces = [
            ['article', 'Life in the boarding house', "The rising bell still finds him half in a dream. He crosses the quadrangle in the harmattan haze, bucket in hand, and the red earth of Umuahia is cold under his feet.\n\nIn the dormitory he has learned the small courtesies that keep a house together: a shared lantern, a borrowed iron, a word for a boy who is new and far from home. Government College Umuahia is a school of boys, and he is proud to be one of them."],
            ['poem', 'The Bell at Dawn', "The bell at dawn is older than the boys who hear it.\nIt leans across the hills and names each house in turn.\nHe stands in line, shirt buttoned, and he remembers\nthat every Old Boy once stood here, listening for the same bronze voice."],
            ['letter', 'A letter to the boys of today', "I write as an Old Boy who walked these paths before you. The buildings have changed, and some of the trees are taller than I left them, but the College still asks the same of her sons: to work hard, to be kind, and to remember one another.\n\nWhen you open The Red Star, you are reading a conversation that has lasted for generations. Keep it going."],
            ['news', 'Debate team reaches the state finals', "The College debate team, led by boys from Fisher and Wareham, has reached the state finals. He who speaks for Umuahia this year carries the same brief the College has always given her sons: be clear, be fair, and stand your ground."],
        ];
        foreach ($users as $i => $user) {
            $piece = $pieces[$i % count($pieces)];
            Submission::forceCreate([
                'user_id' => $user->id, 'kind' => $piece[0], 'title' => $piece[1],
                'body' => $piece[2], 'status' => $statuses[$i % 4], 'is_demo' => true,
            ]);
        }

        foreach ($editions as $i => $edition) {
            $user = $users[$i % $users->count()];
            Bookmark::forceCreate([
                'user_id' => $user->id, 'edition_id' => $edition->id, 'is_demo' => true,
                'page_number' => 1, 'note' => 'To reread on Sunday evening.',
            ]);
            $tagged = $users->firstWhere('admission_year', 1975) ?? $users->first();
            PageTag::forceCreate([
                'edition_id' => $edition->id, 'page_number' => min(4, $edition->page_count),
                'user_id' => $tagged->id, 'tagged_by' => $user->id,
                'note' => 'Pictured with his set.', 'is_demo' => true,
            ]);
        }

        $cover = SeedArt::cover('seed/events/homecoming-2026.webp', 'Homecoming 2026', 2026, 'GCUOBA');
        Event::create([
            'title' => 'GCUOBA Homecoming & AGM — Class of 1976 Golden Jubilee',
            'slug' => 'homecoming-2026-class-of-1976',
            'kind' => 'homecoming',
            'set_year' => 1976,
            'starts_on' => '2026-12-11',
            'ends_on' => '2026-12-13',
            'venue' => 'Government College Umuahia, Abia State',
            'description' => 'The Government College Umuahia Class of 1976 invites Old Boys home for their golden jubilee reunion — fifty years since admission — and for the Association Annual General Meeting on the College grounds.',
            'cover_path' => $cover,
            'published' => true,
            'is_demo' => true,
        ]);
        Event::create([
            'title' => 'Inter-House Sports 2024',
            'slug' => 'inter-house-sports-2024',
            'kind' => 'sports',
            'starts_on' => '2024-02-17',
            'ends_on' => '2024-02-17',
            'venue' => 'College sports field, Government College Umuahia',
            'description' => 'Wareham and Fisher set the pace on a clear February afternoon, while Nile House took the relay that closed the day. Old Boys stood along the track and cheered the boys by name.',
            'published' => true,
            'is_demo' => true,
        ]);

        Announcement::create([
            'title' => 'Class of 1975 — photographs from Homecoming',
            'body' => 'The Class of 1975 has sent photographs from the December 2025 Homecoming and Annual General Meeting. They will appear in the next number of The Red Star. Any Old Boy who wishes to add a caption should write to the editors.',
            'set_year' => 1975, 'published' => true, 'published_at' => now()->subDays(8),
            'user_id' => $users->first()->id, 'is_demo' => true,
        ]);
        Announcement::create([
            'title' => 'Wareham House dinner',
            'body' => 'Wareham House will host a quiet dinner for Old Boys visiting Umuahia at the end of term. He who plans to attend should send word to the housemaster so a place can be kept for him.',
            'house' => 'Wareham House', 'published' => true, 'published_at' => now()->subDays(3),
            'user_id' => $users->first()->id, 'is_demo' => true,
        ]);

        $feature = $users->firstWhere('admission_year', 1975) ?? $users->first();
        $month = now()->startOfMonth();
        while (FeaturedMember::whereDate('month', $month->toDateString())->exists()) {
            $month->subMonth();
        }
        FeaturedMember::create([
            'user_id' => $feature->id,
            'month' => $month->toDateString(),
            'blurb' => $feature->name.' of the Class of 1975 returns often to the College he entered fifty years ago. He speaks quietly of the friends who walked the same corridors, and he has spent the year gathering memories for The Red Star.',
            'picked_by' => $users->first()->id,
            'is_demo' => true,
        ]);
    }

    private static function seedCommerce($users, $editions): void
    {
        $owner = $users->first();
        $listings = [
            ['Umuahia Hills Legal Practice', 'law', 'Aba', 'A chamber in Aba advising families and Old Boys on property and succession. He still takes calls from classmates after close of business.', true],
            ['Niger River Books', 'books', 'Enugu', 'An independent bookshop in Enugu stocking Nigerian letters, school readers and back numbers of school magazines.', false],
            ['Wareham Field Clinics', 'health', 'Umuahia', 'A small clinic near the College gate, open to boys, staff and Old Boys visiting home.', false],
        ];
        foreach ($listings as $i => $row) {
            if ($i >= $users->count()) break;
            $u = $users[$i];
            $logo = SeedArt::logo('seed/logos/'.Str::slug($row[0]).'.webp', $row[0]);
            Business::create([
                'user_id' => $u->id, 'name' => $row[0], 'slug' => Str::slug($row[0]),
                'category' => $row[1], 'description' => $row[3], 'state' => 'Abia', 'location' => $row[2],
                'phone' => '0803'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                'whatsapp' => '0803'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                'logo_path' => $logo, 'status' => 'approved',
                'featured_until' => $row[4] ? now()->addMonths(2) : null,
                'is_demo' => true,
            ]);
        }

        $package = SponsorshipPackage::create([
            'name' => 'Homecoming patron', 'target' => 'any', 'price_kobo' => 25000000,
            'perks' => 'Name and logo in The Red Star and in the Homecoming programme.',
            'active' => true, 'sort' => 50, 'is_demo' => true,
        ]);
        $home = $editions->first();
        foreach ([
            ['set', 'Class of 1975', 1975, null, 'edition', 'With thanks from the golden jubilee set.'],
            ['house', 'Wareham House Old Boys', null, 'Wareham House', 'homecoming', 'Wareham stands with the College at Homecoming.'],
            ['company', 'Umuahia Hills Legal Practice', null, null, 'edition', 'Proud to support The Red Star.'],
        ] as $i => $s) {
            $logo = SeedArt::logo('seed/logos/sponsor-'.$i.'.webp', $s[1]);
            Sponsorship::create([
                'package_id' => $package->id, 'user_id' => $owner->id,
                'sponsor_type' => $s[0], 'sponsor_name' => $s[1], 'set_year' => $s[2], 'house' => $s[3],
                'target' => $s[4], 'edition_id' => $s[4] === 'edition' ? $home->id : null,
                'homecoming_year' => $s[4] === 'homecoming' ? 2026 : null,
                'logo_path' => $logo, 'email' => $owner->email, 'message' => $s[5],
                'amount_kobo' => $package->price_kobo, 'status' => 'approved', 'paid_at' => now()->subDays(10),
                'is_demo' => true,
            ]);
        }

        $banner = SeedArt::logo('seed/ads/homecoming-banner.webp', 'Homecoming 2026');
        Ad::create([
            'user_id' => $owner->id, 'slot_key' => 'homepage_banner', 'kind' => 'ad',
            'advertiser' => 'GCUOBA Class of 1976', 'email' => $owner->email,
            'title' => 'Come home in December', 'body' => 'The Class of 1976 invites every Old Boy to the golden jubilee Homecoming on the College grounds, 11–13 December 2026.',
            'image_path' => $banner, 'audience' => 'all', 'periods' => 1,
            'starts_on' => now()->subDays(5)->toDateString(), 'ends_on' => now()->addDays(40)->toDateString(),
            'amount_kobo' => 3000000, 'status' => 'approved', 'paid_at' => now()->subDays(5),
            'is_demo' => true,
        ]);
        Ad::create([
            'user_id' => $owner->id, 'slot_key' => 'notice', 'kind' => 'congratulatory',
            'advertiser' => 'Class of 1975', 'email' => $owner->email,
            'title' => 'Congratulations to the Class of 1975',
            'body' => 'With pride and affection, Old Boys congratulate the Class of 1975 on a memorable golden jubilee Homecoming, 12–14 December 2025.',
            'audience' => 'all', 'periods' => 1,
            'starts_on' => now()->subDays(20)->toDateString(), 'ends_on' => now()->addDays(20)->toDateString(),
            'amount_kobo' => 500000, 'status' => 'approved', 'paid_at' => now()->subDays(20),
            'is_demo' => true,
        ]);
    }

    private static function editionBlueprints(): array
    {
        return [
            [
                'title' => 'Homecoming 2025',
                'slug' => 'homecoming-2025',
                'year' => 2025,
                'volume' => 'LXXX',
                'issue' => 'Special',
                'access' => 'public',
                'print' => true,
                'published_at' => Carbon::parse('2025-12-20'),
                'summary' => 'A special number of The Red Star on the golden jubilee of the Class of 1975, with reports from the December Homecoming, a short history of the College, house notes and verse.',
                'pages' => [
                    ['kicker' => 'Editorial', 'title' => 'From the Editor', 'body' => "This special number of The Red Star is given over to the Class of 1975, who came home in December to mark fifty years since they first walked through the College gates as boys.\n\nGovernment College Umuahia remains a school for boys, and the voices in these pages are the voices of her sons: those who still sit in class, those who teach them, and those who have gone out into the world and found their way back.\n\nWe have tried to write as the archive itself writes — carefully, and with affection — so that a boy opening this number in twenty years will know what the hills looked like in the year of the golden jubilee."],
                    ['kicker' => 'Homecoming', 'title' => 'The Golden Jubilee', 'body' => "The Class of 1975 hosted the Government College Umuahia Old Boys Association Homecoming and Annual General Meeting from December 12 to 14, 2025, marking their golden jubilee reunion — fifty years since admission.\n\nThey gathered on the College grounds in Umuahia. Morning began with a visit to the houses and a walk along the old paths; the afternoon was given to remembrance and to the Association meeting. In the evening the set dined together and named the friends who could not be present.\n\nOld Boys from later and earlier sets came to stand with them. The Red Star was there with a notebook, and much of what follows was first written in those three days."],
                    ['kicker' => 'History', 'title' => 'A College on the Umuahia Hills', 'body' => "Government College Umuahia was founded as a school for boys, and that character has never left it. From the first admission the College asked a boy to work hard, to stand straight, and to look after the boy beside him.\n\nThe hills have seen generations of houses — Cozens, Fisher, Niger, Nile, School, Simpson, Wareham and the rest — each with its colours and its songs. A boy who leaves carries the name of his house with him, and he is expected to answer when it is called.\n\nThe Red Star has tried, year after year, to set that life down in print: Speech Day, the sports field, the library, the quiet of Sunday letters home."],
                    ['kicker' => 'Alumni', 'title' => 'Sons of the Class of 1975', 'body' => "The men who entered the College in 1975 are scattered now across professions and cities, yet they speak of Umuahia as if they left last term. One practises law in Aba; another teaches; several have spent a lifetime in public service.\n\nAt Homecoming they walked the dormitories and argued, with great seriousness, about which tree had stood by the pavilion. He who had not been back in twenty years found the chapel first, then the house that had been his, and stood a long time in the doorway.\n\nWe print a few of their accounts here, not as a roll of honour, but as a record of how a set keeps faith with itself."],
                    ['kicker' => 'Houses', 'title' => 'Notes from the Houses', 'body' => "Wareham reports a strong year on the field and a quieter one in the library, which the housemaster regards as a respectable balance. Fisher sent a large party of Old Boys to the Homecoming and left a gift for the common room.\n\nNiger and Nile arranged a joint evensong. Cozens and School held a debate that ran long past the bell. Kent, New, Extension, Simpson and Erekosima each sent greetings to the Class of 1975, and each asked that their boys be remembered in these pages.\n\nHouse news, like house loyalty, is local and fierce. We print it gladly."],
                    ['kicker' => 'Verse', 'title' => 'The Bell at Dawn', 'body' => "The bell at dawn is older than the boys who hear it.\nIt leans across the hills and names each house in turn.\nHe stands in line, shirt buttoned, and he remembers\nthat every Old Boy once stood here, listening for the same bronze voice.\n\nGo well, sons of Umuahia, and come home when you are called."],
                ],
            ],
            [
                'title' => "Founders' Day 1976",
                'slug' => 'founders-day-1976',
                'year' => 1976,
                'volume' => 'XXXII',
                'issue' => "Founders' Day",
                'access' => 'public',
                'published_at' => Carbon::parse('1976-11-15'),
                'summary' => "The Founders' Day number for 1976: a short history of the College, sports notes, a letter from a master, and verse from the boys.",
                'pages' => [
                    ['kicker' => 'Editorial', 'title' => 'A word at Founders\' Day', 'body' => "Founders' Day asks the College to look backward without becoming still. In 1976 the boys of Government College Umuahia keep the same hours their predecessors kept, and they sing the same songs, though the world beyond the gate has changed.\n\nThis number records the year as we found it: the field, the classroom, the house, and the quiet pride of a school that continues to form men."],
                    ['kicker' => 'History', 'title' => 'How the College began', 'body' => "The story of Government College Umuahia is the story of a school raised for boys on these hills, staffed by men who believed that a thorough education was a public good.\n\nThe early sets left a pattern — scholarship, sport, and a fierce attachment to house — that later boys inherited without always knowing the names of those who first set it down.\n\nOn Founders' Day we name them, and we ask the present College to be worthy of the beginning."],
                    ['kicker' => 'Sports', 'title' => 'The year on the field', 'body' => "Inter-house sports were run in bright weather. The sprints went to Fisher; the distance races to Wareham; Nile took the relay that closed the afternoon. A boy from School House cleared the high jump at a height that will be talked about until next season.\n\nThe cricket eleven played with more heart than luck, and the football side found a late run of form. Masters and boys stood together on the boundary and agreed, as they do every year, that it had been a season worth writing down."],
                    ['kicker' => 'Letter', 'title' => 'A letter from a master', 'body' => "I have taught at Umuahia long enough to watch a boy arrive in short trousers and leave as a young man. He forgets, sometimes, how much of that change was his own work.\n\nTo the boys who will go out this year I offer only this: keep your curiosity, keep your friends, and keep a little of the College with you. The hills will still be here when you come back."],
                    ['kicker' => 'Houses', 'title' => 'House notes, 1976', 'body' => "Each house sent a paragraph, and each paragraph claimed a victory of some kind — academic, athletic, or merely moral. We print them as they arrived, with only the spelling mended.\n\nCozens and Simpson report new books for the house libraries. Erekosima and Extension have repaired a window. New House and Kent have been rehearsing a play they decline, for the moment, to name."],
                    ['kicker' => 'Verse', 'title' => 'Harmattan', 'body' => "Dust on the window, dust on the book,\nThe hills go pale and the trees look old.\nHe blows the page and he tries to look\nAs if the cold were a thing he chose to hold."],
                ],
            ],
            [
                'title' => 'Inter-House Sports 2024',
                'slug' => 'inter-house-sports-2024',
                'year' => 2024,
                'volume' => 'LXXIX',
                'issue' => 'Sports',
                'access' => 'members',
                'published_at' => Carbon::parse('2024-03-01'),
                'summary' => 'A full account of Inter-House Sports 2024, with results, house rivalry, a note on Old Boys who came to watch, and a poem from the track.',
                'pages' => [
                    ['kicker' => 'Editorial', 'title' => 'A day for the houses', 'body' => "Once a year the College becomes a single field. Houses that have argued all term about prep and punctuality meet, at last, where the argument can be settled in lanes and measured in seconds.\n\nThis number of The Red Star is given to that day in 2024: the results, the crowds, the Old Boys along the rope, and the boys who ran until there was nothing left to give."],
                    ['kicker' => 'Sports', 'title' => 'Results of the day', 'body' => "Wareham House took the championship on a narrow margin from Fisher. Nile won the 4×100 metres relay in a time that reset the College mark. A junior boy from Cozens won the long jump with a leap that surprised even his housemaster.\n\nKent and New shared the tug of war after a pull that had to be taken twice. School House, Niger, Simpson, Erekosima and Extension filled the minor places with honour, which is to say they left the field exhausted and already talking about next year."],
                    ['kicker' => 'Houses', 'title' => 'Rivalry in the proper key', 'body' => "House rivalry at Government College Umuahia is a serious business conducted, on the whole, in good humour. A boy may spend a week inventing a chant and a morning painting a banner, then spend the afternoon cheering a runner from another house who deserves it.\n\nThat balance — to want to win, and to know how to lose — is part of what the College tries to teach. The sports field is simply the place where the lesson is hardest to ignore."],
                    ['kicker' => 'Alumni', 'title' => 'Old Boys at the games', 'body' => "A surprising number of Old Boys found their way to the track in 2024. Some had sons in the houses; some had only the memory of a race they once ran, or nearly ran, in the same lanes.\n\nThey stood in clusters by house colour and argued about starting orders from decades past. He who had been a captain in his day was asked, more than once, to tell the story again."],
                    ['kicker' => 'News', 'title' => 'Around the College', 'body' => "The science laboratories have new benches. The library has opened a shelf of Nigerian history at the request of the senior boys. Preparations for Founders' Day are already under way, and the choir has been heard after supper.\n\nThe editors thank the boys who sent notes from the houses, and they invite more of the same. The Red Star is only as strong as the College that writes to it."],
                    ['kicker' => 'Verse', 'title' => 'On the track', 'body' => "He hears the gun and the day goes narrow.\nThe lane is a country with a single road.\nBehind him his house is a single voice.\nAhead of him only the tape, and the debt he owes."],
                ],
            ],
        ];
    }

    private static function seededKeys(): array
    {
        $keys = [];
        foreach (User::where('is_demo', true)->whereNotNull('avatar_path')->pluck('avatar_path') as $k) $keys[] = $k;
        foreach (Edition::where('is_demo', true)->get() as $e) {
            if ($e->cover_path) $keys[] = $e->cover_path;
            if ($e->pdf_path) $keys[] = $e->pdf_path;
            foreach ($e->pages()->whereNotNull('image_key')->pluck('image_key') as $k) $keys[] = $k;
        }
        foreach (Event::where('is_demo', true)->whereNotNull('cover_path')->pluck('cover_path') as $k) $keys[] = $k;
        foreach (Business::where('is_demo', true)->whereNotNull('logo_path')->pluck('logo_path') as $k) $keys[] = $k;
        foreach (Sponsorship::where('is_demo', true)->whereNotNull('logo_path')->pluck('logo_path') as $k) $keys[] = $k;
        foreach (Ad::where('is_demo', true)->whereNotNull('image_path')->pluck('image_path') as $k) $keys[] = $k;
        foreach (Submission::where('is_demo', true)->whereNotNull('photo_path')->pluck('photo_path') as $k) $keys[] = $k;
        return array_values(array_unique(array_filter($keys)));
    }

    private static function forgetPrefix(string $prefix): void
    {
        try {
            $disk = app(MagazineStorage::class)->diskName();
            $fs = Storage::disk($disk);
            if (method_exists($fs, 'allFiles')) {
                foreach ($fs->allFiles($prefix) as $key) $fs->delete($key);
            }
        } catch (\Throwable) {}
    }
}
