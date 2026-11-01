<?php

use App\Http\Controllers\FileController;
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
Route::get('/archive', [PublicController::class, 'archive'])->name('archive');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/contribute', [PublicController::class, 'contribute'])->name('contribute');

// Private magazine files (signed, short-lived)
Route::get('/files/{key}', [FileController::class, 'show'])->where('key', '.*')
    ->middleware('signed')->name('files.show');

// Members
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::post('/submissions', [SubmissionController::class, 'store'])->middleware('throttle:10,60')->name('submissions.store');
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
    Route::get('/members', [Hq::class, 'members'])->name('members');
    Route::patch('/members/{user}', [Hq::class, 'updateMember'])->name('members.update');
});

require __DIR__.'/auth.php';
