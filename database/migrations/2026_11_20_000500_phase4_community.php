<?php
use App\Support\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phase 4: sets (by year of admission), houses, tags, homecoming events, announcements, Old Boy of the month, digest. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // GCU sets are named by year of ADMISSION ("Class of 1975" = admitted 1975).
            $t->unsignedSmallInteger('admission_year')->nullable()->index()->after('graduation_year');
            $t->boolean('show_in_directory')->default(true)->after('house'); // public profile + set/house pages
            $t->boolean('digest_opt_in')->default(false)->after('show_in_directory');
            $t->boolean('notify_email')->default(true)->after('digest_opt_in'); // email me when tagged
            $t->index('house');
        });

        // Normalise free-text houses to the fixed list; unknown values become null.
        foreach (DB::table('users')->whereNotNull('house')->distinct()->pluck('house') as $raw) {
            DB::table('users')->where('house', $raw)->update(['house' => Roles::normalizeHouse($raw)]);
        }

        Schema::create('page_tags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('edition_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page_number');
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // tagged member…
            $t->string('name', 120)->nullable();                                    // …or a person without an account
            $t->string('note', 200)->nullable();
            $t->foreignId('tagged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['edition_id', 'page_number']);
        });

        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('kind', 30)->default('homecoming');
            $t->unsignedSmallInteger('set_year')->nullable()->index(); // jubilee set (admission year)
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->string('venue')->nullable();
            $t->text('description')->nullable();
            $t->string('cover_path')->nullable(); // MagazineStorage key
            $t->boolean('published')->default(false)->index();
            $t->timestamps();
        });

        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body');
            $t->unsignedSmallInteger('set_year')->nullable()->index(); // set news
            $t->string('house', 60)->nullable()->index();             // house news
            $t->boolean('published')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('featured_members', function (Blueprint $t) { // "Old Boy of the month"
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('month')->unique(); // first day of the month
            $t->text('blurb');
            $t->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Real record: Class of 1975 hosted the GCUOBA Homecoming & AGM, 12–14 Dec 2025 (golden jubilee).
        DB::table('events')->insertOrIgnore([
            'title' => 'GCUOBA Homecoming & AGM — Class of 1975 Golden Jubilee',
            'slug' => 'homecoming-2025-class-of-1975',
            'kind' => 'homecoming',
            'set_year' => 1975,
            'starts_on' => '2025-12-12',
            'ends_on' => '2025-12-14',
            'venue' => 'Government College Umuahia, Abia State',
            'description' => 'The Government College Umuahia Class of 1975 hosted the Government College Umuahia Old Boys Association Homecoming and Annual General Meeting from December 12 to 14, 2025, marking their golden jubilee reunion — 50 years since admission.',
            'published' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_members');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('events');
        Schema::dropIfExists('page_tags');
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['house']);
            $t->dropIndex(['admission_year']);
            $t->dropColumn(['admission_year', 'show_in_directory', 'digest_opt_in', 'notify_email']);
        });
    }
};
