<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class ScalevWebhookController extends Controller
{
    /**
     * Handle webhook from ScaleV: when status changes from not paid to paid,
     * create (or update) a user with role 'member' and random password.
     */
    public function handle(Request $request)
    {
        // Follow ScaleV docs: verify X-Scalev-Hmac-Sha256 (base64 HMAC-SHA256 of raw JSON)
        $rawBody = $request->getContent();
        $secret = env('SCALEV_WEBHOOK_SECRET');
        $enforce = filter_var(env('SCALEV_WEBHOOK_ENFORCE', true), FILTER_VALIDATE_BOOLEAN);
        if ($enforce && !empty($secret)) {
            $sigVal = $request->header('X-Scalev-Hmac-Sha256')
                ?? $request->header('X-ScaleV-Hmac-Sha256'); // tolerate case variation
            if (!$sigVal) {
                return response()->json(['ok' => false, 'error' => 'missing_signature'], 401);
            }
            $calc = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
            if (!hash_equals($calc, trim($sigVal))) {
                return response()->json(['ok' => false, 'error' => 'invalid_signature'], 401);
            }
        }

        // Parse body according to ScaleV: { event, timestamp, data: {...} }
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'invalid_json'], 400);
        }
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? $payload; // tolerate direct data body
        if (!is_array($data)) { $data = []; }

        // Extract payment status transition
        $statusTo = strtolower((string)($data['payment_status'] ?? $data['status'] ?? ''));
        $statusFrom = null;
        if (!empty($data['payment_status_history']) && is_array($data['payment_status_history'])) {
            $hist = $data['payment_status_history'];
            if (count($hist) >= 1) { $statusFrom = strtolower((string)($hist[0]['status'] ?? '')); }
        } else {
            // Avoid undefined array key notice when 'unpaid_time' is absent
            $statusFrom = (isset($data['unpaid_time']) && !empty($data['unpaid_time'])) ? 'unpaid' : '';
        }

        $from = $statusFrom ?: 'unpaid';
        $to   = $statusTo ?: '';

        // Accept various not-paid labels
        $notPaidLabels = ['not_paid', 'unpaid', 'pending'];
        if (!in_array($from, $notPaidLabels, true) || $to !== 'paid') {
            return response()->json(['ok' => true, 'result' => 'ignored', 'reason' => 'no-paid-transition']);
        }

        // Create or update user
        // Extract customer info from destination_address or fallback
        $dest = $data['destination_address'] ?? [];
        $email = $dest['email'] ?? $data['customer_email'] ?? $data['email'] ?? null;
        $name  = $dest['name']  ?? $data['customer_name']  ?? $data['name']  ?? null;
        $phone = $dest['phone'] ?? $data['customer_phone'] ?? $data['phone'] ?? null;

        $user = User::where('email', $email)->first();
        $created = false;
        $plainPassword = null;

        if (!$user) {
            $created = true;
            $plainPassword = Str::random(12);
            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->phone = $phone;
            $user->role = 'member';
            $user->password = Hash::make($plainPassword);
            $user->save();

            Log::info('ScaleV webhook created member', [
                'email' => $email,
                // Do NOT log plaintext password in production. Shown here for handoff purposes only.
            ]);
        } else {
            // Ensure role and profile data are up to date
            $user->name = $name;
            $user->phone = $phone;
            $user->role = 'member';
            $user->save();
        }

        // Upsert OrderData for visibility in /orders page
        $orderId = $data['order_id']
            ?? $request->input('order_id')
            ?? $request->input('OrderId')
            ?? $request->input('orderId')
            ?? ('ORD-'.Str::upper(bin2hex(random_bytes(4))));
        // Product/name & price from final_variants/orderlines if present
        $productName = null;
        $variantPrice = null;
        if (!empty($data['final_variants']) && is_array($data['final_variants'])) {
            $keys = array_keys($data['final_variants']);
            if (!empty($keys)) { $productName = $keys[0]; }
        }
        if (!$productName && !empty($data['orderlines']) && is_array($data['orderlines'])) {
            $first = $data['orderlines'][0] ?? [];
            $productName = $first['product_name'] ?? $productName;
            $variantPrice = $first['variant_price'] ?? $variantPrice;
        }
        $variantPrice = $variantPrice ?? ($data['product_price'] ?? null);
        $netRevenue = $data['net_revenue'] ?? null;
        $statusText = $to === 'paid' ? 'Paid' : ($request->input('Status') ?? 'Not Paid');

        $now = Carbon::now();
        $exists = DB::table('OrderData')->where('OrderId', $orderId)->exists();
        if (!$exists) {
            DB::table('OrderData')->insert([
                'OrderId' => $orderId,
                'Email' => $email,
                'Phone' => $phone,
                'Name' => $name,
                'ProductName' => $productName,
                'VariantPrice' => $variantPrice,
                'NetRevenue' => $netRevenue,
                'Status' => $statusText,
                'CreatedAt' => $now,
                'UpdatedAt' => $now,
            ]);
        } else {
            DB::table('OrderData')->where('OrderId', $orderId)->update([
                'Email' => $email,
                'Phone' => $phone,
                'Name' => $name,
                'ProductName' => $productName,
                'VariantPrice' => $variantPrice,
                'NetRevenue' => $netRevenue,
                'Status' => $statusText,
                'UpdatedAt' => $now,
            ]);
        }

        return response()->json([
            'ok' => true,
            'result' => $created ? 'created' : 'updated',
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'order_id' => $orderId,
            // Return temporary password only when newly created
            'temporary_password' => $created ? $plainPassword : null,
        ], $created ? 201 : 200);
    }
}
