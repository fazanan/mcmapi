<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\CustomerLicense;
use App\Models\VoiceOverTransaction;
use Illuminate\Support\Str;

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
                        if (!empty($data['pg_payment_info']['description'])) {
                            $productName = (string)$data['pg_payment_info']['description'];
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
                                'edition' => null,
                                'payment_status' => 'paid',
                                'product_name' => $productName,
                                'tenor_days' => null,
                                'is_activated' => false,
                                'activation_date_utc' => null,
                                'expires_at_utc' => null,
                                'machine_id' => null,
                                'max_seats' => null,
                                'max_video' => null,
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
                                'status' => $lic->status ?: 'InActive',
                            ]);
                            $lic->save();
                        }

                        $topup = 100000; // seconds
                        $lic->vo_seconds_remaining = (int)($lic->vo_seconds_remaining ?? 0) + (int)$topup;
                        $lic->save();
                        VoiceOverTransaction::create(['license_id'=>$lic->id,'type'=>'topup','seconds'=>$topup]);
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
