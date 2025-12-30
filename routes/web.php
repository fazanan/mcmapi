<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    return view('welcome');
});

// Route untuk cek status data dan struktur tabel
Route::get('/check-db-status', function () {
    // Force enable error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    try {
        $results = [];

        // 1. Cek Jumlah Data (Memastikan data aman)
        try {
            $results['data_status'] = [
                'users_count' => \App\Models\User::count(),
                'customer_licenses_count' => \App\Models\CustomerLicense::count(),
            ];
        } catch (\Throwable $e) {
            $results['data_status'] = 'Error counting data: ' . $e->getMessage();
        }

        // 2. Cek Struktur Tabel license_activations_plugin
        try {
            $tableName = 'license_activations_plugin';
            if (Schema::hasTable($tableName)) {
                $columns = Schema::getColumnListing($tableName);
                $hasLicenseKey = in_array('license_key', $columns);
                
                $results['table_structure'] = [
                    'table_exists' => true,
                    'columns' => $columns,
                    'status' => $hasLicenseKey ? 'CORRECT (license_key exists)' : 'INCORRECT (still using license_id?)'
                ];
            } else {
                $results['table_structure'] = [
                    'table_exists' => false,
                    'status' => 'Table not found'
                ];
            }
        } catch (\Throwable $e) {
            $results['table_structure'] = 'Error checking schema: ' . $e->getMessage();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'System Check Results',
            'data' => $results
        ]);

    } catch (\Throwable $e) {
        return "FATAL ERROR: " . $e->getMessage();
    }
});

// Route sementara untuk fix database (JIKA PERLU DIJALANKAN LAGI)
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
