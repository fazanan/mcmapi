<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CustomerLicenseController;
use App\Http\Controllers\Admin\VoiceOverTransactionController;
use App\Http\Controllers\Admin\ConfigApiKeyController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\WhatsappConfigController;
use App\Http\Controllers\Admin\VoiceJobController;
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

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    
    // Users Management
    Route::resource('users', UserController::class);
    
    // Customer Licenses Management
    Route::resource('licenses', CustomerLicenseController::class);
    
    // Voice Over Transactions
    Route::get('voice-transactions', [VoiceOverTransactionController::class, 'index'])->name('voice-transactions.index');
    
    // Config API Key Management
    Route::resource('config-api-keys', ConfigApiKeyController::class);

    // Order Management
    Route::resource('orders', OrderController::class);
    Route::post('orders/{order}/process', [OrderController::class, 'process'])->name('orders.process');
    
    // WhatsApp Config
    Route::get('whatsapp-config', [WhatsappConfigController::class, 'index'])->name('whatsapp-config.index');
    Route::post('whatsapp-config/store', [WhatsappConfigController::class, 'store'])->name('whatsapp-config.store');
    Route::post('whatsapp-config/test', [WhatsappConfigController::class, 'test'])->name('whatsapp-config.test');
    
    // Voice Jobs
    Route::get('voice-jobs', [VoiceJobController::class, 'index'])->name('voice-jobs.index');
});

// API Routes (Dipindah ke sini karena Laravel 11 defaultnya tidak ada routes/api.php jika installasi minim)
// Atau jika ingin tetap dipisah, pastikan install api:install
Route::prefix('api')->group(function () {
    Route::post('/check_activation', [CheckActivationController::class, 'checkActivation']);
    Route::post('/check_activation_plugin', [CheckActivationPluginController::class, 'checkActivation']);
});

// Remove require auth.php as it does not exist
// require __DIR__.'/auth.php';
