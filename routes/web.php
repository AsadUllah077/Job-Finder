<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\SavedJobController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'jobs.index'));
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => app(AuthController::class)->form('login'))->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', fn () => app(AuthController::class)->form('register'))->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/forgot-password', fn () => app(AuthController::class)->form('forgot'))->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::get('/alerts/unsubscribe/{user}', [PreferenceController::class, 'unsubscribe'])->middleware('signed')->name('alerts.unsubscribe');
Route::post('/alerts/unsubscribe/{user}', [PreferenceController::class, 'confirmUnsubscribe'])->middleware(['signed', 'throttle:5,1']);
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [JobController::class, 'dashboard'])->name('dashboard');
    Route::get('/saved-jobs', [SavedJobController::class, 'index'])->name('saved.index');
    Route::get('/saved-jobs/export', [SavedJobController::class, 'export'])->middleware('throttle:10,1')->name('saved.export');
    Route::post('/jobs/{job}/save', [SavedJobController::class, 'store'])->name('saved.store');
    Route::patch('/saved-jobs/{savedJob}', [SavedJobController::class, 'update'])->name('saved.update');
    Route::delete('/saved-jobs/{savedJob}', [SavedJobController::class, 'destroy'])->name('saved.destroy');
    Route::get('/preferences', [PreferenceController::class, 'edit'])->name('preferences.edit');
    Route::put('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');
    Route::delete('/search-history', [PreferenceController::class, 'clearHistory'])->name('history.destroy');
    Route::prefix('admin')->middleware(AdminOnly::class)->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::put('/sources/{source}', [AdminController::class, 'updateSource'])->name('sources.update');
        Route::post('/sources/{source}/sync', [AdminController::class, 'sync'])->middleware('throttle:6,1')->name('sources.sync');
        Route::put('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/users/{user}',[AdminController::class, 'user'])->name('users.update');
    });
});
