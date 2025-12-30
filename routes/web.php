<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\CheckActivationController;
use App\Http\Controllers\Api\CheckActivationPluginController;
use App\Http\Controllers\AuthController;
use App\Models\CustomerLicense;
use Illuminate\Support\Str;

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
    Route::get('/customerlicense', function () {
        $q = request()->query('q');
        $query = CustomerLicense::query()->orderByDesc('updated_at');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('order_id', 'like', "%$q%")
                  ->orWhere('license_key', 'like', "%$q%")
                  ->orWhere('owner', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%")
                  ->orWhere('product_name', 'like', "%$q%");
            });
        }
        $rows = $query->get()->map(function ($m) {
            return [
                'Status' => $m->status,
                'OrderId' => $m->order_id,
                'LicenseKey' => $m->license_key,
                'Owner' => $m->owner,
                'Version' => $m->version,
                'Email' => $m->email,
                'Phone' => $m->phone,
                'Edition' => $m->edition,
                'PaymentStatus' => $m->payment_status,
                'DeliveryStatus' => $m->delivery_status,
                'DeliveryLog' => $m->delivery_log,
                'ProductName' => $m->product_name,
                'TenorDays' => $m->tenor_days,
                'IsActivated' => (bool)$m->is_activated,
                'ActivationDate' => optional($m->activation_date_utc)->toIso8601String(),
                'ExpiresAt' => optional($m->expires_at_utc)->toIso8601String(),
                'MaxhineId' => $m->machine_id,
                'DeviceId' => $m->device_id,
                'MaxSeatsShopeeScrap' => $m->max_seats_shopee_scrap,
                'UsedSeatsShopeeScrap' => $m->used_seats_shopee_scrap,
                'MaxSeats' => $m->max_seats,
                'MaxVideo' => $m->max_video,
                'Features' => $m->features,
                'LastUsed' => optional($m->last_used)->toIso8601String(),
            ];
        });
        return response()->json($rows);
    });
    Route::get('/customerlicense/{orderId}', function ($orderId) {
        $m = CustomerLicense::where('order_id', $orderId)->first();
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json([
            'Status' => $m->status,
            'OrderId' => $m->order_id,
            'LicenseKey' => $m->license_key,
            'Owner' => $m->owner,
            'Version' => $m->version,
            'Email' => $m->email,
            'Phone' => $m->phone,
            'Edition' => $m->edition,
            'PaymentStatus' => $m->payment_status,
            'DeliveryStatus' => $m->delivery_status,
            'DeliveryLog' => $m->delivery_log,
            'ProductName' => $m->product_name,
            'TenorDays' => $m->tenor_days,
            'IsActivated' => (bool)$m->is_activated,
            'ActivationDate' => optional($m->activation_date_utc)->toIso8601String(),
            'ExpiresAt' => optional($m->expires_at_utc)->toIso8601String(),
            'MaxSeats' => $m->max_seats,
            'MaxVideo' => $m->max_video,
            'Features' => $m->features,
            'MaxhineId' => $m->machine_id,
            'DeviceId' => $m->device_id,
            'RowVerBase64' => null,
            'MaxSeatsShopeeScrap' => $m->max_seats_shopee_scrap,
            'UsedSeatsShopeeScrap' => $m->used_seats_shopee_scrap,
        ]);
    });
    Route::put('/customerlicense/{orderId}', function ($orderId) {
        $m = CustomerLicense::where('order_id', $orderId)->first();
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        $p = request()->json()->all();
        $m->license_key = $p['LicenseKey'] ?? $m->license_key;
        $m->owner = $p['Owner'] ?? $m->owner;
        $m->email = $p['Email'] ?? $m->email;
        $m->phone = $p['Phone'] ?? $m->phone;
        $m->edition = $p['Edition'] ?? $m->edition;
        $m->payment_status = $p['PaymentStatus'] ?? $m->payment_status;
        $m->product_name = $p['ProductName'] ?? $m->product_name;
        $m->tenor_days = array_key_exists('TenorDays', $p) ? $p['TenorDays'] : $m->tenor_days;
        $m->max_seats = array_key_exists('MaxSeats', $p) ? $p['MaxSeats'] : $m->max_seats;
        $m->max_video = array_key_exists('MaxVideo', $p) ? $p['MaxVideo'] : $m->max_video;
        $m->features = $p['Features'] ?? $m->features;
        $m->status = $p['Status'] ?? $m->status;
        $m->is_activated = array_key_exists('IsActivated', $p) ? (bool)$p['IsActivated'] : $m->is_activated;
        $m->activation_date_utc = $p['ActivationDateUtc'] ?? $m->activation_date_utc;
        $m->expires_at_utc = $p['ExpiresAtUtc'] ?? $m->expires_at_utc;
        $m->machine_id = $p['MachineId'] ?? $m->machine_id;
        $m->device_id = $p['DeviceId'] ?? $m->device_id;
        $m->max_seats_shopee_scrap = array_key_exists('MaxSeatsShopeeScrap', $p) ? $p['MaxSeatsShopeeScrap'] : $m->max_seats_shopee_scrap;
        $m->used_seats_shopee_scrap = array_key_exists('UsedSeatsShopeeScrap', $p) ? $p['UsedSeatsShopeeScrap'] : $m->used_seats_shopee_scrap;
        $m->save();
        return response()->json(['ok' => true]);
    });
    Route::delete('/customerlicense/{orderId}', function ($orderId) {
        $hard = filter_var(request()->query('hard', false), FILTER_VALIDATE_BOOLEAN);
        $m = CustomerLicense::where('order_id', $orderId)->first();
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        if ($hard) { $m->forceDelete(); } else { $m->delete(); }
        return response()->json(['ok' => true]);
    });
    Route::post('/customerlicense', function () {
        $p = request()->json()->all();
        $m = new CustomerLicense();
        $m->order_id = 'MANUAL-' . Str::upper(Str::random(8));
        $m->license_key = $p['LicenseKey'] ?? null;
        $m->owner = $p['Owner'] ?? null;
        $m->email = $p['Email'] ?? null;
        $m->phone = $p['Phone'] ?? null;
        $m->edition = $p['Edition'] ?? null;
        $m->payment_status = $p['PaymentStatus'] ?? null;
        $m->product_name = $p['ProductName'] ?? null;
        $m->tenor_days = $p['TenorDays'] ?? null;
        $m->max_seats = $p['MaxSeats'] ?? null;
        $m->max_video = $p['MaxVideo'] ?? null;
        $m->features = $p['Features'] ?? null;
        $m->is_activated = array_key_exists('IsActivated', $p) ? (bool)$p['IsActivated'] : false;
        $m->activation_date_utc = $p['ActivationDateUtc'] ?? null;
        $m->expires_at_utc = $p['ExpiresAtUtc'] ?? null;
        $m->machine_id = $p['MachineId'] ?? null;
        $m->device_id = $p['DeviceId'] ?? null;
        $m->max_seats_shopee_scrap = $p['MaxSeatsShopeeScrap'] ?? null;
        $m->used_seats_shopee_scrap = $p['UsedSeatsShopeeScrap'] ?? null;
        $m->vo_seconds_remaining = 0;
        $m->status = $p['Status'] ?? 'InActive';
        $m->save();
        return response()->json(['ok' => true, 'OrderId' => $m->order_id]);
    });
    Route::get('/customerlicense/{orderId}/vo', function ($orderId) {
        $m = CustomerLicense::where('order_id', $orderId)->first();
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json(['seconds' => (int)($m->vo_seconds_remaining ?? 0)]);
    });
    Route::post('/customerlicense/{orderId}/vo/topup', function ($orderId) {
        $m = CustomerLicense::where('order_id', $orderId)->first();
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        $p = request()->json()->all();
        $add = (int)($p['addSeconds'] ?? 0);
        if ($add <= 0) { return response()->json(['message' => 'addSeconds must be > 0'], 400); }
        $m->vo_seconds_remaining = (int)($m->vo_seconds_remaining ?? 0) + $add;
        $m->save();
        return response()->json(['ok' => true, 'seconds_remaining' => (int)$m->vo_seconds_remaining]);
    });
    Route::post('/customerlicense/{orderId}/vo/debit', function ($orderId) {
        $m = CustomerLicense::where('order_id', $orderId)->first();
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        $p = request()->json()->all();
        $use = (int)($p['secondsUsed'] ?? 0);
        if ($use <= 0) { return response()->json(['message' => 'secondsUsed must be > 0'], 400); }
        $curr = (int)($m->vo_seconds_remaining ?? 0);
        if ($curr < $use) { return response()->json(['message' => 'insufficient_quota', 'seconds_remaining' => $curr], 400); }
        $m->vo_seconds_remaining = $curr - $use;
        $m->save();
        return response()->json(['ok' => true, 'seconds_remaining' => (int)$m->vo_seconds_remaining]);
    });
});

// Remove require auth.php as it does not exist
// require __DIR__.'/auth.php';

Route::get('/debug-tables', function () {
    $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
    return response()->json($tables);
});
