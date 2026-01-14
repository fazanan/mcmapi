<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\CheckActivationController;
use App\Http\Controllers\Api\CheckActivationPluginController;
use App\Http\Controllers\ScalevWebhookController;
use App\Http\Controllers\AuthController;
use App\Models\CustomerLicense;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!function_exists('verifySignatureFlexible')) {
    function verifySignatureFlexible(string $input, string $sig): bool {
        $expectedHex = hash('sha256', $input);
        $expectedBin = hash('sha256', $input, true);
        $expectedB64 = base64_encode($expectedBin);
        $s = trim((string)$sig);
        if (hash_equals($expectedHex, strtolower($s))) { return true; }
        if (hash_equals($expectedB64, $s)) { return true; }
        $hexCandidate = strtolower(str_replace(['-', ' ', "\t"], '', $s));
        if (ctype_xdigit($hexCandidate) && strlen($hexCandidate) === 64) {
            if (hash_equals($expectedHex, $hexCandidate)) { return true; }
        }
        $b64Candidate = strtr($s, '-_', '+/');
        $pad = strlen($b64Candidate) % 4; if ($pad) { $b64Candidate .= str_repeat('=', 4 - $pad); }
        $decoded = base64_decode($b64Candidate, true);
        if ($decoded !== false) {
            if (hash_equals($expectedBin, $decoded)) { return true; }
            if (ctype_xdigit($decoded) && strlen($decoded) === 64) {
                if (hash_equals($expectedHex, strtolower($decoded))) { return true; }
            }
        }
        return false;
    }
}
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
    // Alias rute agar cocok dengan link di sidebar
    Route::get('/license-logs', function () { return view('licenselogs.index'); });
    Route::get('/license-activations', function () { return view('activations.index'); });
    Route::get('/orders', function () { return view('orderdata.index'); });
    Route::get('/config-keys', function () { return view('configapikey.index'); });
    Route::get('/whatsapp-config', function () { return view('whatsappconfig.index'); });
    // Debug & maintenance (sementara, aman karena di balik auth)
    Route::get('/debug-routes', function () {
        $list = [];
        foreach (Route::getRoutes() as $r) { $list[] = $r->uri(); }
        return response()->json($list);
    });
    Route::get('/debug-tables', function () {
        return response()->json(DB::select('SHOW TABLES'));
    });
    Route::post('/artisan/route-clear', function () {
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        return response()->json(['ok' => true, 'message' => 'route/config/view cache cleared']);
    });
    Route::get('/users', function () {
        $users = \App\Models\User::query()->orderByDesc('created_at')->get();
        return view('users.index', ['users' => $users]);
    })->name('users.index');
    Route::get('/produk', function () { return view('produk.index'); })->name('produk.index');
    Route::get('/orderdata', function () { return view('orderdata.index'); })->name('orderdata.index');
    Route::get('/activations', function () { return view('activations.index'); })->name('activations.index');
    Route::get('/configapikey', function () { return view('configapikey.index'); })->name('configapikey.index');
    Route::get('/whatsappconfig', function () { return view('whatsappconfig.index'); })->name('whatsappconfig.index');
    Route::get('/test-whatsapp', function () {
        $q = DB::table('WhatsAppConfig');
        if (Schema::hasColumn('WhatsAppConfig', 'updated_at')) {
            $q->orderByDesc('updated_at');
        }
        $latest = $q->first();
        $statusCfg = ['hasApiKey' => !!($latest && ($latest->api_secret ?? null))];
        return view('testwhatsapp.index', [
            'statusCfg' => $statusCfg,
            'recipient' => null,
            'message' => null,
            'overrideSecret' => null,
            'overrideAccount' => null,
            'result' => null,
            'curlPreview' => null,
            'logTail' => [],
        ]);
    })->name('testwhatsapp.index');
});

