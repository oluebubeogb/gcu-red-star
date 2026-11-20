<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('bio')->nullable();
            $t->string('avatar_path')->nullable();
            $t->string('pronouns', 30)->nullable();
        });
        foreach (['editions', 'events', 'announcements', 'featured_members', 'businesses', 'sponsorships', 'sponsorship_packages', 'ads', 'page_tags'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'is_demo')) {
                Schema::table($table, fn (Blueprint $t) => $t->boolean('is_demo')->default(false)->index());
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['bio', 'avatar_path', 'pronouns']));
        foreach (['editions', 'events', 'announcements', 'featured_members', 'businesses', 'sponsorships', 'sponsorship_packages', 'ads', 'page_tags'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_demo')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    try { $t->dropIndex("{$table}_is_demo_index"); } catch (\Throwable) {}
                    $t->dropColumn('is_demo');
                });
            }
        }
    }
};
