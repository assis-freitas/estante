<?php

use App\Http\Controllers\Shelf\AddBookController;
use App\Http\Controllers\Shelf\ReadingController;
use App\Http\Controllers\Shelf\ShelfController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'shelf')->name('dashboard');

    Route::get('shelf', [ShelfController::class, 'index'])->name('shelf.index');
    Route::get('shelf/add', [AddBookController::class, 'create'])->name('shelf.create');
    Route::get('shelf/lookup', [AddBookController::class, 'lookup'])->middleware('throttle:30,1')->name('shelf.lookup');
    Route::post('shelf', [AddBookController::class, 'store'])->name('shelf.store');

    Route::whereNumber('copy')->group(function () {
        Route::get('shelf/{copy}', [ShelfController::class, 'show'])->name('shelf.show');
        Route::delete('shelf/{copy}', [ShelfController::class, 'destroy'])->name('shelf.destroy');
        Route::patch('shelf/{copy}/status', [ReadingController::class, 'status'])->name('shelf.status');
        Route::patch('shelf/{copy}/review', [ReadingController::class, 'review'])->name('shelf.review');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
