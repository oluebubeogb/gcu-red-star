<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phase 3: explicit demo markers + pg_trgm typo-tolerant search (Postgres only). */
return new class extends Migration {
    public function up(): void
    {
        foreach (['users', 'submissions', 'bookmarks'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->boolean('is_demo')->default(false)->index());
        }

        if (DB::getDriverName() !== 'pgsql') return;
        // pg_trgm is a "trusted" extension (PG13+): the database owner can create it without superuser.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS edition_pages_text_trgm ON edition_pages USING GIN (text gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS editions_title_trgm ON editions USING GIN (title gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_name_trgm ON users USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_username_trgm ON users USING GIN (username gin_trgm_ops)');
        // Vocabulary of published editions for "did you mean" (refreshed on publish/unpublish).
        // Schema-qualified: PG17+ runs materialized-view queries with search_path = pg_catalog.
        $schema = DB::selectOne('select current_schema() as s')->s;
        DB::statement(<<<SQL
            CREATE MATERIALIZED VIEW IF NOT EXISTS search_words AS
            SELECT word, ndoc FROM pg_catalog.ts_stat(\$\$
                SELECT pg_catalog.to_tsvector('pg_catalog.simple', coalesce(p.text, '')) FROM "{$schema}".edition_pages p
                JOIN "{$schema}".editions e ON e.id = p.edition_id WHERE e.status = 'published'
            \$\$) WHERE length(word) >= 3 AND word ~ '^[[:alpha:]]+$'
        SQL);
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS search_words_word ON search_words (word)');
        DB::statement('CREATE INDEX IF NOT EXISTS search_words_trgm ON search_words USING GIN (word gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS search_words');
            foreach (['edition_pages_text_trgm', 'editions_title_trgm', 'users_name_trgm', 'users_username_trgm'] as $i) {
                DB::statement("DROP INDEX IF EXISTS $i");
            }
        }
        foreach (['users', 'submissions', 'bookmarks'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex("{$table}_is_demo_index");
                $t->dropColumn('is_demo');
            });
        }
    }
};
