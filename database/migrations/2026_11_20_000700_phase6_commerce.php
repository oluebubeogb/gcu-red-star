<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Paystack transaction; `reference` is unique so callback + webhook can't double-fulfil.
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 64)->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('email');
            $t->string('purpose', 20)->index(); // sponsorship | listing | ad | print
            $t->nullableMorphs('payable');
            $t->unsignedBigInteger('amount_kobo');
            $t->string('currency', 3)->default('NGN');
            $t->string('gateway', 20)->default('paystack');
            $t->string('status', 20)->default('pending')->index(); // pending | success | failed
            $t->string('paystack_id')->nullable();
            $t->string('channel', 30)->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('paid_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('sponsorship_packages', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('target', 20)->default('any'); // edition | homecoming | any
            $t->unsignedBigInteger('price_kobo');
            $t->text('perks')->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });
        Schema::create('sponsorships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('package_id')->constrained('sponsorship_packages')->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sponsor_type', 20); // set | house | individual | company
            $t->string('sponsor_name', 160);
            $t->unsignedSmallInteger('set_year')->nullable();
            $t->string('house', 40)->nullable();
            $t->string('target', 20); // edition | homecoming
            $t->foreignId('edition_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('homecoming_year')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('website')->nullable();
            $t->string('email');
            $t->string('message', 300)->nullable();
            $t->unsignedBigInteger('amount_kobo');
            $t->string('status', 20)->default('pending_payment')->index(); // pending_payment | paid | approved | rejected
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('businesses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 160);
            $t->string('slug', 180)->unique();
            $t->string('category', 60)->index();
            $t->text('description');
            $t->string('state', 40)->index();
            $t->string('location', 160)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('whatsapp', 30)->nullable();
            $t->string('website')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $t->string('review_note', 300)->nullable();
            $t->timestamp('featured_until')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('ad_slots', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();
            $t->string('name', 80);
            $t->string('kind', 20)->default('ad'); // ad | notice
            $t->unsignedBigInteger('price_kobo'); // per period
            $t->unsignedSmallInteger('period_days')->default(30);
            $t->string('size_hint', 80)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        $now = now();
        DB::table('ad_slots')->insert([
            ['key' => 'homepage_banner', 'name' => 'Homepage banner', 'kind' => 'ad', 'price_kobo' => 3000000, 'period_days' => 30, 'size_hint' => '1200×300 image', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'edition_sidebar', 'name' => 'Edition page sidebar', 'kind' => 'ad', 'price_kobo' => 1500000, 'period_days' => 30, 'size_hint' => '600×600 image', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'digest', 'name' => 'Monthly digest email', 'kind' => 'ad', 'price_kobo' => 1000000, 'period_days' => 30, 'size_hint' => 'Text + optional 1200×300 image', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notice', 'name' => 'Congratulatory / obituary notice', 'kind' => 'notice', 'price_kobo' => 500000, 'period_days' => 14, 'size_hint' => 'Text + optional photo', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        Schema::create('ads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('slot_key', 40)->index();
            $t->string('kind', 20)->default('ad'); // ad | congratulatory | obituary
            $t->string('advertiser', 160);
            $t->string('email');
            $t->string('title', 160);
            $t->text('body')->nullable();
            $t->string('image_path')->nullable();
            $t->string('link_url')->nullable();
            $t->string('audience', 10)->default('all'); // all | guests | members
            $t->unsignedSmallInteger('periods')->default(1);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->unsignedBigInteger('amount_kobo');
            $t->string('status', 20)->default('pending_payment')->index(); // pending_payment | paid | approved | rejected
            $t->string('review_note', 300)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->unsignedInteger('impressions')->default(0);
            $t->unsignedInteger('clicks')->default(0);
            $t->timestamps();
        });

        Schema::table('editions', function (Blueprint $t) {
            $t->boolean('print_available')->default(false);
            $t->unsignedBigInteger('print_price_kobo')->nullable();
            $t->unsignedInteger('print_stock')->default(0);
            $t->date('issue_date')->nullable();
            $t->string('import_batch', 40)->nullable()->index();
            $t->boolean('auto_publish')->default(false);
        });
        Schema::create('print_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('edition_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('quantity');
            $t->unsignedBigInteger('unit_kobo');
            $t->unsignedBigInteger('amount_kobo');
            $t->string('fulfilment', 10); // delivery | pickup
            $t->string('name', 120);
            $t->string('phone', 30);
            $t->string('address', 255)->nullable();
            $t->string('city', 80)->nullable();
            $t->string('state', 40)->nullable();
            $t->string('status', 20)->default('pending_payment')->index(); // pending_payment | paid | printing | shipped | ready | delivered | cancelled
            $t->string('tracking_note', 300)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_orders');
        Schema::table('editions', fn (Blueprint $t) => $t->dropColumn(['print_available', 'print_price_kobo', 'print_stock', 'issue_date', 'import_batch', 'auto_publish']));
        Schema::dropIfExists('ads');
        Schema::dropIfExists('ad_slots');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('sponsorship_packages');
        Schema::dropIfExists('payments');
    }
};
