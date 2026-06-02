<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReminderListController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/lists', [ReminderListController::class, 'store'])->name('lists.store');
    Route::put('/lists/reorder', [ReminderListController::class, 'reorder'])->name('lists.reorder');
    Route::put('/lists/{list}', [ReminderListController::class, 'update'])->name('lists.update');
    Route::delete('/lists/{list}', [ReminderListController::class, 'destroy'])->name('lists.destroy');

    Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::put('/reminders/reorder', [ReminderController::class, 'reorder'])->name('reminders.reorder');
    Route::put('/reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
    Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
    Route::post('/reminders/{reminder}/complete', [ReminderController::class, 'complete'])->name('reminders.complete');
});

require __DIR__.'/settings.php';
