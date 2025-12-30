<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\CheckActivationController;
use App\Http\Controllers\Api\CheckActivationPluginController;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/licenses', function () { return view('licenses.index'); })->name('licenses.index');
    Route::get('/licenselogs', function () { return view('licenselogs.index'); })->name('licenselogs.index');
});

// API Routes (Dipindah ke sini karena Laravel 11 defaultnya tidak ada routes/api.php jika installasi minim)
// Atau jika ingin tetap dipisah, pastikan install api:install
Route::prefix('api')->group(function () {
    Route::post('/check_activation', [CheckActivationController::class, 'checkActivation']);
    Route::post('/check_activation_plugin', [CheckActivationPluginController::class, 'checkActivation']);
});

// Remove require auth.php as it does not exist
// require __DIR__.'/auth.php';
