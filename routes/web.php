<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\CustomerLicense;
use App\Models\VoiceOverTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Controllers\ScalevWebhookController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/licenses', function () {
    return view('licenses.index');
});

Route::get('/config-keys', function () {
    return view('configapikey.index');
});

Route::get('/orders', function () {
    return view('orderdata.index');
});

Route::get('/whatsapp-config', function () {
    return view('whatsappconfig.index');
});

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
        'MaxSeats' => $m->max_seats,
        'MaxVideo' => $m->max_video,
        'Features' => $m->features,
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
        'features' => $data['Features'] ?? null,
        'expires_at_utc' => isset($data['ExpiresAtUtc']) && $data['ExpiresAtUtc'] ? $data['ExpiresAtUtc'] : null,
        'is_activated' => isset($data['IsActivated']) ? (bool)$data['IsActivated'] : false,
        'machine_id' => $data['MachineId'] ?? null,
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
        'features' => $data['Features'] ?? $m->features,
        'status' => $data['Status'] ?? $m->status,
        'is_activated' => isset($data['IsActivated']) ? (bool)$data['IsActivated'] : $m->is_activated,
        'activation_date_utc' => isset($data['ActivationDateUtc']) && $data['ActivationDateUtc'] ? $data['ActivationDateUtc'] : $m->activation_date_utc,
        'expires_at_utc' => isset($data['ExpiresAtUtc']) && $data['ExpiresAtUtc'] ? $data['ExpiresAtUtc'] : $m->expires_at_utc,
        'machine_id' => $data['MachineId'] ?? $m->machine_id,
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
    $sig = trim((string)($request->input('Signature') ?? $request->input('signature') ?? $request->input('sig')));
    if (!$key || !$email || !$mid || !$sig) {
        return response()->json(['Success'=>false,'Message'=>'LicenseKey, Email, MachineId, dan Signature wajib diisi.','ErrorCode'=>'INVALID_REQUEST'],400);
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
    $expectedHex = hash('sha256', $key.'|'.$mid.'|'.$email);
    $expectedB64 = base64_encode(hash('sha256', $key.'|'.$mid.'|'.$email, true));
    if (!hash_equals($expectedHex, $sig) && !hash_equals($expectedB64, $sig)) {
        DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Activate','result'=>'Failed','message'=>'Signature tidak valid.','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['Success'=>false,'Message'=>'Signature tidak valid.','ErrorCode'=>'INVALID_SIGNATURE'],400);
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
    $lic->machine_id = $mid;
    $lic->activation_date_utc = now('UTC');
    $lic->is_activated = true;
    $lic->expires_at_utc = $expires;
    $lic->save();
    DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Activate','result'=>'Success','message'=>'License activated.','created_at'=>now(),'updated_at'=>now()]);
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
            'machineId' => $mid,
            'MachineId' => $mid,
            'LicenseKey' => $lic->license_key,
            'Email' => $lic->email,
            'email' => $lic->email,
            'ExpiresAt' => optional($lic->expires_at_utc)->toISOString(),
        ],
    ],200);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::match(['GET','POST'],'/api/license/reset', function (Request $request) {
    $key = trim((string)($request->input('LicenseKey') ?? $request->input('licenseKey') ?? $request->input('license') ?? $request->input('key')));
    $email = trim((string)($request->input('Email') ?? $request->input('email')));
    $sig = trim((string)($request->input('Signature') ?? $request->input('signature') ?? $request->input('sig')));
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
    $expectedHex = hash('sha256', $key.'|'.($lic->machine_id ?? '').'|'.$email);
    $expectedB64 = base64_encode(hash('sha256', $key.'|'.($lic->machine_id ?? '').'|'.$email, true));
    if (!hash_equals($expectedHex, $sig) && !hash_equals($expectedB64, $sig)) {
        DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Reset','result'=>'Failed','message'=>'Signature tidak valid.','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['Success'=>false,'Message'=>'Signature tidak valid.','ErrorCode'=>'INVALID_SIGNATURE'],400);
    }
    $lic->status = 'Reset';
    $lic->is_activated = false;
    $lic->activation_date_utc = null;
    $prevMid = $lic->machine_id;
    $lic->machine_id = null;
    $lic->save();
    DB::table('license_actions')->insert(['license_key'=>$key,'order_id'=>$lic->order_id,'email'=>$email,'action'=>'Reset','result'=>'Success','message'=>'License reset.','created_at'=>now(),'updated_at'=>now()]);
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
    if (array_key_exists('Model',$data)) $update['Model'] = $data['Model'];
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
        $expectedHex = hash('sha256', ($lic->license_key ?? '').'|'.($lic->machine_id ?? '').'|'.($email ?: $lic->email ?? ''));
        $expectedB64 = base64_encode(hash('sha256', ($lic->license_key ?? '').'|'.($lic->machine_id ?? '').'|'.($email ?: $lic->email ?? ''), true));
        if (!hash_equals($expectedHex, $sig) && !hash_equals($expectedB64, $sig)) return response()->json(['Success'=>false,'Message'=>'Signature tidak valid.','ErrorCode'=>'INVALID_SIGNATURE'],400);
    }
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
    $items = DB::table('ConfigApiKey')
        ->whereRaw('LOWER(status) = ?', ['available'])
        ->whereNotNull('ApiKey')
        ->where('ApiKey','<>','')
        ->orderByRaw("CASE WHEN LOWER(Model) = 'gemini-2.5-flash-preview-tts' THEN 0 WHEN LOWER(Model) = 'gemini-2.5-pro-preview-tts' THEN 1 ELSE 2 END")
        ->orderByDesc('UpdatedAt')
        ->select(['JenisApiKey','ApiKey','DefaultVoiceId','Model'])
        ->get()
        ->map(function($x) use ($remain){
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

Route::get('/api/whatsappconfig', function (Request $request) {
    $q = $request->query('q');
    $rows = DB::table('WhatsAppConfig')
        ->when($q, function($qr) use ($q){
            $like = '%'.$q.'%';
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
    foreach (['ApiSecret','AccountUniqueId'] as $f) {
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
    $items = DB::table('ConfigApiKey')
        ->whereRaw('LOWER(status) = ?', ['available'])
        ->whereNotNull('ApiKey')
        ->where('ApiKey','<>','')
        ->orderByRaw("CASE WHEN LOWER(Model) = 'gemini-2.5-flash-preview-tts' THEN 0 WHEN LOWER(Model) = 'gemini-2.5-pro-preview-tts' THEN 1 ELSE 2 END")
        ->orderByDesc('UpdatedAt')
        ->select(['JenisApiKey','ApiKey','DefaultVoiceId','Model'])
        ->get()
        ->map(function($x) use ($remain){
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
