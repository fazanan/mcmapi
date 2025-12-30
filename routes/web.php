<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

// Route sementara untuk fix database di server
Route::get('/fix-migration-plugin', function () {
    try {
        Artisan::call('migrate:refresh', [
            '--path' => '/database/migrations/2025_12_30_000004_create_license_activations_plugin_table.php',
            '--force' => true
        ]);
        return "Migration Refreshed Successfully:\n" . Artisan::output();
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});