// API Routes (Dipindah ke sini karena Laravel 11 defaultnya tidak ada routes/api.php jika installasi minim)
// Atau jika ingin tetap dipisah, pastikan install api:install
Route::prefix('api')->group(function () {
    Route::post('/webhooks/scalev', [ScalevWebhookController::class, 'handle']);
    Route::post('/check_activation', [CheckActivationController::class, 'checkActivation']);
    Route::post('/check_activation_plugin', [CheckActivationPluginController::class, 'checkActivation'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::post('/check_activation_plugin/logout', [CheckActivationPluginController::class, 'logout'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::post('/check_massvoseat_login', [CheckActivationPluginController::class, 'loginMassVoSeat'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
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
                'MaxSeatUploadTiktok' => $m->max_seat_upload_tiktok,
                'UsedSeatUploadTiktok' => $m->used_seat_upload_tiktok,
                'MassVoSeat' => $m->massvoseat,
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
            'MaxSeatUploadTiktok' => $m->max_seat_upload_tiktok,
            'UsedSeatUploadTiktok' => $m->used_seat_upload_tiktok,
            'MassVoSeat' => $m->massvoseat,
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
        $m->max_seat_upload_tiktok = array_key_exists('MaxSeatUploadTiktok', $p) ? $p['MaxSeatUploadTiktok'] : $m->max_seat_upload_tiktok;
        $m->used_seat_upload_tiktok = array_key_exists('UsedSeatUploadTiktok', $p) ? $p['UsedSeatUploadTiktok'] : $m->used_seat_upload_tiktok;
        $m->massvoseat = array_key_exists('MassVoSeat', $p) ? $p['MassVoSeat'] : $m->massvoseat;
        $m->save();
        return response()->json(['ok' => true]);
    })->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::delete('/customerlicense/{orderId}', function ($orderId) {
        $hard = filter_var(request()->query('hard', false), FILTER_VALIDATE_BOOLEAN);
        $m = CustomerLicense::where('order_id', $orderId)->first();
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        if ($hard) { $m->forceDelete(); } else { $m->delete(); }
        return response()->json(['ok' => true]);
    })->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
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
        $m->max_seat_upload_tiktok = $p['MaxSeatUploadTiktok'] ?? null;
        $m->used_seat_upload_tiktok = $p['UsedSeatUploadTiktok'] ?? null;
        $m->massvoseat = $p['MassVoSeat'] ?? null;
        $m->vo_seconds_remaining = 0;
        $m->status = $p['Status'] ?? 'InActive';
        $m->save();
        return response()->json(['ok' => true, 'OrderId' => $m->order_id]);
    })->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
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
    })->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
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
    })->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    
    // License Activations API
    Route::get('/license-activations', function () {
        $q = request()->query('q');
        $query = \App\Models\LicenseActivationsPlugin::query()->orderByDesc('activated_at');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('product_name', 'like', "%$q%")
                  ->orWhere('device_id', 'like', "%$q%")
                  ->orWhere('license_key', 'like', "%$q%");
            });
        }
        $rows = $query->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'license_key' => $m->license_key,
                'device_id' => $m->device_id,
                'product_name' => $m->product_name,
                'activated_at' => optional($m->activated_at)->toIso8601String(),
                'last_seen_at' => optional($m->last_seen_at)->toIso8601String(),
                'revoked' => (bool)$m->revoked,
            ];
        });
        return response()->json($rows);
    });
    Route::get('/license-activations/{id}', function ($id) {
        $m = \App\Models\LicenseActivationsPlugin::find($id);
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json([
            'id' => $m->id,
            'license_key' => $m->license_key,
            'device_id' => $m->device_id,
            'product_name' => $m->product_name,
            'activated_at' => optional($m->activated_at)->toIso8601String(),
            'last_seen_at' => optional($m->last_seen_at)->toIso8601String(),
            'revoked' => (bool)$m->revoked,
        ]);
    });
    Route::post('/license-activations', function () {
        $p = request()->json()->all();
        $m = new \App\Models\LicenseActivationsPlugin();
        $m->license_key = $p['license_key'] ?? null;
        $m->device_id = $p['device_id'] ?? null;
        $m->product_name = $p['product_name'] ?? null;
        $m->revoked = array_key_exists('revoked', $p) ? (bool)$p['revoked'] : false;
        $m->activated_at = $p['activated_at'] ?? null;
        $m->last_seen_at = $p['last_seen_at'] ?? null;
        $m->save();
        return response()->json(['ok' => true, 'id' => $m->id]);
    });
    Route::put('/license-activations/{id}', function ($id) {
        $m = \App\Models\LicenseActivationsPlugin::find($id);
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        $p = request()->json()->all();
        $m->license_key = $p['license_key'] ?? $m->license_key;
        $m->device_id = $p['device_id'] ?? $m->device_id;
        $m->product_name = $p['product_name'] ?? $m->product_name;
        $m->revoked = array_key_exists('revoked', $p) ? (bool)$p['revoked'] : $m->revoked;
        $m->activated_at = $p['activated_at'] ?? $m->activated_at;
        $m->last_seen_at = $p['last_seen_at'] ?? $m->last_seen_at;
        $m->save();
        return response()->json(['ok' => true]);
    });
    Route::delete('/license-activations/{id}', function ($id) {
        $m = \App\Models\LicenseActivationsPlugin::find($id);
        if (!$m) { return response()->json(['message' => 'Not found'], 404); }
        $m->delete();
        return response()->json(['ok' => true]);
    });

    // License Logs API (read-only)
    Route::get('/license-logs', function () {
        $q = request()->query('q');
        $query = DB::table('license_actions')->orderByDesc('created_at');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('license_key', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%")
                  ->orWhere('message', 'like', "%$q%")
                  ->orWhere('action', 'like', "%$q%")
                  ->orWhere('order_id', 'like', "%$q%")
                  ->orWhere('result', 'like', "%$q%");
            });
        }
        $rows = $query->get()->map(function ($r) {
            $toIso = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; };
            return [
                'created_at' => $toIso($r->created_at ?? null),
                'action' => $r->action ?? null,
                'result' => $r->result ?? null,
                'email' => $r->email ?? null,
                'license_key' => $r->license_key ?? null,
                'order_id' => $r->order_id ?? null,
                'message' => $r->message ?? null,
            ];
        });
        return response()->json($rows);
    });

    // OrderData API
    Route::get('/orderdata', function () {
        $q = request()->query('q');
        $toIso = function ($v) { 
            try { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; } 
            catch (\Throwable $e) { return null; } 
        };
        $mapRow = function ($r) use ($toIso) {
            return [
                'OrderId' => $r->order_id ?? $r->OrderId ?? null,
                'Email' => $r->email ?? $r->Email ?? null,
                'Phone' => $r->phone ?? $r->Phone ?? null,
                'Name' => $r->name ?? $r->Name ?? null,
                'ProductName' => $r->product_name ?? $r->ProductName ?? null,
                'VariantPrice' => $r->variant_price ?? $r->VariantPrice ?? null,
                'NetRevenue' => $r->net_revenue ?? $r->NetRevenue ?? null,
                'Status' => $r->status ?? $r->Status ?? null,
                'CreatedAt' => $toIso($r->created_at ?? $r->CreatedAt ?? null),
                'UpdatedAt' => $toIso($r->updated_at ?? $r->UpdatedAt ?? null),
            ];
        };
        $rows = collect();
        try {
            $query = DB::table('OrderData');
            if ($q) {
                $cols = [];
                try { $cols = Schema::getColumnListing('OrderData'); } catch (\Throwable $e) {}
                $fields = ['order_id','email','phone','name','product_name'];
                $query->where(function ($w) use ($q, $cols, $fields) {
                    foreach ($fields as $f) { if (in_array($f, $cols)) { $w->orWhere($f, 'like', "%$q%"); } }
                });
            }
            $rows = $rows->concat($query->get()->map($mapRow));
        } catch (\Throwable $e) {}
        try {
            $query2 = DB::table('orders');
            if ($q) {
                $cols2 = [];
                try { $cols2 = Schema::getColumnListing('orders'); } catch (\Throwable $e) {}
                $fields2 = ['order_id','email','phone','name','product_name'];
                $query2->where(function ($w) use ($q, $cols2, $fields2) {
                    foreach ($fields2 as $f) { if (in_array($f, $cols2)) { $w->orWhere($f, 'like', "%$q%"); } }
                });
            }
            $rows = $rows->concat($query2->get()->map($mapRow));
        } catch (\Throwable $e) {}
        return response()->json($rows->values());
    });
    Route::get('/orderdata/{orderId}', function ($orderId) {
        $toIso = function ($v) { 
            try { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; } 
            catch (\Throwable $e) { return null; } 
        };
        $r = null;
        try { $r = DB::table('OrderData')->where('order_id', $orderId)->first(); } catch (\Throwable $e) {}
        if (!$r) { try { $r = DB::table('orders')->where('order_id', $orderId)->first(); } catch (\Throwable $e) {} }
        if (!$r) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json([
            'OrderId' => $r->order_id ?? null,
            'Email' => $r->email ?? null,
            'Phone' => $r->phone ?? null,
            'Name' => $r->name ?? null,
            'ProductName' => $r->product_name ?? null,
            'VariantPrice' => $r->variant_price ?? null,
            'NetRevenue' => $r->net_revenue ?? null,
            'Status' => $r->status ?? null,
            'CreatedAt' => $toIso($r->created_at ?? null),
            'UpdatedAt' => $toIso($r->updated_at ?? null),
        ]);
    });
    Route::post('/orderdata', function () {
        $p = request()->json()->all();
        $now = \Illuminate\Support\Carbon::now('UTC');
        DB::table('OrderData')->insert([
            'order_id' => $p['OrderId'] ?? ('ORD-' . Str::upper(Str::random(8))),
            'email' => $p['Email'] ?? null,
            'phone' => $p['Phone'] ?? null,
            'name' => $p['Name'] ?? null,
            'product_name' => $p['ProductName'] ?? null,
            'variant_price' => $p['VariantPrice'] ?? null,
            'net_revenue' => $p['NetRevenue'] ?? null,
            'status' => $p['Status'] ?? 'Not Paid',
            'created_at' => $p['CreatedAtUtc'] ?? $now,
            'updated_at' => $now,
        ]);
        return response()->json(['ok' => true]);
    });
    Route::put('/orderdata/{orderId}', function ($orderId) {
        $p = request()->json()->all();
        $now = \Illuminate\Support\Carbon::now('UTC');
        $aff = DB::table('OrderData')->where('order_id', $orderId)->update([
            'email' => $p['Email'] ?? DB::raw('email'),
            'phone' => $p['Phone'] ?? DB::raw('phone'),
            'name' => $p['Name'] ?? DB::raw('name'),
            'product_name' => $p['ProductName'] ?? DB::raw('product_name'),
            'variant_price' => $p['VariantPrice'] ?? DB::raw('variant_price'),
            'net_revenue' => $p['NetRevenue'] ?? DB::raw('net_revenue'),
            'status' => $p['Status'] ?? DB::raw('status'),
            'created_at' => $p['CreatedAtUtc'] ?? DB::raw('created_at'),
            'updated_at' => $now,
        ]);
        if ($aff < 1) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json(['ok' => true]);
    });
    Route::delete('/orderdata/{orderId}', function ($orderId) {
        $aff = DB::table('OrderData')->where('order_id', $orderId)->delete();
        if ($aff < 1) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json(['ok' => true]);
    });

    // WhatsAppConfig API
    Route::get('/whatsappconfig', function () {
        $q = request()->query('q');
        $query = DB::table('WhatsAppConfig');
        if (Schema::hasColumn('WhatsAppConfig', 'UpdatedAt')) {
            $query->orderByDesc('UpdatedAt');
        }
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('ApiSecret', 'like', "%$q%")
                  ->orWhere('AccountUniqueId', 'like', "%$q%");
            });
        }
        $rows = $query->get()->map(function ($r) {
            $toIso = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; };
            return [
                'Id' => $r->Id ?? $r->id ?? null,
                'ApiSecret' => $r->ApiSecret ?? $r->api_secret ?? null,
                'AccountUniqueId' => $r->AccountUniqueId ?? $r->account_unique_id ?? null,
                'GroupLink' => $r->GroupLink ?? $r->group_link ?? null,
                'InstallerLink' => $r->InstallerLink ?? $r->installer_link ?? null,
                'InstallerVersion' => $r->InstallerVersion ?? $r->installer_version ?? null,
                'UpdatedAt' => $toIso($r->UpdatedAt ?? $r->updated_at ?? null),
            ];
        });
        return response()->json($rows);
    });
    Route::get('/whatsappconfig/{id}', function ($id) {
        $r = DB::table('WhatsAppConfig')->where('Id', $id)->first();
        if (!$r) { return response()->json(['message' => 'Not found'], 404); }
        $toIso = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; };
        return response()->json([
            'Id' => $r->Id ?? $r->id ?? null,
            'ApiSecret' => $r->ApiSecret ?? $r->api_secret ?? null,
            'AccountUniqueId' => $r->AccountUniqueId ?? $r->account_unique_id ?? null,
            'GroupLink' => $r->GroupLink ?? $r->group_link ?? null,
            'InstallerLink' => $r->InstallerLink ?? $r->installer_link ?? null,
            'InstallerVersion' => $r->InstallerVersion ?? $r->installer_version ?? null,
            'UpdatedAt' => $toIso($r->UpdatedAt ?? $r->updated_at ?? null),
        ]);
    });
    Route::post('/whatsappconfig', function () {
        $p = request()->json()->all();
        $now = \Illuminate\Support\Carbon::now('UTC');
        $id = DB::table('WhatsAppConfig')->insertGetId([
            'ApiSecret' => $p['ApiSecret'] ?? null,
            'AccountUniqueId' => $p['AccountUniqueId'] ?? null,
            'GroupLink' => $p['GroupLink'] ?? null,
            'InstallerLink' => $p['InstallerLink'] ?? null,
            'InstallerVersion' => $p['InstallerVersion'] ?? null,
            'UpdatedAt' => $now,
            'CreatedAt' => $now,
        ]);
        return response()->json(['ok' => true, 'Id' => $id]);
    });
    Route::put('/whatsappconfig/{id}', function ($id) {
        $p = request()->json()->all();
        $now = \Illuminate\Support\Carbon::now('UTC');
        $aff = DB::table('WhatsAppConfig')->where('Id', $id)->update([
            'ApiSecret' => $p['ApiSecret'] ?? DB::raw('ApiSecret'),
            'AccountUniqueId' => $p['AccountUniqueId'] ?? DB::raw('AccountUniqueId'),
            'GroupLink' => $p['GroupLink'] ?? DB::raw('GroupLink'),
            'InstallerLink' => $p['InstallerLink'] ?? DB::raw('InstallerLink'),
            'InstallerVersion' => $p['InstallerVersion'] ?? DB::raw('InstallerVersion'),
            'UpdatedAt' => $now,
        ]);
        if ($aff < 1) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json(['ok' => true]);
    });
    Route::delete('/whatsappconfig/{id}', function ($id) {
        $aff = DB::table('WhatsAppConfig')->where('Id', $id)->delete();
        if ($aff < 1) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json(['ok' => true]);
    });

    // ConfigApiKey API
    Route::get('/configapikey', function () {
        $q = request()->query('q');
        $query = DB::table('ConfigApiKey');
        if (Schema::hasColumn('ConfigApiKey', 'updated_at')) {
            $query->orderByDesc('updated_at');
        }
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('provider', 'like', "%$q%")
                  ->orWhere('api_key', 'like', "%$q%")
                  ->orWhere('model', 'like', "%$q%")
                  ->orWhere('status', 'like', "%$q%");
            });
        }
        $rows = $query->get()->map(function ($r) {
            $toIso = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; };
            return [
                'ApiKeyId' => $r->api_key_id ?? $r->id ?? null,
                'JenisApiKey' => $r->provider,
                'ApiKey' => $r->api_key,
                'Model' => $r->model,
                'DefaultVoiceId' => $r->default_voice_id,
                'Status' => $r->status,
                'CooldownUntilPT' => $toIso($r->cooldown_until),
                'UpdatedAt' => $toIso($r->updated_at),
            ];
        });
        return response()->json($rows);
    });
    Route::get('/configapikey/{id}', function ($id) {
        $r = DB::table('ConfigApiKey')->where(function($q) use ($id){ $q->where('api_key_id', $id)->orWhere('id', $id); })->first();
        if (!$r) { return response()->json(['message' => 'Not found'], 404); }
        $toIso = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; };
        return response()->json([
            'ApiKeyId' => $r->api_key_id ?? $r->id ?? null,
            'JenisApiKey' => $r->provider,
            'ApiKey' => $r->api_key,
            'Model' => $r->model,
            'DefaultVoiceId' => $r->default_voice_id,
            'Status' => $r->status,
            'CooldownUntilPT' => $toIso($r->cooldown_until),
            'UpdatedAt' => $toIso($r->updated_at),
        ]);
    });
    Route::post('/configapikey', function () {
        $p = request()->json()->all();
        $now = \Illuminate\Support\Carbon::now('UTC');
        $id = DB::table('ConfigApiKey')->insertGetId([
            'provider' => $p['JenisApiKey'] ?? null,
            'api_key' => $p['ApiKey'] ?? null,
            'model' => $p['Model'] ?? null,
            'default_voice_id' => $p['DefaultVoiceId'] ?? null,
            'status' => $p['Status'] ?? 'AVAILABLE',
            'cooldown_until' => $p['CooldownUntilPT'] ?? null,
            'updated_at' => $now,
        ]);
        return response()->json(['ok' => true, 'ApiKeyId' => $id]);
    });
    Route::put('/configapikey/{id}', function ($id) {
        $p = request()->json()->all();
        $now = \Illuminate\Support\Carbon::now('UTC');
        $aff = DB::table('ConfigApiKey')->where(function($q) use ($id){ $q->where('api_key_id', $id)->orWhere('id', $id); })->update([
            'provider' => $p['JenisApiKey'] ?? DB::raw('provider'),
            'api_key' => $p['ApiKey'] ?? DB::raw('api_key'),
            'model' => $p['Model'] ?? DB::raw('model'),
            'default_voice_id' => $p['DefaultVoiceId'] ?? DB::raw('default_voice_id'),
            'status' => $p['Status'] ?? DB::raw('status'),
            'cooldown_until' => $p['CooldownUntilPT'] ?? DB::raw('cooldown_until'),
            'updated_at' => $now,
        ]);
        if ($aff < 1) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json(['ok' => true]);
    });
    Route::delete('/configapikey/{id}', function ($id) {
        $aff = DB::table('ConfigApiKey')->where(function($q) use ($id){ $q->where('api_key_id', $id)->orWhere('id', $id); })->delete();
        if ($aff < 1) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json(['ok' => true]);
    });
});

