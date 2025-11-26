<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        }

        return response()->json(['message' => 'OK']);
    }
}
