<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'gateway')) {
            Schema::table('payments', function (Blueprint $t) {
                $t->string('gateway', 20)->default('paystack');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'gateway')) {
            Schema::table('payments', function (Blueprint $t) {
                $t->dropColumn('gateway');
            });
        }
    }
};
