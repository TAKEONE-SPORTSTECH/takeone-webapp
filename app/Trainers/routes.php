<?php

/*
| Personal training — the trainer's public page, the member-facing profile, and
| the reviews members leave on a club's instructors.
|
| Registered by App\Support\Modules\ModuleServiceProvider inside `web`, so each
| group below declares the exact middleware it was declared with in
| routes/web.php. Note the three are NOT alike, and that is deliberate:
|
|   /t/{user}            public — the QR-code page, reachable with no session.
|   /trainer/{user}      ['auth','two-factor'] — no `verified`, the same stack
|                        the explore group it sat in has always used.
|   /instructor/{id}/reviews
|                        ['auth','verified','two-factor'] — writing a review is a
|                        member action and demands a verified account.
*/

use App\Trainers\Controllers\InstructorReviewController;
use App\Trainers\Controllers\TrainerController;
use Illuminate\Support\Facades\Route;

// Public trainer page - no login required (used for QR code)
Route::get('/t/{user}', [TrainerController::class, 'showPublic'])->name('trainer.show.public');

// The signed-in trainer profile, reached from explore, packages and instructor lists.
Route::middleware(['auth', 'two-factor'])->group(function () {
    Route::get('/trainer/{user}', [TrainerController::class, 'show'])->name('trainer.show');
});

// Instructor Review routes
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/instructor/{instructorId}/reviews', [InstructorReviewController::class, 'index'])->name('instructor.reviews.index');
    Route::post('/instructor/{instructorId}/reviews', [InstructorReviewController::class, 'store'])->name('instructor.reviews.store')->middleware('throttle:social');
    Route::put('/instructor/reviews/{reviewId}', [InstructorReviewController::class, 'update'])->name('instructor.reviews.update')->middleware('throttle:social');
});
