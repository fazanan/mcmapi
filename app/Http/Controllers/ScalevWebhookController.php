<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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
            }
        }

        return response()->json(['message' => 'OK']);
    }
}