// Remove require auth.php as it does not exist
// require __DIR__.'/auth.php';
 
Route::get('/debug-tables', function () {
    $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
    return response()->json($tables);
});

Route::post('/api/license/activate', function (Request $request) {
    $key = trim((string)($request->input('LicenseKey') ?? $request->input('licenseKey') ?? $request->input('license') ?? $request->input('key')));
    $email = trim((string)($request->input('Email') ?? $request->input('email')));
    $mid = trim((string)($request->input('MachineId') ?? $request->input('machineId') ?? $request->input('MachineID') ?? $request->input('mid')));
    if (!$key || !$email) {
        return response()->json(['Success'=>false,'Message'=>'LicenseKey dan Email wajib diisi.','ErrorCode'=>'INVALID_REQUEST'],400);
    }
    $lic = CustomerLicense::query()->where('license_key',$key)->first();
    if (!$lic) {
        DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>null,'email'=>$email,'action'=>'Activate','result'=>'Failed','message'=>'License not found.','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['Success'=>false,'Message'=>'License not found.','ErrorCode'=>'LICENSE_NOT_FOUND'],400);
    }
    if ($lic->expires_at_utc && now('UTC')->gt($lic->expires_at_utc)) {
        DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Activate','result'=>'Failed','message'=>'License expired.','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['Success'=>false,'Message'=>'License expired.','ErrorCode'=>'LICENSE_EXPIRED'],400);
    }
    if (strcasecmp((string)$lic->email, $email) !== 0) {
        DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Activate','result'=>'Failed','message'=>'Email tidak sesuai.','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['Success'=>false,'Message'=>'Email tidak sesuai.','ErrorCode'=>'EMAIL_MISMATCH'],400);
    }
    if ($lic->is_activated) {
        DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Activate','result'=>'Failed','message'=>'License sudah aktif dan masih berlaku.','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['Success'=>false,'Message'=>'License sudah aktif dan masih berlaku.','ErrorCode'=>'LICENSE_ALREADY_ACTIVE'],400);
    }
    $expires = null;
    if ($lic->status) {
        if ($lic->status === 'InActive') {
            $days = $lic->tenor_days ?? 1;
            if (strcasecmp((string)$lic->payment_status,'paid')===0) { $expires = now('UTC')->addDays((int)$days); } else { $expires = $lic->expires_at_utc; }
        } else if ($lic->status === 'Reset' || $lic->status === 'Active') {
            $expires = $lic->expires_at_utc;
        }
    }
    $lic->status = 'Active';
    if (strlen($mid) > 0) { $lic->machine_id = $mid; }
    $lic->activation_date_utc = now('UTC');
    $lic->is_activated = true;
    $lic->expires_at_utc = $expires;
    $lic->save();
    DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Activate','result'=>'Success','message'=>'License activated (no signature check).','created_at'=>now(),'updated_at'=>now()]);
    $exp = $lic->expires_at_utc ? $lic->expires_at_utc->format('Y-m-d') : null;
    $statusVal = (string)($lic->status ?? '');
    $editionVal = (string)($lic->edition ?? '');
    return response()->json([
        'Success' => true,
        'Message' => 'License activated.',
        'Data' => [
            'expirationdate' => $exp,
            'expirationDate' => $exp,
            'status' => $statusVal,
            'Status' => $statusVal,
            'LicenseStatus' => $statusVal,
            'edition' => $editionVal,
            'edtion' => $editionVal,
            'Edition' => $editionVal,
            'machineId' => $lic->machine_id,
            'MachineId' => $lic->machine_id,
            'LicenseKey' => $lic->license_key,
            'Email' => $lic->email,
            'email' => $lic->email,
            'ExpiresAt' => optional($lic->expires_at_utc)->toIso8601String(),
        ],
    ],200);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::match(['GET','POST'],'/api/license/reset', function (Request $request) {
    $key = trim((string)($request->input('LicenseKey') ?? $request->input('licenseKey') ?? $request->input('license') ?? $request->input('key')));
    $email = trim((string)($request->input('Email') ?? $request->input('email')));
    $sig = trim((string)($request->input('Signature') ?? $request->input('signature') ?? $request->input('sig')));
    $newMid = trim((string)($request->input('MachineId') ?? $request->input('machineId') ?? $request->input('mid')));
    $rawContent = $request->getContent();
    $rawJson = json_decode($rawContent, true);
    $rawKey = null; $rawEmail = null; $rawMid = null;
    if (is_array($rawJson)) {
        $rawKey = $rawJson['LicenseKey'] ?? $rawJson['licenseKey'] ?? $rawJson['license'] ?? $rawJson['key'] ?? null;
        $rawEmail = $rawJson['Email'] ?? $rawJson['email'] ?? null;
        $rawMid = $rawJson['MachineId'] ?? $rawJson['machineId'] ?? $rawJson['mid'] ?? null;
    }
    if (!$key || !$email || !$sig) {
        return response()->json(['Success'=>false,'Message'=>'LicenseKey, Email, dan Signature wajib diisi.','ErrorCode'=>'INVALID_REQUEST'],400);
    }
    $lic = CustomerLicense::query()->where('license_key',$key)->first();
    if (!$lic) {
        DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>null,'email'=>$email,'action'=>'Reset','result'=>'Failed','message'=>'License not found.','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['Success'=>false,'Message'=>'License not found.','ErrorCode'=>'LICENSE_NOT_FOUND'],400);
    }
    if (strcasecmp((string)$lic->email, $email) !== 0) {
        DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Reset','result'=>'Failed','message'=>'Email tidak sesuai.','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['Success'=>false,'Message'=>'Email tidak sesuai.','ErrorCode'=>'EMAIL_MISMATCH'],400);
    }
    $inputs = [];
    $keyVars = [$key, strtoupper($key), strtolower($key)];
    if ($rawKey && $rawKey !== $key) { $keyVars[] = $rawKey; }
    $keyVars = array_unique($keyVars);
    $emailVars = [$email, strtolower($email), strtoupper($email)];
    if ($rawEmail && $rawEmail !== $email) { $emailVars[] = $rawEmail; }
    $emailVars = array_unique($emailVars);
    $midVars = [''];
    if ($lic->machine_id) { $midVars[] = $lic->machine_id; }
    if ($newMid && $newMid !== $lic->machine_id) { $midVars[] = $newMid; }
    if ($rawMid !== null && $rawMid !== $newMid) { $midVars[] = $rawMid; }
    $midVars = array_unique($midVars);
    $ok = false; 
    $successDetail = "";
    foreach ($keyVars as $_k) {
        foreach ($emailVars as $_e) {
            $inputs[] = "$_k|$_e";
            foreach ($midVars as $_m) {
                $inputs[] = "$_k|$_m|$_e";
                $inputs[] = "$_k|$_e|$_m";
                if ($_m === '') { $inputs[] = "$_k||$_e"; }
            }
        }
    }
    foreach ($inputs as $canon) { 
        if (verifySignatureFlexible($canon, $sig)) { 
            $ok = true; 
            $successDetail = "(Matched: $canon)";
            break; 
        } 
    }
    if (!$ok) {
        $lic->max_seats = 1;
        $debugMsg = "Invalid Sig. Fallback to Key+Email check. Enforcing MaxSeats=1. Recv: $sig.";
        DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Reset','result'=>'Warning','message'=>$debugMsg,'created_at'=>now(),'updated_at'=>now()]);
    }
    $lic->status = 'Reset';
    $lic->is_activated = false;
    $lic->activation_date_utc = null;
    $prevMid = $lic->machine_id;
    $lic->machine_id = null;
    $lic->save();
    DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Reset','result'=>'Success','message'=>'License reset. '.$successDetail,'created_at'=>now(),'updated_at'=>now()]);
    $exp = $lic->expires_at_utc ? $lic->expires_at_utc->format('Y-m-d') : null;
    $statusVal = (string)($lic->status ?? '');
    $editionVal = (string)($lic->edition ?? '');
    return response()->json([
        'Success' => true,
        'Message' => 'License reset.',
        'Data' => [
            'expirationdate' => $exp,
            'expirationDate' => $exp,
            'status' => $statusVal,
            'Status' => $statusVal,
            'LicenseStatus' => $statusVal,
            'edition' => $editionVal,
            'edtion' => $editionVal,
            'Edition' => $editionVal,
            'machineId' => $prevMid,
            'MachineId' => $prevMid,
            'LicenseKey' => $lic->license_key,
            'Email' => $lic->email,
            'email' => $lic->email,
            'ExpiresAt' => optional($lic->expires_at_utc)->toIso8601String(),
        ],
    ],200);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/license/check', function (Request $request) {
    $key = trim((string)($request->input('LicenseKey') ?? $request->input('licenseKey') ?? $request->input('license') ?? $request->input('key')));
    if (!$key) {
        return response()->json(['Success'=>false,'Message'=>'LicenseKey wajib diisi.','ErrorCode'=>'INVALID_REQUEST'],400);
    }
    $lic = CustomerLicense::query()->where('license_key',$key)->first();
    if (!$lic) {
        return response()->json(['Success'=>false,'Message'=>'License not found.','ErrorCode'=>'LICENSE_NOT_FOUND'],404);
    }
    $exp = $lic->expires_at_utc ? \Illuminate\Support\Carbon::parse($lic->expires_at_utc)->format('Y-m-d') : null;
    $statusVal = (string)($lic->status ?? '');
    return response()->json([
        'Success' => true,
        'Message' => 'License found.',
        'Data' => [
            'LicenseKey' => $lic->license_key,
            'Email' => $lic->email,
            'Status' => $statusVal,
            'ExpiresAt' => optional($lic->expires_at_utc)->toIso8601String(),
            'expirationdate' => $exp,
            'expirationDate' => $exp,
            'IsActivated' => (bool)$lic->is_activated,
            'ActivationDate' => optional($lic->activation_date_utc)->toIso8601String(),
            'MachineId' => $lic->machine_id,
            'Edition' => $lic->edition,
            'ProductName' => $lic->product_name,
            'Version' => $lic->version,
            'LastUsed' => optional($lic->last_used)->toIso8601String(),
        ]
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
