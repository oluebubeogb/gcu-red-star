<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 40)->nullable()->unique()->after('name');
            $t->string('category', 20)->default('student')->index()->after('email'); // student|old_boy|teacher|editorial|admin
            $t->string('role', 20)->default('member')->index()->after('category');   // member|editor|admin
            $t->unsignedSmallInteger('graduation_year')->nullable()->after('role');
            $t->string('current_class', 40)->nullable()->after('graduation_year');
            $t->string('house', 60)->nullable()->after('current_class');
        });
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['username','category','role','graduation_year','current_class','house']));
    }
};
