<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\CustomerLicense;

Route::get('/', function () {
    return view('welcome');
});

// Route untuk cek status data
Route::get('/check-db-status', function () {
    try {
        $userCount = User::count();
        $licenseCount = CustomerLicense::count();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Database check complete',
            'data' => [
                'users_count' => $userCount,
                'customer_licenses_count' => $licenseCount
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});
