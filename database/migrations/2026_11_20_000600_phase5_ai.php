<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Phase 5 (AI via Replicate): chunks + embeddings, summaries/entities, OCR cleanup, audio, ask log, assistant, settings. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('edition_chunks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('edition_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page_number');
            $t->unsignedInteger('chunk_index');
            $t->text('text');
            $t->string('text_hash', 40);
            $t->longText('embedding_json')->nullable(); // fallback storage: JSON float array
            $t->string('embed_model')->nullable();
            $t->timestamp('embedded_at')->nullable()->index();
            $t->timestamps();
            $t->index(['edition_id', 'page_number']);
        });
        $this->tryPgvector();

        Schema::table('editions', function (Blueprint $t) {
            $t->timestamp('ai_indexed_at')->nullable();
        });

        Schema::create('ai_summaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('edition_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page_from')->nullable(); // null = whole edition
            $t->unsignedInteger('page_to')->nullable();
            $t->text('summary');
            $t->json('entities')->nullable(); // {people:[], places:[], events:[], years:[]}
            $t->string('status', 20)->default('draft')->index(); // draft|approved|rejected
            $t->string('model')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('edition_pages', function (Blueprint $t) {
            $t->longText('cleaned_text')->nullable();               // AI OCR cleanup (original `text` is kept)
            $t->string('cleaned_status', 20)->nullable()->index(); // pending|approved|rejected
        });
        if (DB::getDriverName() === 'pgsql') {
            // Full-text index uses the cleaned text once an editor approves it.
            DB::statement('DROP INDEX IF EXISTS edition_pages_search_idx');
            DB::statement('ALTER TABLE edition_pages DROP COLUMN IF EXISTS search');
            DB::statement("ALTER TABLE edition_pages ADD COLUMN search tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(CASE WHEN cleaned_status = 'approved' THEN cleaned_text END, text, ''))) STORED");
            DB::statement('CREATE INDEX edition_pages_search_idx ON edition_pages USING GIN (search)');
        }

        Schema::create('edition_audio', function (Blueprint $t) {
            $t->id();
            $t->foreignId('edition_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page_number');
            $t->string('audio_key')->nullable(); // MagazineStorage key
            $t->string('status', 20)->default('pending'); // pending|ready|failed
            $t->text('error')->nullable();
            $t->unsignedInteger('chars')->default(0);
            $t->timestamps();
            $t->unique(['edition_id', 'page_number']);
        });

        Schema::create('ask_logs', function (Blueprint $t) {
            $t->id();
            $t->text('question');
            $t->string('normalized', 300)->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('ip_hash', 64)->nullable();
            $t->boolean('answered')->default(false);
            $t->boolean('cached')->default(false);
            $t->timestamps();
        });

        Schema::table('submissions', function (Blueprint $t) {
            $t->json('ai_review')->nullable(); // {spam_score, flags[], headline, suggestions[]}
            $t->timestamp('ai_reviewed_at')->nullable();
        });
    }

    /** pgvector is optional: enable it if the server has it, otherwise embeddings live in embedding_json. */
    private function tryPgvector(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! config('ai.pgvector')) return;
        $dims = (int) config('ai.embed_dims', 768);
        try {
            if (! DB::selectOne("select 1 as ok from pg_available_extensions where name = 'vector'")) {
                Log::info('redstar: pgvector not available — using JSON embeddings fallback');
                return;
            }
            DB::transaction(function () use ($dims) { // savepoint: a failure must not abort the migration
                DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
                DB::statement("ALTER TABLE edition_chunks ADD COLUMN embedding vector($dims)");
                DB::statement('CREATE INDEX edition_chunks_embedding_hnsw ON edition_chunks USING hnsw (embedding vector_cosine_ops)');
            });
        } catch (\Throwable $e) {
            Log::warning('redstar: could not enable pgvector — using JSON embeddings fallback', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        Schema::table('submissions', fn (Blueprint $t) => $t->dropColumn(['ai_review', 'ai_reviewed_at']));
        Schema::dropIfExists('ask_logs');
        Schema::dropIfExists('edition_audio');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS edition_pages_search_idx');
            DB::statement('ALTER TABLE edition_pages DROP COLUMN IF EXISTS search');
            DB::statement("ALTER TABLE edition_pages ADD COLUMN search tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(text, ''))) STORED");
            DB::statement('CREATE INDEX edition_pages_search_idx ON edition_pages USING GIN (search)');
        }
        Schema::table('edition_pages', function (Blueprint $t) {
            $t->dropIndex(['cleaned_status']);
            $t->dropColumn(['cleaned_text', 'cleaned_status']);
        });
        Schema::dropIfExists('ai_summaries');
        Schema::table('editions', fn (Blueprint $t) => $t->dropColumn('ai_indexed_at'));
        Schema::dropIfExists('edition_chunks');
        Schema::dropIfExists('settings');
    }
};
