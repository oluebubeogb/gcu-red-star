<?php

use App\Http\Controllers\AskController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommerceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Hq\CommerceController as HqCommerce;
use App\Http\Controllers\Hq\BulkUploadController as HqBulk;
use App\Http\Controllers\Hq\AiController as HqAi;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DigestController;
use App\Http\Controllers\Hq\CommunityController as HqCommunity;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Hq\EditionController as HqEditions;
use App\Http\Controllers\Hq\DashboardController as Hq;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public site
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/editions', [PublicController::class, 'editions'])->name('editions.index');
Route::get('/editions/{edition}', [PublicController::class, 'edition'])->name('editions.show');
Route::get('/editions/{edition}/cover', [PublicController::class, 'cover'])->name('editions.cover');
Route::get('/search', [PublicController::class, 'search'])->middleware('throttle:60,1')->name('search');
Route::get('/sets', [CommunityController::class, 'sets'])->name('sets.index');
Route::get('/sets/{year}', [CommunityController::class, 'set'])->whereNumber('year')->name('sets.show');
Route::get('/houses', [CommunityController::class, 'houses'])->name('houses.index');
Route::get('/houses/{slug}', [CommunityController::class, 'house'])->name('houses.show');
Route::get('/members/{user:username}', [CommunityController::class, 'member'])->name('members.show');
Route::get('/homecoming', [CommunityController::class, 'homecoming'])->name('homecoming');
Route::get('/digest/unsubscribe/{user}', [DigestController::class, 'unsubscribe'])->middleware('signed')->name('digest.unsubscribe');
Route::get('/ask', [AskController::class, 'show'])->name('ask');
Route::post('/ask', [AskController::class, 'ask'])->middleware('throttle:ask')->name('ask.store');
// Phase 6: sponsorships, directory, ads, payments (paid actions return 503 when Paystack keys are missing)
Route::get('/sponsor', [CommerceController::class, 'sponsor'])->name('sponsor.index');
Route::post('/sponsor', [CommerceController::class, 'storeSponsor'])->middleware('throttle:10,10')->name('sponsor.store');
Route::get('/directory', [CommerceController::class, 'directory'])->name('directory');
Route::get('/advertise', [CommerceController::class, 'advertise'])->name('advertise');
Route::post('/advertise', [CommerceController::class, 'storeAd'])->middleware('throttle:10,10')->name('advertise.store');
Route::get('/ads/{ad}/click', [CommerceController::class, 'clickAd'])->middleware('throttle:60,1')->name('ads.click');
Route::get('/payments/callback', [PaymentController::class, 'callback'])->middleware('throttle:30,1')->name('payments.callback');
Route::post('/payments/webhook', [PaymentController::class, 'webhook'])->name('payments.webhook');
Route::post('/payments/webhook/monnify', [PaymentController::class, 'webhookMonnify'])->name('payments.webhook.monnify');
Route::get('/archive', [PublicController::class, 'archive'])->name('archive');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/contribute', [PublicController::class, 'contribute'])->name('contribute');

// Private magazine files (signed, short-lived)
Route::get('/files/{key}', [FileController::class, 'show'])->where('key', '.*')
    ->middleware('signed:relative')->name('files.show');

