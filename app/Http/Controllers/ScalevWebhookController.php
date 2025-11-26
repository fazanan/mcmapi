<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScalevWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $timestamp = $request->header('X-SCALEV-TIMESTAMP');
        $signature = $request->header('X-SCALEV-SIGNATURE');
        $secret = env('SCALEV_WEBHOOK_SECRET');
        if (!$timestamp || !$signature) {
            return response()->json(['message' => 'Missing signature headers'], 400);
        }
        $rawBody = $request->getContent();
        $signedPayload = $timestamp.'.'.$rawBody;
        $expectedSignature = hash_hmac('sha256', $signedPayload, (string)$secret);
        if (!hash_equals($expectedSignature, (string)$signature)) {
            Log::warning('Invalid Scalev signature');
            return response()->json(['message' => 'Invalid signature'], 400);
        }
        $event = $request->input('event');
        $data = $request->input('data');
        if ($event === 'business.test_event') {
            Log::info('Scalev Test Event Received');
            return response()->json(['message' => 'Test OK'], 200);
        }
        switch ($event) {
            case 'order.created':
                $this->handleOrderCreated($data);
                break;
            case 'order.payment_status_changed':
                $this->handlePaymentStatus($data);
                break;
            case 'order.status_changed':
                $this->handleOrderStatus($data);
                break;
            default:
                Log::info('Unhandled Scalev Event: '.$event);
                break;
        }
        return response()->json(['status' => 'ok'], 200);
    }

    protected function handleOrderCreated($data)
    {
        Log::info('Order Created Event', is_array($data) ? $data : ['data' => $data]);
        $orderId = $data['order_id'] ?? null;
        $fullname = $data['destination_address']['fullname'] ?? null;
        $phone = $data['destination_address']['phone'] ?? null;
        $address = $data['destination_address']['address'] ?? null;
        $total = $data['pricing']['grand_total'] ?? null;
        Log::info('Order saved', compact('orderId','fullname','phone','address','total'));
    }

    protected function handlePaymentStatus($data)
    {
        Log::info('Payment Status Changed', is_array($data) ? $data : ['data' => $data]);
        $orderId = $data['order_id'] ?? null;
        $paymentStatus = $data['payment_status'] ?? null;
        if ($paymentStatus === 'paid') {
        }
    }

    protected function handleOrderStatus($data)
    {
        Log::info('Order Status Changed', is_array($data) ? $data : ['data' => $data]);
        $orderId = $data['order_id'] ?? null;
        $status = $data['status'] ?? null;
    }
}