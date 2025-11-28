<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\CustomerLicense;
use App\Models\VoiceOverTransaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ScalevWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret     = env('SCALEV_WEBHOOK_SECRET');
        $rawBody    = $request->getContent();

        // Signature yang dikirim SCALEV
        $receivedSignature = $request->header('X-Scalev-Hmac-Sha256');

        if (!$receivedSignature) {
            return response()->json(['message' => 'Missing signature header'], 400);
        }

        // SIGNATURE sesuai dokumentasi
        // BASE64( HMAC_SHA256( RAW_BODY , SECRET ) )
        $calculatedSignature = base64_encode(
            hash_hmac('sha256', $rawBody, $secret, true)
        );

        // DEBUG
        Log::info('SCALEV SIGNATURE CHECK', [
            'raw_body' => $rawBody,
            'received_signature' => $receivedSignature,
            'calculated_signature' => $calculatedSignature
        ]);

        // Cocokkan signature
        if (!hash_equals($calculatedSignature, $receivedSignature)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Jika valid → proses event
        $json = json_decode($rawBody, true);
        $event = $json['event'] ?? null;

        if ($event === "business.test_event") {
            return response()->json(['message' => 'Test event OK']);
        }

        if ($event === "order.created") {
            Log::info("ORDER CREATED RECEIVED", $json);
            $data = $json['data'] ?? [];
            $orderId = $data['order_id'] ?? null;
            $email = $data['destination_address']['email'] ?? ($data['customer']['email'] ?? null);
            $phone = $data['destination_address']['phone'] ?? ($data['customer']['phone'] ?? null);
            $name = $data['destination_address']['name'] ?? ($data['customer']['name'] ?? null);
            $productName = ($data['orderlines'][0]['product_name'] ?? null);
            $variantPrice = isset($data['orderlines'][0]['variant_price']) ? (float)$data['orderlines'][0]['variant_price'] : null;
            $netRevenue = isset($data['net_revenue']) ? (float)$data['net_revenue'] : null;
            $createdAt = $data['created_at'] ?? null;
            $createdDb = null;
            if ($createdAt) {
                try { $createdDb = Carbon::parse($createdAt)->setTimezone('UTC')->format('Y-m-d H:i:s'); } catch (\Throwable $e) { $createdDb = null; }
            }
            if ($orderId) {
                try {
                    DB::table('OrderData')->updateOrInsert(
                        ['OrderId' => $orderId],
                        [
                            'Email' => $email,
                            'Phone' => $phone,
                            'Name' => $name,
                            'ProductName' => $productName,
                            'VariantPrice' => $variantPrice,
                            'NetRevenue' => $netRevenue,
                            'Status' => 'Not Paid',
                            'CreatedAt' => $createdDb ?? now('UTC'),
                            'UpdatedAt' => now('UTC'),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('OrderData upsert failed: '.$e->getMessage());
                }
            }
        }

        if ($event === "order.payment_status_changed") {
            $data = $json['data'] ?? [];
            $orderId = $data['order_id'] ?? null;
            $paymentStatus = strtolower((string)($data['payment_status'] ?? ''));
            if ($orderId) {
                try {
                    if (in_array($paymentStatus, ['paid','settled'])) {
                        DB::table('OrderData')->updateOrInsert(
                            ['OrderId'=>$orderId],
                            [
                                'Status' => 'Paid',
                                'UpdatedAt' => now('UTC'),
                            ]
                        );

                        $email = $data['customer']['email'] ?? null;
                        $phone = $data['customer']['phone'] ?? null;
                        $name = $data['customer']['name'] ?? null;
                        $productName = null;
                        $od = DB::table('OrderData')->where('OrderId',$orderId)->first();
                        if ($od && !empty($od->ProductName)) {
                            $productName = (string)$od->ProductName;
                        } else if (!empty($data['orderlines'][0]['product_name'])) {
                            $productName = (string)$data['orderlines'][0]['product_name'];
                        } else if (!empty($data['pg_payment_info']['description'])) {
                            $productName = (string)$data['pg_payment_info']['description'];
                        }
                        $edition = null;
                        $tenorDays = null;
                        if ($productName) {
                            $parts = explode('-', $productName);
                            if (count($parts) >= 3) {
                                $edition = trim($parts[1]);
                                $m = [];
                                if (preg_match('/Akses\s+(\d+)\s+bulan/i', $productName, $m)) {
                                    $tenorDays = ((int)$m[1]) * 30;
                                }
                            } else {
                                $m = [];
                                if (preg_match('/Akses\s+(\d+)\s+bulan/i', $productName, $m)) {
                                    $tenorDays = ((int)$m[1]) * 30;
                                }
                            }
                        }
                        $lic = CustomerLicense::query()->where('order_id',$orderId)->first();
                        if (!$lic) {
                            $licenseKey = 'LIC-'.strtoupper(bin2hex(random_bytes(4)));
                            $lic = CustomerLicense::create([
                                'order_id' => $orderId,
                                'license_key' => $licenseKey,
                                'owner' => $name,
                                'email' => $email,
                                'phone' => $phone,
                                'edition' => $edition,
                                'payment_status' => 'paid',
                                'product_name' => $productName,
                                'tenor_days' => $tenorDays,
                                'is_activated' => false,
                                'activation_date_utc' => null,
                                'expires_at_utc' => null,
                                'machine_id' => null,
                                'max_seats' => 1,
                                'max_video' => 232331,
                                'features' => null,
                                'vo_seconds_remaining' => 0,
                                'status' => 'InActive',
                            ]);
                        } else {
                            $lic->fill([
                                'owner' => $name ?? $lic->owner,
                                'email' => $email ?? $lic->email,
                                'phone' => $phone ?? $lic->phone,
                                'payment_status' => 'paid',
                                'product_name' => $productName ?? $lic->product_name,
                                'edition' => $edition ?? $lic->edition,
                                'tenor_days' => $tenorDays ?? $lic->tenor_days,
                                'max_seats' => 1,
                                'max_video' => 232331,
                                'status' => $lic->status ?: 'InActive',
                            ]);
                            $lic->save();
                        }

                        $topup = 100000; // seconds
                        $lic->vo_seconds_remaining = (int)($lic->vo_seconds_remaining ?? 0) + (int)$topup;
                        $lic->save();
                        VoiceOverTransaction::create(['license_id'=>$lic->id,'type'=>'topup','seconds'=>$topup]);

                        $cfg = DB::table('WhatsAppConfig')->orderByDesc('UpdatedAt')->first();
                        if ($cfg && $phone) {
                            $months = null;
                            if (!empty($lic->tenor_days) && $lic->tenor_days > 0) {
                                $months = (int) round($lic->tenor_days / 30);
                            }
                            $monthsText = $months ? ($months.' bulan') : 'Akses 6 bulan';
                            $msg = "Halo kak! Berikut license MCM kakak 🎉\n\n".
                                   "Nama: ".$lic->owner."\n".
                                   "Email: ".$lic->email."\n".
                                   "License: ".$lic->license_key."\n".
                                   "Masa berlaku: ".$monthsText."\n".
                                   "Link installer: https://drive.google.com/file/d/1bP08SYrPoGfY82smV1laIarT2Io-azkH/view?usp=sharing\n".
                                   "Group Whatsapp: https://chat.whatsapp.com/JKa0ASoWRl80oTkt0cVlyd\n\n".
                                   "Kalau butuh bantuan instalasi, tinggal chat ya. Siap bantu! 🚀";
                            try {
                                $resp = Http::withOptions(['multipart' => [
                                    ['name'=>'secret','contents'=>$cfg->ApiSecret],
                                    ['name'=>'account','contents'=>$cfg->AccountUniqueId],
                                    ['name'=>'recipient','contents'=>$phone],
                                    ['name'=>'type','contents'=>'text'],
                                    ['name'=>'message','contents'=>$msg],
                                ]])->post('https://whapify.id/api/send/whatsapp');
                                $code = (int) $resp->status();
                                $j = null; try { $j = $resp->json(); } catch (\Throwable $__) { $j = null; }
                                $ok = $code >= 200 && $code < 300 && ((int)($j['status'] ?? $code) === 200);
                                if (Schema::hasColumn('customer_licenses','delivery_status')) {
                                    $lic->delivery_status = $ok ? 'Terkirim' : 'Gagal';
                                }
                                if (Schema::hasColumn('customer_licenses','delivery_log')) {
                                    $msgText = is_array($j) ? ($j['message'] ?? null) : null;
                                    $lic->delivery_log = $ok ? null : ('[HTTP '.$code.'] '.($msgText ?: ($resp->body() ?? '')));
                                }
                                $lic->save();
                            } catch (\Throwable $e) {
                                if (Schema::hasColumn('customer_licenses','delivery_status')) {
                                    $lic->delivery_status = 'Gagal';
                                }
                                if (Schema::hasColumn('customer_licenses','delivery_log')) {
                                    $lic->delivery_log = 'Exception: '.$e->getMessage();
                                }
                                $lic->save();
                                Log::error('Send WhatsApp failed: '.$e->getMessage());
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Payment status handler failed: '.$e->getMessage());
                }
            }
        }

        if ($event === "order.payment_status_changed") {
            $data = $json['data'] ?? [];
            $orderId = $data['order_id'] ?? null;
            $ps = strtolower((string)($data['payment_status'] ?? ''));
            $newStatus = ($ps === 'paid' || $ps === 'settled') ? 'Paid' : (($ps === 'unpaid') ? 'Not Paid' : ucfirst($ps));
            if ($orderId) {
                try {
                    DB::table('OrderData')->updateOrInsert(
                        ['OrderId' => $orderId],
                        [
                            'Status' => $newStatus,
                            'UpdatedAt' => now('UTC'),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('OrderData payment update failed: '.$e->getMessage());
                }
            }
        }

        return response()->json(['message' => 'OK']);
    }
}