// Members
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::post('/submissions', [SubmissionController::class, 'store'])->middleware('throttle:10,60')->name('submissions.store');
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::delete('/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
    Route::post('/editions/{edition}/tags', [TagController::class, 'store'])->middleware('throttle:30,1')->name('tags.store');
    Route::post('/tags/{tag}/claim', [TagController::class, 'claim'])->name('tags.claim');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');
    Route::get('/my/businesses', [CommerceController::class, 'myBusinesses'])->name('businesses.mine');
    Route::post('/my/businesses', [CommerceController::class, 'storeBusiness'])->middleware('throttle:10,60')->name('businesses.store');
    Route::post('/my/businesses/{business}', [CommerceController::class, 'updateBusiness'])->name('businesses.update');
    Route::delete('/my/businesses/{business}', [CommerceController::class, 'destroyBusiness'])->name('businesses.destroy');
    Route::post('/my/businesses/{business}/feature', [CommerceController::class, 'featureBusiness'])->middleware('throttle:10,10')->name('businesses.feature');
    Route::get('/editions/{edition}/print', [CommerceController::class, 'printForm'])->name('print.create');
    Route::post('/editions/{edition}/print', [CommerceController::class, 'storePrint'])->middleware('throttle:10,10')->name('print.store');
    Route::get('/orders', [CommerceController::class, 'orders'])->name('orders.index');
    Route::get('/editions/{edition}/download', [BookmarkController::class, 'download'])->middleware('throttle:20,1')->name('editions.download');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Editorial HQ
Route::middleware(['auth', 'verified', 'can:editorial'])->prefix('hq')->name('hq.')->group(function () {
    Route::get('/', [Hq::class, 'index'])->name('index');
    Route::patch('/submissions/{submission}', [Hq::class, 'updateSubmission'])->name('submissions.update');
    Route::get('/editions', [HqEditions::class, 'index'])->name('editions.index');
    Route::get('/editions/create', [HqEditions::class, 'create'])->name('editions.create');
    Route::post('/editions', [HqEditions::class, 'store'])->name('editions.store');
    Route::get('/editions/{edition:id}/edit', [HqEditions::class, 'edit'])->name('editions.edit');
    Route::put('/editions/{edition:id}', [HqEditions::class, 'update'])->name('editions.update');
    Route::post('/editions/{edition:id}/reprocess', [HqEditions::class, 'reprocess'])->name('editions.reprocess');
    Route::post('/editions/{edition:id}/publish', [HqEditions::class, 'publish'])->name('editions.publish');
    Route::delete('/editions/{edition:id}', [HqEditions::class, 'destroy'])->name('editions.destroy');
    Route::get('/community', [HqCommunity::class, 'index'])->name('community');
    Route::get('/ai', [HqAi::class, 'index'])->name('ai');
    Route::get('/commerce', [HqCommerce::class, 'index'])->name('commerce');
    Route::post('/commerce/packages', [HqCommerce::class, 'storePackage'])->name('packages.store');
    Route::put('/commerce/packages/{package}', [HqCommerce::class, 'updatePackage'])->name('packages.update');
    Route::delete('/commerce/packages/{package}', [HqCommerce::class, 'destroyPackage'])->name('packages.destroy');
    Route::put('/commerce/sponsorships/{sponsorship}', [HqCommerce::class, 'reviewSponsorship'])->name('sponsorships.review');
    Route::put('/commerce/businesses/{business:id}', [HqCommerce::class, 'reviewBusiness'])->name('businesses.review');
    Route::put('/commerce/ads/{ad}', [HqCommerce::class, 'reviewAd'])->name('ads.review');
    Route::put('/commerce/slots/{slot}', [HqCommerce::class, 'updateSlot'])->name('slots.update');
    Route::put('/commerce/featured-price', [HqCommerce::class, 'updateFeaturedPrice'])->name('featured.price');
    Route::put('/commerce/print/{edition:id}', [HqCommerce::class, 'updatePrint'])->name('print.update');
    Route::put('/commerce/orders/{order}', [HqCommerce::class, 'updateOrder'])->name('orders.update');
    Route::get('/revenue', [HqCommerce::class, 'revenue'])->name('revenue');
    Route::get('/revenue/export', [HqCommerce::class, 'export'])->name('revenue.export');
    Route::get('/bulk', [HqBulk::class, 'index'])->name('bulk');
    Route::post('/bulk/upload', [HqBulk::class, 'upload'])->name('bulk.upload');
    Route::post('/bulk/folder', [HqBulk::class, 'folder'])->name('bulk.folder');
    Route::post('/bulk/auto-publish', [HqBulk::class, 'autoPublish'])->name('bulk.auto');
    Route::post('/ai/settings', [HqAi::class, 'settings'])->name('ai.settings');
    Route::post('/ai/rebuild', [HqAi::class, 'rebuild'])->middleware('throttle:3,10')->name('ai.rebuild');
    Route::get('/editions/{edition:id}/ai', [HqAi::class, 'edition'])->name('editions.ai');
    Route::post('/editions/{edition:id}/ai/reindex', [HqAi::class, 'reindex'])->name('editions.ai.reindex');
    Route::post('/editions/{edition:id}/ai/summarize', [HqAi::class, 'summarize'])->middleware('throttle:10,10')->name('editions.ai.summarize');
    Route::post('/editions/{edition:id}/ai/ocr', [HqAi::class, 'cleanOcr'])->middleware('throttle:10,10')->name('editions.ai.ocr');
    Route::post('/editions/{edition:id}/ai/audio', [HqAi::class, 'audio'])->middleware('throttle:10,10')->name('editions.ai.audio');
    Route::put('/ai/summaries/{summary}', [HqAi::class, 'updateSummary'])->name('ai.summaries.update');
    Route::put('/ai/pages/{page}', [HqAi::class, 'reviewOcr'])->name('ai.pages.update');
    Route::post('/submissions/{submission}/assistant', [HqAi::class, 'reviewSubmission'])->middleware('throttle:30,1')->name('submissions.assistant');
    Route::post('/events', [HqCommunity::class, 'storeEvent'])->name('events.store');
    Route::put('/events/{event}', [HqCommunity::class, 'updateEvent'])->name('events.update');
    Route::delete('/events/{event}', [HqCommunity::class, 'destroyEvent'])->name('events.destroy');
    Route::post('/announcements', [HqCommunity::class, 'storeAnnouncement'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [HqCommunity::class, 'updateAnnouncement'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [HqCommunity::class, 'destroyAnnouncement'])->name('announcements.destroy');
    Route::post('/features', [HqCommunity::class, 'storeFeature'])->name('features.store');
    Route::delete('/features/{feature}', [HqCommunity::class, 'destroyFeature'])->name('features.destroy');
    Route::post('/digest/test', [HqCommunity::class, 'testDigest'])->middleware('throttle:5,1')->name('digest.test');
    Route::get('/members', [Hq::class, 'members'])->name('members');
    Route::post('/demo', [Hq::class, 'seedDemo'])->middleware('throttle:10,1')->name('demo.seed');
    Route::delete('/demo', [Hq::class, 'purgeDemo'])->name('demo.purge');
    Route::patch('/members/{user}', [Hq::class, 'updateMember'])->name('members.update');
});

require __DIR__.'/auth.php';
