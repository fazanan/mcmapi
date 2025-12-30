<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\CheckActivationController;
use App\Http\Controllers\Api\CheckActivationPluginController;
use App\Http\Controllers\AuthController;
use App\Models\CustomerLicense;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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
        $latest = DB::table('WhatsAppConfig')->orderByDesc('updated_at')->first();
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

    // OrderData API
    Route::get('/orderdata', function () {
        $q = request()->query('q');
        $query = DB::table('OrderData')->orderByDesc('updated_at');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('order_id', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%")
                  ->orWhere('phone', 'like', "%$q%")
                  ->orWhere('name', 'like', "%$q%")
                  ->orWhere('product_name', 'like', "%$q%");
            });
        }
        $rows = $query->get()->map(function ($r) {
            $toIso = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; };
            return [
                'OrderId' => $r->order_id,
                'Email' => $r->email,
                'Phone' => $r->phone,
                'Name' => $r->name,
                'ProductName' => $r->product_name,
                'VariantPrice' => $r->variant_price,
                'NetRevenue' => $r->net_revenue,
                'Status' => $r->status,
                'CreatedAt' => $toIso($r->created_at),
                'UpdatedAt' => $toIso($r->updated_at),
            ];
        });
        return response()->json($rows);
    });
    Route::get('/orderdata/{orderId}', function ($orderId) {
        $r = DB::table('OrderData')->where('order_id', $orderId)->first();
        if (!$r) { return response()->json(['message' => 'Not found'], 404); }
        $toIso = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; };
        return response()->json([
            'OrderId' => $r->order_id,
            'Email' => $r->email,
            'Phone' => $r->phone,
            'Name' => $r->name,
            'ProductName' => $r->product_name,
            'VariantPrice' => $r->variant_price,
            'NetRevenue' => $r->net_revenue,
            'Status' => $r->status,
            'CreatedAt' => $toIso($r->created_at),
            'UpdatedAt' => $toIso($r->updated_at),
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
        $query = DB::table('WhatsAppConfig')->orderByDesc('updated_at');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('api_secret', 'like', "%$q%")
                  ->orWhere('account_unique_id', 'like', "%$q%");
            });
        }
        $rows = $query->get()->map(function ($r) {
            $toIso = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; };
            return [
                'Id' => $r->id,
                'ApiSecret' => $r->api_secret,
                'AccountUniqueId' => $r->account_unique_id,
                'GroupLink' => $r->group_link,
                'InstallerLink' => $r->installer_link,
                'InstallerVersion' => $r->installer_version,
                'UpdatedAt' => $toIso($r->updated_at),
            ];
        });
        return response()->json($rows);
    });
    Route::get('/whatsappconfig/{id}', function ($id) {
        $r = DB::table('WhatsAppConfig')->where('id', $id)->first();
        if (!$r) { return response()->json(['message' => 'Not found'], 404); }
        $toIso = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null; };
        return response()->json([
            'Id' => $r->id,
            'ApiSecret' => $r->api_secret,
            'AccountUniqueId' => $r->account_unique_id,
            'GroupLink' => $r->group_link,
            'InstallerLink' => $r->installer_link,
            'InstallerVersion' => $r->installer_version,
            'UpdatedAt' => $toIso($r->updated_at),
        ]);
    });
    Route::post('/whatsappconfig', function () {
        $p = request()->json()->all();
        $now = \Illuminate\Support\Carbon::now('UTC');
        $id = DB::table('WhatsAppConfig')->insertGetId([
            'api_secret' => $p['ApiSecret'] ?? null,
            'account_unique_id' => $p['AccountUniqueId'] ?? null,
            'group_link' => $p['GroupLink'] ?? null,
            'installer_link' => $p['InstallerLink'] ?? null,
            'installer_version' => $p['InstallerVersion'] ?? null,
            'updated_at' => $now,
        ]);
        return response()->json(['ok' => true, 'Id' => $id]);
    });
    Route::put('/whatsappconfig/{id}', function ($id) {
        $p = request()->json()->all();
        $now = \Illuminate\Support\Carbon::now('UTC');
        $aff = DB::table('WhatsAppConfig')->where('id', $id)->update([
            'api_secret' => $p['ApiSecret'] ?? DB::raw('api_secret'),
            'account_unique_id' => $p['AccountUniqueId'] ?? DB::raw('account_unique_id'),
            'group_link' => $p['GroupLink'] ?? DB::raw('group_link'),
            'installer_link' => $p['InstallerLink'] ?? DB::raw('installer_link'),
            'installer_version' => $p['InstallerVersion'] ?? DB::raw('installer_version'),
            'updated_at' => $now,
        ]);
        if ($aff < 1) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json(['ok' => true]);
    });
    Route::delete('/whatsappconfig/{id}', function ($id) {
        $aff = DB::table('WhatsAppConfig')->where('id', $id)->delete();
        if ($aff < 1) { return response()->json(['message' => 'Not found'], 404); }
        return response()->json(['ok' => true]);
    });

    // ConfigApiKey API
    Route::get('/configapikey', function () {
        $q = request()->query('q');
        $query = DB::table('ConfigApiKey')->orderByDesc('updated_at');
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
