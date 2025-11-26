<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScalevWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret     = env('SCALEV_WEBHOOK_SECRET');
        $timestamp  = $request->header('X-SCALEV-TIMESTAMP');
        $signature  = $request->header('X-SCALEV-SIGNATURE');
        $rawBody    = $request->getContent();

        // Cek header lengkap
        if (!$timestamp || !$signature) {
            return response()->json(['message' => 'Missing signature headers'], 400);
        }

        // Format sesuai dokumentasi SCALEV:
        // HMAC_SHA256( timestamp + "." + body , secret ) → lalu BASE64.encode(raw)
    $expectedSignature = base64_encode(
    hash_hmac('sha256', $rawBody, $secret, true)
    );


        // Debug log — bisa kamu hapus nanti
        Log::info("SCALEV SIGNATURE DEBUG", [
            'raw_body'          => $rawBody,
            'timestamp_header'  => $timestamp,
            'received_signature'=> $signature,
            'calculated_signature' => $expectedSignature,
        ]);

        // Cocokkan signature
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Signature benar → proses event
        $json = json_decode($rawBody, true);
        $event = $json['event'] ?? null;

        if ($event === "business.test_event") {
            return response()->json(['message' => 'Test event OK']);
        }

        if ($event === "order.created") {
            // Lanjutkan proses order di sini...
            Log::info("ORDER CREATED RECEIVED", $json);
        }

        

        return response()->json(['message' => 'OK']);
    }

    public function dump(Request $request)
    {
        return response()->json([
            'raw' => $request->getContent(),
            'hmac' => base64_encode(hash_hmac("sha256", $request->getContent(), env("SCALEV_WEBHOOK_SECRET"), true)),
        ]);
    }

}
