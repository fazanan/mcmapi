<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\CustomerLicense;
use App\Models\VoiceOverTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Controllers\VoiceOverController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ScalevWebhookController;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;

// Helper verifikasi signature yang toleran format
if (!function_exists('verifySignatureFlexible')) {
    function verifySignatureFlexible(string $input, string $sig): bool {
        $expectedHex = hash('sha256', $input);
        $expectedBin = hash('sha256', $input, true);
        $expectedB64 = base64_encode($expectedBin);
        $s = trim((string)$sig);
        // Cocok hex (case-insensitive)
        if (hash_equals($expectedHex, strtolower($s))) { return true; }
        // Cocok base64 digest biner
        if (hash_equals($expectedB64, $s)) { return true; }
        // Hex dengan strip/spasi
        $hexCandidate = strtolower(str_replace(['-', ' ', "\t"], '', $s));
        if (ctype_xdigit($hexCandidate) && strlen($hexCandidate) === 64) {
            if (hash_equals($expectedHex, $hexCandidate)) { return true; }
        }
        // Base64 url-safe atau base64 dari teks hex (terima tanpa padding)
        $b64Candidate = strtr($s, '-_', '+/');
        $pad = strlen($b64Candidate) % 4; if ($pad) { $b64Candidate .= str_repeat('=', 4 - $pad); }
        $decoded = base64_decode($b64Candidate, true);
        if ($decoded !== false) {
            // Jika hasil decode adalah digest biner
            if (hash_equals($expectedBin, $decoded)) { return true; }
            // Jika hasil decode adalah teks hex (base64 dari hex string)
            if (ctype_xdigit($decoded) && strlen($decoded) === 64) {
                if (hash_equals($expectedHex, strtolower($decoded))) { return true; }
            }
        }
        return false;
    }
}
Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/produk');
    }
    return redirect('/login');
});

// Auth routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::get('/licenses', function () {
    return view('licenses.index');
})->middleware(['auth','role:admin']);

Route::get('/config-keys', function () {
    return view('configapikey.index');
})->middleware(['auth','role:admin']);

Route::get('/orders', function () {
    return view('orderdata.index');
})->middleware(['auth','role:admin']);

Route::get('/whatsapp-config', function () {
    return view('whatsappconfig.index');
})->middleware(['auth','role:admin']);

Route::get('/license-logs', function () {
    return view('licenselogs.index');
})->middleware(['auth','role:admin']);

// Member-accessible Produk page
Route::get('/produk', function () {
    return view('produk.index');
})->middleware(['auth']);

// Admin-only Users page
Route::get('/users', function () {
    $users = \App\Models\User::query()->orderBy('created_at','desc')->get();
    return view('users.index', ['users' => $users]);
})->middleware(['auth','role:admin']);

