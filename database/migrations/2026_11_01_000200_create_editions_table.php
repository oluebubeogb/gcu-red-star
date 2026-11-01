<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('editions', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->unsignedSmallInteger('year')->nullable()->index();
            $t->string('volume', 40)->nullable();
            $t->string('issue', 40)->nullable();
            $t->text('summary')->nullable();
            // Storage-abstracted paths (disk name + key), never absolute filesystem paths.
            $t->string('storage_disk', 40)->default('magazines');
            $t->string('pdf_path')->nullable();
            $t->string('cover_path')->nullable();
            $t->unsignedInteger('page_count')->nullable();
            $t->string('processing_status', 20)->default('pending'); // pending|processing|ready|failed
            $t->string('access', 20)->default('members');            // public|members
            $t->string('status', 20)->default('draft')->index();     // draft|review|published|archived
            $t->timestamp('published_at')->nullable()->index();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('kind', 30)->default('article'); // article|poem|photo|letter|news
            $t->text('body')->nullable();
            $t->string('status', 20)->default('submitted'); // submitted|in_review|accepted|declined
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('editions');
    }
};
