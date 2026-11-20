<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phase 2: processing results (pages + Comfort Reading blocks), bookmarks, photo submissions. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('editions', function (Blueprint $t) {
            $t->text('processing_error')->nullable()->after('processing_status');
            $t->timestamp('processed_at')->nullable()->after('processing_error');
        });

        // Written by the Python worker (worker/main.py).
        Schema::create('edition_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('edition_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page_number');
            $t->longText('text')->nullable();
            $t->boolean('ocr')->default(false);
            $t->string('image_key')->nullable(); // storage key of the WebP page preview
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->timestamps();
            $t->unique(['edition_id', 'page_number']);
        });
        if (DB::getDriverName() === 'pgsql') {
            // Generated column: the worker only writes `text`, Postgres keeps the tsvector in sync.
            DB::statement("ALTER TABLE edition_pages ADD COLUMN search tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(text, ''))) STORED");
            DB::statement('CREATE INDEX edition_pages_search_idx ON edition_pages USING GIN (search)');
        }

        // Comfort Reading: reflowable text blocks in reading order.
        Schema::create('edition_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('edition_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page_number');
            $t->unsignedInteger('position');
            $t->string('type', 20)->default('paragraph'); // heading|paragraph
            $t->text('text');
            $t->index(['edition_id', 'page_number', 'position']);
        });

        Schema::create('bookmarks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('edition_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page_number')->default(1);
            $t->string('note', 200)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'edition_id', 'page_number']);
        });

        Schema::table('submissions', function (Blueprint $t) {
            $t->string('photo_path')->nullable()->after('body'); // MagazineStorage key
        });
    }

    public function down(): void
    {
        Schema::table('submissions', fn (Blueprint $t) => $t->dropColumn('photo_path'));
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('edition_blocks');
        Schema::dropIfExists('edition_pages');
        Schema::table('editions', fn (Blueprint $t) => $t->dropColumn(['processing_error', 'processed_at']));
    }
};