// ScaleV webhook: create member access when status transitions to paid
Route::post('/webhooks/scalev', [ScalevWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/license-logs', function (Request $request) {
    $q = $request->query('q');
    $rows = DB::table('license_actions')
        ->when($q, function ($qr) use ($q) {
            $like = '%'.$q.'%';
            $qr->where('license_key','like',$like)
               ->orWhere('email','like',$like)
               ->orWhere('action','like',$like)
               ->orWhere('result','like',$like)
               ->orWhere('message','like',$like);
        })
        ->orderByDesc('created_at')
        ->limit(500) // Batasi 500 log terakhir
        ->get();
        
    $items = $rows->map(function($x){
        return [
            'id' => $x->id,
            'license_key' => $x->license_key,
            'order_id' => $x->order_id,
            'email' => $x->email,
            'action' => $x->action,
            'result' => $x->result,
            'message' => $x->message,
            'created_at' => $x->created_at ? \Illuminate\Support\Carbon::parse($x->created_at)->setTimezone('UTC')->toISOString() : null,
        ];
    });
    return response()->json($items);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/customerlicense', function (Request $request) {
    $q = $request->query('q');
    $rows = CustomerLicense::query()
        ->when($q, function ($qr) use ($q) {
            $like = '%'.$q.'%';
            $qr->where(function($w) use ($like){
                $w->where('order_id','like',$like)
                  ->orWhere('owner','like',$like)
                  ->orWhere('email','like',$like)
                  ->orWhere('product_name','like',$like);
            });
        })
        ->orderByDesc('created_at')
        ->get();
    $items = $rows->map(function($m){
        return [
            'OrderId' => $m->order_id,
            'Status' => $m->status,
            'LicenseKey' => $m->license_key,
            'Owner' => $m->owner,
            'VoSecondsRemaining' => $m->vo_seconds_remaining,
            'Email' => $m->email,
            'Phone' => $m->phone,
            'Edition' => $m->edition,
            'PaymentStatus' => $m->payment_status,
            'DeliveryStatus' => $m->delivery_status,
            'DeliveryLog' => $m->delivery_log,
            'ProductName' => $m->product_name,
            'TenorDays' => $m->tenor_days,
            'IsActivated' => (bool)$m->is_activated,
            'ActivationDate' => optional($m->activation_date_utc)->toISOString(),
            'ExpiresAt' => optional($m->expires_at_utc)->toISOString(),
            'MaxhineId' => $m->machine_id,
            'Version' => $m->version,
            'LastUsed' => optional($m->last_used)->toISOString(),
        ];
    });
    return response()->json($items);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/customerlicense/{id}', function ($id) {
    $m = CustomerLicense::query()->where('order_id',$id)->first();
    if (!$m) return response()->json(['message'=>'Not found'],404);
    return response()->json([
        'OrderId' => $m->order_id,
        'Status' => $m->status,
        'LicenseKey' => $m->license_key,
        'Owner' => $m->owner,
        'VoSecondsRemaining' => $m->vo_seconds_remaining,
        'Email' => $m->email,
        'Phone' => $m->phone,
        'Edition' => $m->edition,
        'PaymentStatus' => $m->payment_status,
        'DeliveryStatus' => $m->delivery_status,
        'DeliveryLog' => $m->delivery_log,
        'ProductName' => $m->product_name,
        'TenorDays' => $m->tenor_days,
        'IsActivated' => (bool)$m->is_activated,
        'ActivationDate' => optional($m->activation_date_utc)->toISOString(),
        'ExpiresAt' => optional($m->expires_at_utc)->toISOString(),
        'MaxhineId' => $m->machine_id,
        'DeviceId' => $m->device_id,
        'MaxSeats' => $m->max_seats,
        'MaxVideo' => $m->max_video,
        'MaxSeatsShopeeScrap' => $m->max_seats_shopee_scrap,
        'UsedSeatsShopeeScrap' => $m->used_seats_shopee_scrap,
        'Features' => $m->features,
        'Version' => $m->version,
        'LastUsed' => optional($m->last_used)->toISOString(),
        'RowVerBase64' => null,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/customerlicense', function (Request $request) {
    $data = $request->all();
    $orderId = 'ORD-NEW-'.rand(100,999);
    $licenseKey = 'LIC-'.strtoupper(bin2hex(random_bytes(4)));
    $statusInit = $data['Status'] ?? $data['status'] ?? 'InActive';
    $m = CustomerLicense::create([
        'order_id' => $orderId,
        'license_key' => $licenseKey,
        'owner' => $data['Owner'] ?? null,
        'email' => $data['Email'] ?? null,
        'phone' => $data['Phone'] ?? null,
        'edition' => $data['Edition'] ?? null,
        'payment_status' => $data['PaymentStatus'] ?? null,
        'product_name' => $data['ProductName'] ?? null,
        'tenor_days' => $data['TenorDays'] ?? null,
        'max_seats' => $data['MaxSeats'] ?? null,
        'max_video' => $data['MaxVideo'] ?? null,
        'max_seats_shopee_scrap' => $data['MaxSeatsShopeeScrap'] ?? null,
        'used_seats_shopee_scrap' => $data['UsedSeatsShopeeScrap'] ?? 0,
        'features' => $data['Features'] ?? null,
        'expires_at_utc' => isset($data['ExpiresAtUtc']) && $data['ExpiresAtUtc'] ? $data['ExpiresAtUtc'] : null,
        'is_activated' => isset($data['IsActivated']) ? (bool)$data['IsActivated'] : false,
        'machine_id' => $data['MachineId'] ?? null,
        'device_id' => $data['DeviceId'] ?? null,
        'status' => $statusInit,
    ]);
    return response()->json(['OrderId'=>$m->order_id,'LicenseKey'=>$m->license_key],201);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::put('/api/customerlicense/{id}', function ($id, Request $request) {
    $m = CustomerLicense::query()->where('order_id',$id)->first();
    if (!$m) return response()->json(['message'=>'Not found'],404);
    $data = $request->all();
    $m->fill([
        'license_key' => $data['LicenseKey'] ?? $m->license_key,
        'owner' => $data['Owner'] ?? $m->owner,
        'email' => $data['Email'] ?? $m->email,
        'phone' => $data['Phone'] ?? $m->phone,
        'edition' => $data['Edition'] ?? $m->edition,
        'payment_status' => $data['PaymentStatus'] ?? $m->payment_status,
        'product_name' => $data['ProductName'] ?? $m->product_name,
        'tenor_days' => $data['TenorDays'] ?? $m->tenor_days,
        'max_seats' => $data['MaxSeats'] ?? $m->max_seats,
        'max_video' => $data['MaxVideo'] ?? $m->max_video,
        'max_seats_shopee_scrap' => $data['MaxSeatsShopeeScrap'] ?? $m->max_seats_shopee_scrap,
        'used_seats_shopee_scrap' => $data['UsedSeatsShopeeScrap'] ?? $m->used_seats_shopee_scrap,
        'features' => $data['Features'] ?? $m->features,
        'status' => $data['Status'] ?? $m->status,
        'is_activated' => isset($data['IsActivated']) ? (bool)$data['IsActivated'] : $m->is_activated,
        'activation_date_utc' => isset($data['ActivationDateUtc']) && $data['ActivationDateUtc'] ? $data['ActivationDateUtc'] : $m->activation_date_utc,
        'expires_at_utc' => isset($data['ExpiresAtUtc']) && $data['ExpiresAtUtc'] ? $data['ExpiresAtUtc'] : $m->expires_at_utc,
        'machine_id' => $data['MachineId'] ?? $m->machine_id,
        'device_id' => $data['DeviceId'] ?? $m->device_id,
    ]);
    $m->save();
    return response()->json(['ok'=>true]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::delete('/api/customerlicense/{id}', function ($id, Request $request) {
    $m = CustomerLicense::query()->where('order_id',$id)->first();
    if (!$m) return response()->json(['message'=>'Not found'],404);
    $hard = $request->query('hard') === '1' || $request->query('hard') === 'true';
    if ($hard) { $m->forceDelete(); } else { $m->delete(); }
    return response()->json(['ok' => true, 'hard' => $hard]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/customerlicense/{id}/vo', function ($id) {
    $m = CustomerLicense::query()->where('order_id',$id)->first();
    if (!$m) return response()->json(['message'=>'Not found'],404);
    $sec = (int)($m->vo_seconds_remaining ?? 0);
    return response()->json(['ok'=>true,'orderId'=>$id,'seconds' => $sec, 'minutes' => (int)ceil($sec/60.0)]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/customerlicense/{id}/vo/topup', function ($id, Request $request) {
    $m = CustomerLicense::query()->where('order_id',$id)->first();
    if (!$m) return response()->json(['message'=>'Not found'],404);
    $add = (int)($request->input('addSeconds') ?? 0);
    $add = max(0,$add);
    $m->vo_seconds_remaining = ($m->vo_seconds_remaining ?? 0) + $add;
    $m->save();
    VoiceOverTransaction::create(['license_id'=>$m->id,'type'=>'topup','seconds'=>$add]);
    $sec = (int)($m->vo_seconds_remaining ?? 0);
    return response()->json(['ok'=>true,'orderId'=>$id,'seconds_remaining' => $sec, 'minutes_remaining' => (int)ceil($sec/60.0)]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/customerlicense/{id}/vo/debit', function ($id, Request $request) {
    $m = CustomerLicense::query()->where('order_id',$id)->first();
    if (!$m) { $m = CustomerLicense::query()->where('license_key',$id)->first(); }
    if (!$m) return response()->json(['message'=>'Not found'],404);
    $use = (int)($request->input('secondsUsed') ?? $request->input('charsVo') ?? 0);
    $use = max(0,$use);
    $debited = min($use, $m->vo_seconds_remaining ?? 0);
    $m->vo_seconds_remaining = max(0, ($m->vo_seconds_remaining ?? 0) - $debited);
    $m->save();
    VoiceOverTransaction::create(['license_id'=>$m->id,'type'=>'debit','seconds'=>$debited]);
    return response()->json(['ok'=>true,'license'=>$id,'seconds_remaining' => (int)($m->vo_seconds_remaining ?? 0), 'seconds_debited' => (int)$debited]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/customerlicense/{id}/activate', function ($id, Request $request) {
    $m = CustomerLicense::query()->where('order_id',$id)->first();
    if (!$m) return response()->json(['message'=>'Not found'],404);
    $machineId = $request->input('MachineId');
    $activationDate = $request->input('ActivationDateUtc');
    $m->is_activated = true;
    $m->activation_date_utc = $activationDate ? $activationDate : now('UTC');
    if ($machineId) { $m->machine_id = $machineId; }
    $m->save();
    return response()->json([
        'OrderId' => $m->order_id,
        'IsActivated' => (bool)$m->is_activated,
        'ActivationDate' => optional($m->activation_date_utc)->toISOString(),
        'MaxhineId' => $m->machine_id,
        'Status' => $m->status,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/license/activate', function (Request $request) {
    $key = trim((string)($request->input('LicenseKey') ?? $request->input('licenseKey') ?? $request->input('license') ?? $request->input('key')));
    $email = trim((string)($request->input('Email') ?? $request->input('email')));
    $mid = trim((string)($request->input('MachineId') ?? $request->input('machineId') ?? $request->input('MachineID') ?? $request->input('mid')));
    // MachineId opsional; aktivasi tanpa verifikasi signature berdasarkan permintaan
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
    // Signature tidak diverifikasi: cukup cek license, expired, dan email match
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
    // Simpan MachineId hanya jika dikirim klien
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
            'ExpiresAt' => optional($lic->expires_at_utc)->toISOString(),
        ],
    ],200);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::match(['GET','POST'],'/api/license/reset', function (Request $request) {
    // Ambil input standar (Trimmed oleh middleware atau manual)
    $key = trim((string)($request->input('LicenseKey') ?? $request->input('licenseKey') ?? $request->input('license') ?? $request->input('key')));
    $email = trim((string)($request->input('Email') ?? $request->input('email')));
    $sig = trim((string)($request->input('Signature') ?? $request->input('signature') ?? $request->input('sig')));
    $newMid = trim((string)($request->input('MachineId') ?? $request->input('machineId') ?? $request->input('mid')));

    // Ambil Raw Input dari JSON Body (untuk menangani kasus spasi yang tidak sengaja terkirim dan ter-hash di client)
    $rawContent = $request->getContent();
    $rawJson = json_decode($rawContent, true);
    $rawKey = null;
    $rawEmail = null;
    $rawMid = null;
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
    // Verifikasi Signature Robust: Coba berbagai variasi case dan format
    $inputs = [];
    
    // Variasi komponen untuk antisipasi case sensitivity client & raw input
    $keyVars = [$key, strtoupper($key), strtolower($key)];
    if ($rawKey && $rawKey !== $key) { $keyVars[] = $rawKey; }
    $keyVars = array_unique($keyVars);

    $emailVars = [$email, strtolower($email), strtoupper($email)];
    if ($rawEmail && $rawEmail !== $email) { $emailVars[] = $rawEmail; }
    $emailVars = array_unique($emailVars);
    
    // Variasi Machine ID
    $midVars = ['']; // Selalu coba anggap kosong
    if ($lic->machine_id) { $midVars[] = $lic->machine_id; }
    if ($newMid && $newMid !== $lic->machine_id) { $midVars[] = $newMid; }
    if ($rawMid !== null && $rawMid !== $newMid) { $midVars[] = $rawMid; }
    $midVars = array_unique($midVars);

    foreach ($keyVars as $_k) {
        foreach ($emailVars as $_e) {
            // Pola 1: Key|Email (Tanpa Mid)
            $inputs[] = "$_k|$_e";
            
            foreach ($midVars as $_m) {
                // Pola 2: Key|Mid|Email
                $inputs[] = "$_k|$_m|$_e";
                // Pola 3: Key|Email|Mid
                $inputs[] = "$_k|$_e|$_m";
                // Pola 4: Key||Email (Jika mid kosong menghasilkan separator ganda)
                if ($_m === '') { $inputs[] = "$_k||$_e"; }
            }
        }
    }

    $ok = false; 
    $successDetail = "";
    foreach ($inputs as $canon) { 
        if (verifySignatureFlexible($canon, $sig)) { 
            $ok = true; 
            $successDetail = "(Matched: $canon)";
            break; 
        } 
    }

    if (!$ok) {
        // FALLBACK STRATEGY (Saran User):
        // Jika signature gagal (mungkin karena MachineID mismatch), kita tetap izinkan Reset
        // ASALKAN Email dan LicenseKey sudah cocok (sudah diverifikasi di atas).
        // Sebagai pengaman tambahan, kita pastikan MaxSeats = 1 agar tidak terjadi multiple login bocor.
        
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
            'ExpiresAt' => optional($lic->expires_at_utc)->toISOString(),
        ],
    ],200);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/configapikey', function (Request $request) {
    $q = $request->query('q');
    $rows = DB::table('ConfigApiKey')
        ->when($q, function($qr) use ($q){
            $like = '%'.$q.'%';
            $qr->where(function($w) use ($like){
                $w->where('JenisApiKey','like',$like)
                  ->orWhere('ApiKey','like',$like)
                  ->orWhere('Model','like',$like)
                  ->orWhere('Status','like',$like);
            });
        })
        ->orderByDesc('UpdatedAt')
        ->get();
    $items = $rows->map(function($x){
        $cool = $x->CooldownUntilPT ? \Illuminate\Support\Carbon::parse($x->CooldownUntilPT)->toISOString() : null;
        $upd = $x->UpdatedAt ? \Illuminate\Support\Carbon::parse($x->UpdatedAt)->toISOString() : null;
        return [
            'ApiKeyId' => $x->ApiKeyId ?? null,
            'JenisApiKey' => $x->JenisApiKey ?? null,
            'ApiKey' => $x->ApiKey ?? null,
            'Model' => $x->Model ?? null,
            'DefaultVoiceId' => $x->DefaultVoiceId ?? null,
            'voice_id' => $x->DefaultVoiceId ?? null,
            'Status' => $x->Status ?? null,
            'CooldownUntilPT' => $cool,
            'UpdatedAt' => $upd,
        ];
    });
    return response()->json($items);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/configapikey/{id}', function ($id) {
    $x = DB::table('ConfigApiKey')->where('ApiKeyId',$id)->first();
    if (!$x) return response()->json(['message'=>'Not found'],404);
    $cool = $x->CooldownUntilPT ? \Illuminate\Support\Carbon::parse($x->CooldownUntilPT)->toISOString() : null;
    $upd = $x->UpdatedAt ? \Illuminate\Support\Carbon::parse($x->UpdatedAt)->toISOString() : null;
    return response()->json([
        'ApiKeyId' => $x->ApiKeyId ?? null,
        'JenisApiKey' => $x->JenisApiKey ?? null,
        'ApiKey' => $x->ApiKey ?? null,
        'Model' => $x->Model ?? null,
        'DefaultVoiceId' => $x->DefaultVoiceId ?? null,
        'voice_id' => $x->DefaultVoiceId ?? null,
        'Status' => $x->Status ?? null,
        'CooldownUntilPT' => $cool,
        'UpdatedAt' => $upd,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/configapikey', function (Request $request) {
    $data = $request->all();
    $now = now('UTC');
    $nextId = ((int) (DB::table('ConfigApiKey')->max('ApiKeyId') ?? 0)) + 1;
    $coolDb = null;
    if (isset($data['CooldownUntilPT']) && $data['CooldownUntilPT']) {
        try {
            $coolDb = \Illuminate\Support\Carbon::parse($data['CooldownUntilPT'])->setTimezone('UTC')->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            $coolDb = null;
        }
    }
    DB::table('ConfigApiKey')->insert([
        'ApiKeyId' => $nextId,
        'JenisApiKey' => $data['JenisApiKey'] ?? null,
        'ApiKey' => $data['ApiKey'] ?? null,
        'Model' => $data['Model'] ?? null,
        'DefaultVoiceId' => $data['DefaultVoiceId'] ?? null,
        'Status' => $data['Status'] ?? 'AVAILABLE',
        'CooldownUntilPT' => $coolDb,
        'UpdatedAt' => $now,
        'CreatedAt' => $now,
    ]);
    return response()->json(['ok'=>true,'ApiKeyId'=>$nextId],201);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::put('/api/configapikey/{id}', function ($id, Request $request) {
    $data = $request->all();
    $now = now('UTC');
    $update = [
        'UpdatedAt' => $now,
    ];
    if (array_key_exists('JenisApiKey',$data)) $update['JenisApiKey'] = $data['JenisApiKey'];
    if (array_key_exists('ApiKey',$data)) $update['ApiKey'] = $data['ApiKey'];
   if (array_key_exists('Model',$data)) {
        if (!empty($data['Model'])) {
            $update['Model'] = $data['Model'];
        }
    }

    if (array_key_exists('DefaultVoiceId',$data)) $update['DefaultVoiceId'] = $data['DefaultVoiceId'];
    if (array_key_exists('Status',$data)) $update['Status'] = $data['Status'];
    if (array_key_exists('CooldownUntilPT',$data)) {
        $coolDb = null;
        if ($data['CooldownUntilPT']) {
            try { $coolDb = \Illuminate\Support\Carbon::parse($data['CooldownUntilPT'])->setTimezone('UTC')->format('Y-m-d H:i:s'); }
            catch (\Throwable $e) { $coolDb = null; }
        }
        $update['CooldownUntilPT'] = $coolDb; // boleh null untuk clear cooldown
    }
    $aff = DB::table('ConfigApiKey')->where('ApiKeyId',$id)->update($update);
    if (!$aff) return response()->json(['message'=>'Not found'],404);
    return response()->json(['ok'=>true]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::delete('/api/configapikey/{id}', function ($id) {
    $aff = DB::table('ConfigApiKey')->where('ApiKeyId',$id)->delete();
    if (!$aff) return response()->json(['message'=>'Not found'],404);
    return response()->json(['ok'=>true]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/configapikey/openai', function (Request $request) {
    $onlyAvailable = $request->boolean('only_available');
    $rows = DB::table('ConfigApiKey')
        ->whereRaw('LOWER(JenisApiKey) = ?', ['openai'])
        ->whereNotNull('ApiKey')
        ->where('ApiKey', '<>', '')
        ->when($onlyAvailable, function ($q) {
            $q->where('Status', 'AVAILABLE')
              ->where(function ($qq) {
                  $qq->whereNull('CooldownUntilPT')
                     ->orWhere('CooldownUntilPT', '<', now());
              })
              ->whereColumn('MinuteCount', '<', 'RpmLimit')
              ->whereColumn('DayCount', '<', 'RpdLimit');
        })
        ->orderByDesc('UpdatedAt')
        ->get();

    $items = $rows->map(function ($x) {
        $cool = $x->CooldownUntilPT ? \Illuminate\Support\Carbon::parse($x->CooldownUntilPT)->toISOString() : null;
        $upd = $x->UpdatedAt ? \Illuminate\Support\Carbon::parse($x->UpdatedAt)->toISOString() : null;
        return [
            'ApiKeyId' => $x->ApiKeyId ?? null,
            'JenisApiKey' => $x->JenisApiKey ?? null,
            'ApiKey' => $x->ApiKey ?? null,
            'Model' => $x->Model ?? null,
            'DefaultVoiceId' => $x->DefaultVoiceId ?? null,
            'voice_id' => $x->DefaultVoiceId ?? null,
            'Status' => $x->Status ?? null,
            'CooldownUntilPT' => $cool,
            'UpdatedAt' => $upd,
        ];
    });

    return response()->json($items);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/configapikey/openai/best', function () {
    $x = DB::table('ConfigApiKey')
        ->whereRaw('LOWER(JenisApiKey) = ?', ['openai'])
        ->whereNotNull('ApiKey')
        ->where('ApiKey', '<>', '')
        ->where('Status', 'AVAILABLE')
        ->where(function ($qq) {
            $qq->whereNull('CooldownUntilPT')
               ->orWhere('CooldownUntilPT', '<', now());
        })
        ->whereColumn('MinuteCount', '<', 'RpmLimit')
        ->whereColumn('DayCount', '<', 'RpdLimit')
        ->orderBy('MinuteCount', 'asc')
        ->orderBy('DayCount', 'asc')
        ->orderByDesc('UpdatedAt')
        ->first();

    if (!$x) {
        return response()->json(['message' => 'No OpenAI key available'], 404);
    }

    $cool = $x->CooldownUntilPT ? \Illuminate\Support\Carbon::parse($x->CooldownUntilPT)->toISOString() : null;
    $upd = $x->UpdatedAt ? \Illuminate\Support\Carbon::parse($x->UpdatedAt)->toISOString() : null;

    return response()->json([
        'ApiKeyId' => $x->ApiKeyId ?? null,
        'JenisApiKey' => $x->JenisApiKey ?? null,
        'ApiKey' => $x->ApiKey ?? null,
        'Model' => $x->Model ?? null,
        'DefaultVoiceId' => $x->DefaultVoiceId ?? null,
        'voice_id' => $x->DefaultVoiceId ?? null,
        'Status' => $x->Status ?? null,
        'CooldownUntilPT' => $cool,
        'UpdatedAt' => $upd,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/orderdata', function (Request $request) {
    $q = $request->query('q');
    $rows = DB::table('OrderData')
        ->when($q, function($qr) use ($q){
            $like = '%'.$q.'%';
            $qr->where(function($w) use ($like){
                $w->where('OrderId','like',$like)
                  ->orWhere('Email','like',$like)
                  ->orWhere('Phone','like',$like)
                  ->orWhere('Name','like',$like)
                  ->orWhere('ProductName','like',$like);
            });
        })
        ->orderByDesc('CreatedAt')
        ->get();
    $items = $rows->map(function($x){
        $cr = $x->CreatedAt ? \Illuminate\Support\Carbon::parse($x->CreatedAt)->toISOString() : null;
        $up = $x->UpdatedAt ? \Illuminate\Support\Carbon::parse($x->UpdatedAt)->toISOString() : null;
        return [
            'OrderId' => $x->OrderId,
            'Email' => $x->Email,
            'Phone' => $x->Phone,
            'Name' => $x->Name,
            'ProductName' => $x->ProductName,
            'VariantPrice' => $x->VariantPrice,
            'NetRevenue' => $x->NetRevenue,
            'Status' => $x->Status,
            'CreatedAt' => $cr,
            'UpdatedAt' => $up,
        ];
    });
    return response()->json($items);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/orderdata/{id}', function ($id) {
    $x = DB::table('OrderData')->where('OrderId',$id)->first();
    if (!$x) return response()->json(['message'=>'Not found'],404);
    $cr = $x->CreatedAt ? \Illuminate\Support\Carbon::parse($x->CreatedAt)->toISOString() : null;
    $up = $x->UpdatedAt ? \Illuminate\Support\Carbon::parse($x->UpdatedAt)->toISOString() : null;
    return response()->json([
        'OrderId' => $x->OrderId,
        'Email' => $x->Email,
        'Phone' => $x->Phone,
        'Name' => $x->Name,
        'ProductName' => $x->ProductName,
        'VariantPrice' => $x->VariantPrice,
        'NetRevenue' => $x->NetRevenue,
        'Status' => $x->Status,
        'CreatedAt' => $cr,
        'UpdatedAt' => $up,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/orderdata', function (Request $request) {
    $data = $request->all();
    $cr = null;
    if (!empty($data['CreatedAtUtc'])) {
        try { $cr = \Illuminate\Support\Carbon::parse($data['CreatedAtUtc'])->setTimezone('UTC')->format('Y-m-d H:i:s'); } catch (\Throwable $e) { $cr = null; }
    }
    DB::table('OrderData')->insert([
        'OrderId' => $data['OrderId'] ?? ('ORD-'.strtoupper(bin2hex(random_bytes(5)))),
        'Email' => $data['Email'] ?? null,
        'Phone' => $data['Phone'] ?? null,
        'Name' => $data['Name'] ?? null,
        'ProductName' => $data['ProductName'] ?? null,
        'VariantPrice' => $data['VariantPrice'] ?? null,
        'NetRevenue' => $data['NetRevenue'] ?? null,
        'Status' => $data['Status'] ?? 'Not Paid',
        'CreatedAt' => $cr ?? now('UTC'),
        'UpdatedAt' => now('UTC'),
    ]);
    return response()->json(['ok'=>true],201);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::put('/api/orderdata/{id}', function ($id, Request $request) {
    $data = $request->all();
    $upd = ['UpdatedAt' => now('UTC')];
    foreach (['Email','Phone','Name','ProductName','VariantPrice','NetRevenue','Status'] as $f) {
        if (array_key_exists($f,$data)) $upd[$f] = $data[$f];
    }
    if (array_key_exists('CreatedAtUtc',$data)) {
        $cr = null; if ($data['CreatedAtUtc']) { try { $cr = \Illuminate\Support\Carbon::parse($data['CreatedAtUtc'])->setTimezone('UTC')->format('Y-m-d H:i:s'); } catch (\Throwable $e) { $cr = null; } }
        $upd['CreatedAt'] = $cr ?? DB::raw('CreatedAt');
    }
    $aff = DB::table('OrderData')->where('OrderId',$id)->update($upd);
    if (!$aff) return response()->json(['message'=>'Not found'],404);
    return response()->json(['ok'=>true]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::delete('/api/orderdata/{id}', function ($id) {
    $aff = DB::table('OrderData')->where('OrderId',$id)->delete();
    if (!$aff) return response()->json(['message'=>'Not found'],404);
    return response()->json(['ok'=>true]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/customerlicense/{id}/reset', function ($id, Request $request) {
    $email = trim((string)$request->input('Email'));
    $sig = trim((string)$request->input('Signature'));
    $lic = CustomerLicense::query()->where('order_id',$id)->first();
    if (!$lic) return response()->json(['Success'=>false,'Message'=>'Not found','ErrorCode'=>'LICENSE_NOT_FOUND'],404);
    if ($email && strcasecmp((string)$lic->email,$email)!==0) return response()->json(['Success'=>false,'Message'=>'Email tidak sesuai.','ErrorCode'=>'EMAIL_MISMATCH'],400);
    if ($sig) {
        // Terima signature normal (License|MachineId|Email). Jika gagal, abaikan MachineId,
        // atau coba urutan alternatif (License|Email|MachineId).
        $inputs = [
            ($lic->license_key ?? '').'|'.($lic->machine_id ?? '').'|'.($email ?: $lic->email ?? ''),
            ($lic->license_key ?? '').'|'.($email ?: $lic->email ?? ''),
            ($lic->license_key ?? '').'||'.($email ?: $lic->email ?? ''),
            ($lic->license_key ?? '').'|'.($email ?: $lic->email ?? '').'|'.($lic->machine_id ?? ''),
        ];
        $ok = false; foreach ($inputs as $canon) { if (verifySignatureFlexible($canon, $sig)) { $ok = true; break; } }
        if (!$ok) return response()->json(['Success'=>false,'Message'=>'Signature tidak valid.','ErrorCode'=>'INVALID_SIGNATURE'],400);
    }

// (dipindah ke luar closure route di bawah)
    $lic->status = 'Reset';
    $lic->is_activated = false;
    $lic->activation_date_utc = null;
    $prevMid = $lic->machine_id;
    $lic->machine_id = null;
    $lic->save();
    DB::table('license_actions')->insert(['license_key'=>$lic->license_key,'order_id'=>$lic->order_id,'email'=>$lic->email,'action'=>'Reset','result'=>'Success','message'=>'License reset.','created_at'=>now(),'updated_at'=>now()]);
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
            'ExpiresAt' => optional($lic->expires_at_utc)->toISOString(),
        ],
    ],200);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
// Endpoint debug: preview berbagai bentuk signature yang diharapkan
Route::post('/api/license/signature/preview', function (Request $request) {
    $key = trim((string)($request->input('LicenseKey') ?? $request->input('licenseKey') ?? $request->input('license') ?? $request->input('key')));
    $email = trim((string)($request->input('Email') ?? $request->input('email')));
    $mid = trim((string)($request->input('MachineId') ?? $request->input('machineId') ?? $request->input('MachineID') ?? $request->input('mid')));
    if (!$key || !$email) return response()->json(['ok'=>false,'message'=>'LicenseKey dan Email wajib diisi.'],400);
    $variants = [
        'License|MachineId|Email' => $key.'|'.$mid.'|'.$email,
        'License|Email' => $key.'|'.$email,
        'License||Email' => $key.'||'.$email,
        'License|Email|MachineId' => $key.'|'.$email.'|'.$mid,
    ];
    $out = [];
    foreach ($variants as $name=>$canon) {
        $hex = hash('sha256', $canon);
        $b64 = base64_encode(hash('sha256', $canon, true));
        $out[$name] = ['input'=>$canon,'hex'=>$hex,'base64'=>$b64];
    }
    return response()->json(['ok'=>true,'variants'=>$out]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Endpoint debug: cek signature yang dikirim klien, tunjukkan varian yang cocok
Route::post('/api/license/signature/check', function (Request $request) {
    $key = trim((string)($request->input('LicenseKey') ?? $request->input('licenseKey') ?? $request->input('license') ?? $request->input('key')));
    $email = trim((string)($request->input('Email') ?? $request->input('email')));
    $mid = trim((string)($request->input('MachineId') ?? $request->input('machineId') ?? $request->input('MachineID') ?? $request->input('mid')));
    $sig = trim((string)($request->input('Signature') ?? $request->input('signature') ?? $request->input('sig')));
    if (!$key || !$email || !$sig) return response()->json(['ok'=>false,'message'=>'LicenseKey, Email, dan Signature wajib diisi.'],400);
    $variants = [
        'License|MachineId|Email' => $key.'|'.$mid.'|'.$email,
        'License|Email' => $key.'|'.$email,
        'License||Email' => $key.'||'.$email,
        'License|Email|MachineId' => $key.'|'.$email.'|'.$mid,
    ];
    $matched = null; $canonUsed = null; $format = null;
    $details = [];
    foreach ($variants as $name=>$canon) {
        $hex = hash('sha256', $canon);
        $bin = hash('sha256', $canon, true);
        $b64 = base64_encode($bin);
        // url-safe tanpa padding
        $b64Url = rtrim(strtr($b64, '+/', '-_'), '=');
        // base64 dari teks input (sering salah kaprah di klien)
        $b64Text = base64_encode($canon);
        $details[$name] = [
            'input' => $canon,
            'expectedHex' => $hex,
            'expectedBase64' => $b64,
            'expectedBase64Url' => $b64Url,
            'base64OfText' => $b64Text,
        ];
        if (!$matched) {
            if (hash_equals($hex, strtolower($sig))) { $matched = $name; $canonUsed = $canon; $format = 'hex'; }
            elseif (hash_equals($b64, $sig)) { $matched = $name; $canonUsed = $canon; $format = 'base64'; }
            else {
                // longgar via helper
                if (verifySignatureFlexible($canon, $sig)) { $matched = $name; $canonUsed = $canon; $format = 'flexible'; }
                // terdeteksi sering salah: base64 dari teks input
                else {
                    $sigUrl = strtr($sig, '-_', '+/'); $pad = strlen($sigUrl)%4; if ($pad) { $sigUrl .= str_repeat('=',4-$pad); }
                    $decoded = base64_decode($sigUrl, true);
                    if ($decoded !== false && hash_equals($decoded, $canon)) { $matched = $name; $canonUsed = $canon; $format = 'base64_of_text'; }
                }
            }
        }
    }
    if ($matched) {
        return response()->json(['ok'=>true,'matchedVariant'=>$matched,'format'=>$format,'inputCanonical'=>$canonUsed,'details'=>$details]);
    }
    return response()->json(['ok'=>false,'message'=>'Signature tidak cocok dengan varian yang dikenal.','details'=>$details],400);
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
    $exp = $lic->expires_at_utc ? \Carbon\Carbon::parse($lic->expires_at_utc)->format('Y-m-d') : null;
    $statusVal = (string)($lic->status ?? '');
    return response()->json([
        'Success' => true,
        'Message' => 'License found.',
        'Data' => [
            'LicenseKey' => $lic->license_key,
            'Email' => $lic->email,
            'Status' => $statusVal,
            'ExpiresAt' => optional($lic->expires_at_utc)->toISOString(),
            'expirationdate' => $exp,
            'expirationDate' => $exp,
            'IsActivated' => (bool)$lic->is_activated,
            'ActivationDate' => optional($lic->activation_date_utc)->toISOString(),
            'MachineId' => $lic->machine_id,
            'Edition' => $lic->edition,
            'ProductName' => $lic->product_name,
            'Version' => $lic->version,
            'LastUsed' => optional($lic->last_used)->toISOString(),
        ]
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/license/usage', function (Request $request) {
    $key = trim((string)($request->input('LicenseKey') ?? $request->input('licenseKey') ?? $request->input('license') ?? $request->input('key')));
    $version = trim((string)($request->input('Version') ?? $request->input('version') ?? $request->input('ver')));
    $lastUsedInput = $request->input('LastUsed') ?? $request->input('lastUsed') ?? $request->input('last_used');
    
    if (!$key) {
        return response()->json(['Success'=>false,'Message'=>'LicenseKey wajib diisi.','ErrorCode'=>'INVALID_REQUEST'],400);
    }
    
    $lic = CustomerLicense::query()->where('license_key',$key)->first();
    if (!$lic) {
        return response()->json(['Success'=>false,'Message'=>'License not found.','ErrorCode'=>'LICENSE_NOT_FOUND'],404);
    }
    
    // Gunakan waktu dari client jika ada, jika tidak gunakan waktu server
    $timestamp = now('UTC');
    if ($lastUsedInput) {
        try {
            $timestamp = \Illuminate\Support\Carbon::parse($lastUsedInput)->setTimezone('UTC');
        } catch (\Throwable $e) {
            // Jika format tanggal invalid, fallback ke now()
            $timestamp = now('UTC');
        }
    }

    $lic->last_used = $timestamp;
    if ($version) {
        $lic->version = $version;
    }
    $lic->save();
    
    return response()->json([
        'Success' => true,
        'Message' => 'Usage recorded.',
        'Data' => [
            'LicenseKey' => $lic->license_key,
            'Version' => $lic->version,
            'LastUsed' => optional($lic->last_used)->toISOString(),
        ]
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/license/generate', function (Request $request) {
    $email = $request->input('Email');
    $edition = trim((string)($request->input('Edition') ?? ''));
    if (!$email || !$edition) {
        return response()->json(['Success'=>false,'Message'=>'Email dan Edition wajib diisi.','ErrorCode'=>'INVALID_REQUEST'],400);
    }
    $isUpgrade = filter_var($request->input('StatusUpgrade') ?? $request->input('statusUpdrage'), FILTER_VALIDATE_BOOLEAN);
    $newKey = strtoupper(\Illuminate\Support\Str::uuid()->toString());
    try {
        if (!$isUpgrade) {
            $validityDays = null; $maxVideo = null;
            if (strcasecmp($edition,'Trial')===0) { $validityDays = $request->input('ValidityDays') ?? 7; $maxVideo = 30; }
            else if (strcasecmp($edition,'Basic')===0 || strcasecmp($edition,'Pro')===0) { $validityDays = $request->input('ValidityDays') ?? 180; $maxVideo = 2147483647; }
            else {
                DB::table('license_actions')->insert(['license_key'=>$newKey,'order_id'=>null,'email'=>$email,'action'=>'Generate','result'=>'Failed','message'=>'Edition '.$edition.' tidak dikenal.','created_at'=>now(),'updated_at'=>now()]);
                return response()->json(['Success'=>false,'Message'=>'Edition tidak valid.','ErrorCode'=>'INVALID_EDITION'],400);
            }
            $expires = now('UTC')->addDays((int)$validityDays);
            $featuresInput = $request->input('Features');
            $featuresJson = is_array($featuresInput) ? json_encode($featuresInput) : ($featuresInput ?: json_encode(['Batch','TextOverlay']));
            $lic = CustomerLicense::create([
                'order_id' => 'ORD-GEN-'.rand(100,999),
                'license_key' => $newKey,
                'owner' => $request->input('Owner'),
                'email' => $email,
                'edition' => $edition,
                'expires_at_utc' => $expires,
                'max_seats' => $request->input('MaxSeats') ?? 1,
                'features' => $featuresJson,
                'max_video' => $maxVideo,
                'status' => 'InActive',
            ]);
            DB::table('license_actions')->insert(['license_key'=>$lic->license_key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Generate','result'=>'Success','message'=>'License '.$edition.' berhasil dibuat, berlaku sampai '.$lic->expires_at_utc->format('Y-m-d').'.','created_at'=>now(),'updated_at'=>now()]);
            return response()->json(['Success'=>true,'Message'=>'License generated.','Data'=>['LicenseKey'=>$lic->license_key,'Email'=>$lic->email,'Edition'=>$lic->edition,'Status'=>$lic->status]],200);
        } else {
            $lic = CustomerLicense::query()->where('email',$email)->whereRaw('LOWER(status) = ?', ['active'])->first();
            if (!$lic) {
                DB::table('license_actions')->insert(['license_key'=>$newKey,'order_id'=>null,'email'=>$email,'action'=>'Generate','result'=>'Failed','message'=>'Tidak ada license aktif untuk upgrade.','created_at'=>now(),'updated_at'=>now()]);
                return response()->json(['Success'=>false,'Message'=>'Tidak ada license aktif untuk upgrade.','ErrorCode'=>'NO_ACTIVE_LICENSE'],400);
            }
            if (strcasecmp($edition,'Basic')===0 || strcasecmp($edition,'Pro')===0) {
                $validityDays = $request->input('ValidityDays') ?? 180;
                $lic->edition = $edition;
                $lic->expires_at_utc = now('UTC')->addDays((int)$validityDays);
                if (!is_null($request->input('MaxSeats'))) { $lic->max_seats = (int)$request->input('MaxSeats'); }
                $lic->max_video = 2147483647;
                $featuresInput = $request->input('Features');
                $featuresJson = is_array($featuresInput) ? json_encode($featuresInput) : ($featuresInput ?: json_encode(['Batch','TextOverlay']));
                $lic->features = $featuresJson;
                $lic->save();
                DB::table('license_actions')->insert(['license_key'=>$lic->license_key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Generate','result'=>'Success','message'=>'License di-upgrade menjadi '.$edition.', berlaku sampai '.$lic->expires_at_utc->format('Y-m-d').'.','created_at'=>now(),'updated_at'=>now()]);
                return response()->json(['Success'=>true,'Message'=>'License upgraded.','Data'=>['LicenseKey'=>$lic->license_key,'Email'=>$lic->email,'Edition'=>$lic->edition,'Status'=>$lic->status]],200);
            } else {
                DB::table('license_actions')->insert(['license_key'=>$lic->license_key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Generate','result'=>'Failed','message'=>'Edition '.$edition.' tidak valid untuk upgrade.','created_at'=>now(),'updated_at'=>now()]);
                return response()->json(['Success'=>false,'Message'=>'Edition tidak valid untuk upgrade.','ErrorCode'=>'INVALID_UPGRADE_EDITION'],400);
            }
        }
    } catch (\Exception $ex) {
        DB::table('license_actions')->insert(['license_key'=>$newKey,'order_id'=>null,'email'=>$email,'action'=>'Generate','result'=>'Failed','message'=>'Exception: '.$ex->getMessage(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['Success'=>false,'Message'=>'Terjadi kesalahan internal.','ErrorCode'=>'INTERNAL_ERROR'],500);
    }
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/voice/{license}/voice-config', function ($license) {
    $lic = CustomerLicense::query()->where('license_key',$license)->first();
    if (!$lic) return response()->json(['ok'=>false,'message'=>'Not found'],404);
    $remain = (int)($lic->vo_seconds_remaining ?? 0);
    if ($remain <= 0) {
        return response()->json([
            'ok' => false,
            'license' => $license,
            'message' => 'Silakan topup dulu',
        ],402);
    }
    $base = DB::table('ConfigApiKey')
        ->whereRaw('LOWER(status) = ?', ['available'])
        ->whereNotNull('ApiKey')
        ->where('ApiKey','<>','');
    $prefer = (clone $base)
        ->whereRaw('LOWER(Model) = ?', ['gemini-2.5-flash-preview-tts'])
        ->orderByDesc('UpdatedAt')
        ->select(['JenisApiKey','ApiKey','DefaultVoiceId','Model'])
        ->get();
    if ($prefer->isEmpty()) {
        $prefer = (clone $base)
            ->whereRaw('LOWER(Model) = ?', ['gemini-2.5-pro-preview-tts'])
            ->orderByDesc('UpdatedAt')
            ->select(['JenisApiKey','ApiKey','DefaultVoiceId','Model'])
            ->get();
    }
    $rows = $prefer->isEmpty()
        ? (clone $base)->orderByDesc('UpdatedAt')->select(['JenisApiKey','ApiKey','DefaultVoiceId','Model'])->get()
        : $prefer;
    $items = $rows->map(function($x) use ($remain){
        return [
            'Provider' => $x->JenisApiKey,
            'ApiKey' => $x->ApiKey,
            'DefaultVoiceId' => $x->DefaultVoiceId,
            'Model' => $x->Model,
            'SecondsRemaining' => $remain,
        ];
    });
    return response()->json([
        'ok' => true,
        'license' => $license,
        'seconds_remaining' => $remain,
        'apikeys' => $items,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/webhooks/scalev', [ScalevWebhookController::class,'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/generate-vo', [VoiceOverController::class,'generate'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/vo-status/{jobId}', [VoiceOverController::class,'status'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/whatsappconfig', function (Request $request) {
    $q = $request->query('q');
    $rows = DB::table('WhatsAppConfig')
        ->when($q, function($qr) use ($q){
            $like = '%'.$q+'%';
            $qr->where(function($w) use ($like){
                $w->where('ApiSecret','like',$like)
                  ->orWhere('AccountUniqueId','like',$like);
            });
        })
        ->orderByDesc('UpdatedAt')
        ->get();
    $items = $rows->map(function($x){
        $cr = $x->CreatedAt ? \Illuminate\Support\Carbon::parse($x->CreatedAt)->toISOString() : null;
        $up = $x->UpdatedAt ? \Illuminate\Support\Carbon::parse($x->UpdatedAt)->toISOString() : null;
        return [
            'Id' => $x->Id,
            'ApiSecret' => $x->ApiSecret,
            'AccountUniqueId' => $x->AccountUniqueId,
            'GroupLink' => $x->GroupLink ?? null,
            'InstallerLink' => $x->InstallerLink ?? null,
            'InstallerVersion' => $x->InstallerVersion ?? null,
            'CreatedAt' => $cr,
            'UpdatedAt' => $up,
        ];
    });
    return response()->json($items);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/whatsappconfig/{id}', function ($id) {
    $x = DB::table('WhatsAppConfig')->where('Id',$id)->first();
    if (!$x) return response()->json(['message'=>'Not found'],404);
    $cr = $x->CreatedAt ? \Illuminate\Support\Carbon::parse($x->CreatedAt)->toISOString() : null;
    $up = $x->UpdatedAt ? \Illuminate\Support\Carbon::parse($x->UpdatedAt)->toISOString() : null;
    return response()->json([
        'Id' => $x->Id,
        'ApiSecret' => $x->ApiSecret,
        'AccountUniqueId' => $x->AccountUniqueId,
        'GroupLink' => $x->GroupLink ?? null,
        'InstallerLink' => $x->InstallerLink ?? null,
        'InstallerVersion' => $x->InstallerVersion ?? null,
        'CreatedAt' => $cr,
        'UpdatedAt' => $up,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/whatsappconfig', function (Request $request) {
    $data = $request->all();
    $now = now('UTC');
    $maxId = (int) (DB::table('WhatsAppConfig')->max('Id') ?? 0);
    $idVal = (int) ($data['Id'] ?? 0);
    if ($idVal <= 0) { $idVal = $maxId + 1; }
    try {
        DB::table('WhatsAppConfig')->insert([
            'Id' => $idVal,
            'ApiSecret' => $data['ApiSecret'] ?? null,
            'AccountUniqueId' => $data['AccountUniqueId'] ?? null,
            'GroupLink' => $data['GroupLink'] ?? null,
            'InstallerLink' => $data['InstallerLink'] ?? null,
            'InstallerVersion' => $data['InstallerVersion'] ?? null,
            'UpdatedAt' => $now,
            'CreatedAt' => $now,
        ]);
        return response()->json(['ok'=>true,'Id'=>$idVal],201);
    } catch (\Throwable $e) {
        return response()->json(['ok'=>false,'message'=>$e->getMessage()],500);
    }
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::put('/api/whatsappconfig/{id}', function ($id, Request $request) {
    $data = $request->all();
    $upd = ['UpdatedAt' => now('UTC')];
    foreach (['ApiSecret','AccountUniqueId','GroupLink','InstallerLink','InstallerVersion'] as $f) {
        if (array_key_exists($f,$data)) $upd[$f] = $data[$f];
    }
    $aff = DB::table('WhatsAppConfig')->where('Id',$id)->update($upd);
    if (!$aff) return response()->json(['message'=>'Not found'],404);
    return response()->json(['ok'=>true]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::delete('/api/whatsappconfig/{id}', function ($id) {
    $aff = DB::table('WhatsAppConfig')->where('Id',$id)->delete();
    if (!$aff) return response()->json(['message'=>'Not found'],404);
    return response()->json(['ok'=>true]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/voice/{license}/vo/status', function ($license) {
    $lic = CustomerLicense::query()->where('license_key',$license)->first();
    if (!$lic) return response()->json(['message'=>'Not found'],404);
    return response()->json([
        'ok' => true,
        'license' => $license,
        'seconds_remaining' => (int)($lic->vo_seconds_remaining ?? 0),
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/customerlicense/{license}/vo/status', function ($license) {
    $lic = CustomerLicense::query()->where('license_key',$license)->first();
    if (!$lic) return response()->json(['message'=>'Not found'],404);
    return response()->json([
        'ok' => true,
        'license' => $license,
        'seconds_remaining' => (int)($lic->vo_seconds_remaining ?? 0),
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/api/customerlicense/{license}/voice-config', function ($license) {
    $lic = CustomerLicense::query()->where('license_key',$license)->first();
    if (!$lic) return response()->json(['ok'=>false,'message'=>'Not found'],404);
    $remain = (int)($lic->vo_seconds_remaining ?? 0);
    if ($remain <= 0) {
        return response()->json([
            'ok' => false,
            'license' => $license,
            'message' => 'Silakan topup dulu',
        ],402);
    }
    $base = DB::table('ConfigApiKey')
        ->whereRaw('LOWER(status) = ?', ['available'])
        ->whereNotNull('ApiKey')
        ->where('ApiKey','<>','');
    $prefer = (clone $base)
        ->whereRaw('LOWER(Model) = ?', ['gemini-2.5-flash-preview-tts'])
        ->orderByDesc('UpdatedAt')
        ->select(['JenisApiKey','ApiKey','DefaultVoiceId','Model'])
        ->get();
    if ($prefer->isEmpty()) {
        $prefer = (clone $base)
            ->whereRaw('LOWER(Model) = ?', ['gemini-2.5-pro-preview-tts'])
            ->orderByDesc('UpdatedAt')
            ->select(['JenisApiKey','ApiKey','DefaultVoiceId','Model'])
            ->get();
    }
    $rows = $prefer->isEmpty()
        ? (clone $base)->orderByDesc('UpdatedAt')->select(['JenisApiKey','ApiKey','DefaultVoiceId','Model'])->get()
        : $prefer;
    $items = $rows->map(function($x) use ($remain){
        return [
            'Provider' => $x->JenisApiKey,
            'ApiKey' => $x->ApiKey,
            'DefaultVoiceId' => $x->DefaultVoiceId,
            'Model' => $x->Model,
            'SecondsRemaining' => $remain,
        ];
    });
    return response()->json([
        'ok' => true,
        'license' => $license,
        'seconds_remaining' => $remain,
        'apikeys' => $items,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Debug: tulis log ke channel 'whatsapp' untuk memastikan file whatsapp.log dibuat
Route::get('/debug/whatsapp-log', function () {
    Log::channel('whatsapp')->info('Manual test log for WhatsApp channel', [
        'ts' => now()->toISOString(),
    ]);
    return response()->json(['ok' => true, 'message' => 'whatsapp.log should have a new entry']);
});

// Halaman Test WhatsApp: form sederhana untuk kirim pesan via Whapify dan tampilkan hasil + tail log
Route::match(['GET','POST'],'/test-whatsapp', function (Request $request) {
    $result = null;
    $phone = trim((string)($request->input('phone') ?? $request->input('recipient')));
    $message = (string)($request->input('message') ?? 'Halo, ini test kiriman WhatsApp dari halaman admin.');
    $overrideApiKey = $request->input('api_key');
    $overrideSecret = $request->input('secret');
    $overrideAccount = $request->input('account');
    $curlPreview = null;

    // Ambil config WhatsApp terbaru
    $cfg = DB::table('WhatsAppConfig')->orderByDesc('UpdatedAt')->orderByDesc('Id')->first();
    $secret = $overrideSecret ?: ($cfg->ApiSecret ?? null);
    $account = $overrideAccount ?: ($cfg->AccountUniqueId ?? null);

    $statusCfg = [
        'hasSecret' => !empty($secret),
        'hasAccount' => !empty($account),
        'hasApiKey' => false,
    ];

    if ($request->isMethod('post')) {
        try {
            // Normalisasi nomor
            $target = preg_replace('/\s+/', '', $phone);
            $target = preg_replace('/[^0-9+]/', '', $target);
            if (preg_match('/^0\d+$/', $target)) { $target = '62'.substr($target,1); }
            if (preg_match('/^\+62\d+$/', $target)) { $target = substr($target,1); }

            if (!$secret || !$account) {
                $result = ['ok'=>false,'status'=>0,'body'=>'Secret/Account Whapify tidak terisi (isi secret & account di form atau set di WhatsAppConfig).'];
            } elseif (!$target) {
                $result = ['ok'=>false,'status'=>0,'body'=>'Phone kosong atau tidak valid.'];
            } else {
                $maskedSecret = strlen((string)$secret) > 8 ? substr($secret,0,6).'•••'.substr($secret,-2) : '•••';
                Log::channel('whatsapp')->info('Test WA page: Attempting send via Whapify', [
                    'recipient'=>$target,
                    'account'=>$account,
                    'secret_masked'=>$maskedSecret,
                ]);
                // Bangun preview cURL (masking secret) untuk membantu debugging.
                // Kirim sebagai multipart persis seperti Postman/cURL: gunakan asMultipart()
                $curlPreview = 'curl -X POST "https://whapify.id/api/send/whatsapp" -H "Content-Type: multipart/form-data" -F "secret='.$maskedSecret.'" -F "account='.$account.'" -F "accountUniqueId='.$account.'" -F "recipient='.$target.'" -F "type=text" -F "message='.$message.'" -F "text='.$message.'"';
                $resp = \Illuminate\Support\Facades\Http::timeout(20)
                    ->asMultipart()
                    ->post('https://whapify.id/api/send/whatsapp', [
                        'secret' => $secret,
                        'account' => $account,
                        'accountUniqueId' => $account,
                        'recipient' => $target,
                        'type' => 'text',
                        'message' => $message,
                        'text' => $message,
                    ]);

                // Terkadang API mengembalikan HTTP 200 tetapi body JSON menyatakan gagal (status!=200/data=false).
                $bodyText = $resp->body();
                $json = null;
                try { $json = json_decode($bodyText, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { $json = null; }
                $logicalOk = $resp->ok();
                $logicalStatus = $resp->status();
                if (is_array($json)) {
                    if (isset($json['status']) && is_numeric($json['status'])) { $logicalStatus = (int)$json['status']; }
                    if (array_key_exists('data', $json)) { $logicalOk = $logicalOk && (bool)$json['data']; }
                }

                if ($logicalOk && $logicalStatus === 200) {
                    Log::channel('whatsapp')->info('Test WA page: Sent via Whapify', ['recipient'=>$target, 'http'=>$resp->status(), 'json_status'=>$logicalStatus]);
                    $result = ['ok'=>true,'status'=>200,'body'=>$bodyText];
                } else {
                    Log::channel('whatsapp')->warning('Test WA page: Failed via Whapify', [
                        'http'=>$resp->status(),
                        'json_status'=>is_array($json) ? ($json['status'] ?? null) : null,
                        'body'=>$bodyText,
                    ]);
                    // Retry dengan awalan '+' jika belum ada
                    if (strpos($target, '+') !== 0) {
                        $targetPlus = '+' . $target;
                        Log::channel('whatsapp')->info('Test WA page: Retry with plus prefix', ['recipient'=>$targetPlus]);
                        $resp2 = \Illuminate\Support\Facades\Http::timeout(20)
                            ->asMultipart()
                            ->post('https://whapify.id/api/send/whatsapp', [
                                'secret' => $secret,
                                'account' => $account,
                                'accountUniqueId' => $account,
                                'recipient' => $targetPlus,
                                'type' => 'text',
                                'message' => $message,
                                'text' => $message,
                            ]);
                        $bodyText2 = $resp2->body();
                        $json2 = null; try { $json2 = json_decode($bodyText2, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { $json2 = null; }
                        $logicalOk2 = $resp2->ok();
                        $logicalStatus2 = $resp2->status();
                        if (is_array($json2)) {
                            if (isset($json2['status']) && is_numeric($json2['status'])) { $logicalStatus2 = (int)$json2['status']; }
                            if (array_key_exists('data', $json2)) { $logicalOk2 = $logicalOk2 && (bool)$json2['data']; }
                        }
                        if ($logicalOk2 && $logicalStatus2 === 200) {
                            Log::channel('whatsapp')->info('Test WA page: Sent after retry', ['recipient'=>$targetPlus, 'http'=>$resp2->status(), 'json_status'=>$logicalStatus2]);
                            $result = ['ok'=>true,'status'=>200,'body'=>$bodyText2];
                            $curlPreview = 'curl -X POST "https://whapify.id/api/send/whatsapp" \\\n+  -H "Content-Type: multipart/form-data" \\\n+  -F "secret='.$maskedSecret.'" \\\n+  -F "account='.$account.'" \\\n+  -F "accountUniqueId='.$account.'" \\\n+  -F "recipient='.$targetPlus.'" \\\n+  -F "type=text" \\\n+  -F "message='.$message.'" \\\n+  -F "text='.$message.'"';
                        } else {
                            Log::channel('whatsapp')->warning('Test WA page: Retry failed', [
                                'http'=>$resp2->status(),
                                'json_status'=>is_array($json2) ? ($json2['status'] ?? null) : null,
                                'body'=>$bodyText2,
                            ]);
                            $result = ['ok'=>false,'status'=>$logicalStatus,'body'=>$bodyText];
                        }
                    } else {
                        $result = ['ok'=>false,'status'=>$logicalStatus,'body'=>$bodyText];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('Test WA page: Exception', ['error'=>$e->getMessage()]);
            $result = ['ok'=>false,'status'=>0,'body'=>'Exception: '.$e->getMessage()];
        }
    }

    // Ambil tail log whatsapp untuk ditampilkan di halaman
    $logTail = [];
    try {
        $path = storage_path('logs/whatsapp.log');
        if (file_exists($path)) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) { $logTail = array_slice($lines, -100); }
        }
    } catch (\Throwable $e) { /* abaikan */ }

    return view('testwhatsapp.index', [
        'result' => $result,
        'recipient' => $phone,
        'message' => $message,
        'statusCfg' => $statusCfg,
        'overrideSecret' => $overrideSecret,
        'overrideAccount' => $overrideAccount,
        'overrideApiKey' => null,
        'logTail' => $logTail,
        'curlPreview' => $curlPreview,
    ]);
})->middleware(['auth','role:admin']);

// CRUD License Activations
Route::get('/license-activations', function () {
    return view('activations.index');
})->middleware(['auth', 'role:admin']);

Route::get('/api/license-activations', function (Request $request) {
    $q = $request->query('q');
    $query = \App\Models\LicenseActivationsPlugin::query();
    
    if ($q) {
        $like = '%'.$q.'%';
        $query->where(function ($sub) use ($like) {
            $sub->where('product_name', 'like', $like)
                ->orWhere('device_id', 'like', $like)
                ->orWhere('license_id', 'like', $like);
        });
    }
    
    $rows = $query->orderByDesc('id')->limit(500)->get();
    return response()->json($rows);
})->middleware(['auth', 'role:admin']);

Route::get('/api/license-activations/{id}', function ($id) {
    $m = \App\Models\LicenseActivationsPlugin::find($id);
    if (!$m) return response()->json(['message'=>'Not found'], 404);
    return response()->json($m);
})->middleware(['auth', 'role:admin']);

Route::post('/api/license-activations', function (Request $request) {
    $data = $request->validate([
        'license_id' => 'required|integer',
        'device_id' => 'required|string|max:36',
        'product_name' => 'required|string|max:100',
        'activated_at' => 'nullable|date',
        'last_seen_at' => 'nullable|date',
        'revoked' => 'boolean',
    ]);
    
    try {
        $m = \App\Models\LicenseActivationsPlugin::create([
            'license_id' => $data['license_id'],
            'device_id' => $data['device_id'],
            'product_name' => $data['product_name'],
            'activated_at' => $data['activated_at'] ?? now(),
            'last_seen_at' => $data['last_seen_at'] ?? now(),
            'revoked' => $data['revoked'] ?? false,
        ]);
        return response()->json($m, 201);
    } catch (\Illuminate\Database\QueryException $e) {
        if ($e->errorInfo[1] == 1062) { // Duplicate entry
            return response()->json(['message' => 'Duplicate entry for license/device/product'], 409);
        }
        throw $e;
    }
})->middleware(['auth', 'role:admin']);

Route::put('/api/license-activations/{id}', function ($id, Request $request) {
    $m = \App\Models\LicenseActivationsPlugin::find($id);
    if (!$m) return response()->json(['message'=>'Not found'], 404);
    
    $data = $request->validate([
        'license_id' => 'integer',
        'device_id' => 'string|max:36',
        'product_name' => 'string|max:100',
        'activated_at' => 'nullable|date',
        'last_seen_at' => 'nullable|date',
        'revoked' => 'boolean',
    ]);
    
    try {
        $m->update(array_filter($data, function($v) { return !is_null($v); }));
        return response()->json($m);
    } catch (\Illuminate\Database\QueryException $e) {
        if ($e->errorInfo[1] == 1062) {
            return response()->json(['message' => 'Duplicate entry for license/device/product'], 409);
        }
        throw $e;
    }
})->middleware(['auth', 'role:admin']);

Route::delete('/api/license-activations/{id}', function ($id) {
    $m = \App\Models\LicenseActivationsPlugin::find($id);
    if (!$m) return response()->json(['message'=>'Not found'], 404);
    $m->delete();
    return response()->json(['ok' => true]);
})->middleware(['auth', 'role:admin']);

// API check_activation_plugin
Route::post('/api/check_activation_plugin', function (Request $request) {
    $licenseKey = $request->input('LicenseKey');
    $deviceId = $request->input('DeviceId');
    $productName = $request->input('ProductName');

    if (!$licenseKey || !$deviceId || !$productName) {
        return response()->json(['ok' => false, 'message' => 'Parameter tidak lengkap'], 400);
    }

    // 1. Cari data berdasarkan licensekey
    $license = \App\Models\CustomerLicense::where('license_key', $licenseKey)->first();
    if (!$license) {
        return response()->json(['ok' => false, 'message' => 'License tidak ditemukan'], 404);
    }

    // 2. Cek column ExpiresAt
    if ($license->expires_at_utc && \Illuminate\Support\Carbon::parse($license->expires_at_utc)->isPast()) {
        return response()->json(['ok' => false, 'message' => 'License MCM expired'], 403);
    }

    // 3. Cek apakah kombinasi sudah ada di log aktivasi
    $activation = \App\Models\LicenseActivationsPlugin::where('license_key', $licenseKey)
        ->where('device_id', $deviceId)
        ->where('product_name', $productName)
        ->first();

    if ($activation) {
        if ($activation->revoked) {
             return response()->json(['ok' => false, 'message' => 'Akses perangkat ini telah dicabut.'], 403);
        }
        // Update last_seen_at
        $activation->last_seen_at = now();
        $activation->save();
    } else {
        // Jika belum ada (aktivasi pertama kali untuk device ini), update license used_seats jadi 1
        if ($productName === 'shopee_video_scrap') {
            $license->used_seats_shopee_scrap = 1;
            $license->save();
        }

        // Insert device baru
        try {
            \App\Models\LicenseActivationsPlugin::create([
                'license_key' => $licenseKey,
                'device_id' => $deviceId,
                'product_name' => $productName,
                'activated_at' => now(),
                'last_seen_at' => now(),
                'revoked' => false
            ]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Gagal aktivasi: ' . $e->getMessage()], 500);
        }
    }

    return response()->json(['ok' => true, 'message' => 'Sukses']);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// CRUD Routes for License Activations Plugin (Admin Only)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/api/license-activations', function (Request $request) {
        $q = $request->input('q');
        $query = \App\Models\LicenseActivationsPlugin::query();
        if ($q) {
            $query->where('license_key', 'like', "%$q%")
                  ->orWhere('device_id', 'like', "%$q%")
                  ->orWhere('product_name', 'like', "%$q%");
        }
        return $query->orderBy('id', 'desc')->take(100)->get();
    });

    Route::get('/api/license-activations/{id}', function ($id) {
        return \App\Models\LicenseActivationsPlugin::findOrFail($id);
    });

    Route::post('/api/license-activations', function (Request $request) {
        $data = $request->validate([
            'license_key' => 'required|string',
            'device_id' => 'required|string|max:36',
            'product_name' => 'required|string|max:100',
            'revoked' => 'boolean',
            'activated_at' => 'nullable|date',
            'last_seen_at' => 'nullable|date',
        ]);
        
        // Defaults if not provided
        if (!isset($data['activated_at'])) $data['activated_at'] = now();
        if (!isset($data['last_seen_at'])) $data['last_seen_at'] = now();
        if (!isset($data['revoked'])) $data['revoked'] = false;

        return \App\Models\LicenseActivationsPlugin::create($data);
    });

    Route::put('/api/license-activations/{id}', function (Request $request, $id) {
        $m = \App\Models\LicenseActivationsPlugin::findOrFail($id);
        $data = $request->validate([
            'license_key' => 'string',
            'device_id' => 'string|max:36',
            'product_name' => 'string|max:100',
            'revoked' => 'boolean',
            'activated_at' => 'nullable|date',
            'last_seen_at' => 'nullable|date',
        ]);
        $m->update($data);
        return $m;
    });

    Route::delete('/api/license-activations/{id}', function ($id) {
        \App\Models\LicenseActivationsPlugin::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    });
});
