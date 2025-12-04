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
        // Optional signature verification (HMAC SHA256 of raw body with secret)
        $rawBody = $request->getContent();
        $secret = env('SCALEV_WEBHOOK_SECRET');
        $sigHeaders = [
            'X-ScaleV-Signature', 'X-Signature', 'X-Webhook-Signature',
            'X-Hub-Signature', 'X-Hub-Signature-256', 'ScaleV-Signature'
        ];
        $sigVal = null;
        foreach ($sigHeaders as $h) {
            $v = $request->header($h);
            if ($v) { $sigVal = $v; break; }
        }

        if (!empty($secret)) {
            // Accept several signature formats: raw hex/base64 HMAC, or prefixed "sha256="
            $hmacBin = hash_hmac('sha256', $rawBody, $secret, true);
            $hmacHex = hash_hmac('sha256', $rawBody, $secret);
            $hmacB64 = base64_encode($hmacBin);

            $candidate = trim((string)$sigVal);
            $candidateStripped = strtolower(str_replace([' ', '-', '\t'], '', $candidate));
            $prefixed = strtolower($candidate);

            $match = false;
            if ($candidate && (
                hash_equals($hmacHex, $candidateStripped) ||
                hash_equals($hmacB64, $candidate) ||
                (str_starts_with($prefixed, 'sha256=') && hash_equals($hmacHex, substr($prefixed, 7)))
            )) {
                $match = true;
            }

            // Also accept plain SHA256 of body for interoperability if provider uses digest, not HMAC
            if (!$match) {
                $shaHex = hash('sha256', $rawBody);
                $shaBin = hash('sha256', $rawBody, true);
                $shaB64 = base64_encode($shaBin);
                if (
                    hash_equals($shaHex, $candidateStripped) ||
                    hash_equals($shaB64, $candidate) ||
                    (str_starts_with($prefixed, 'sha256=') && hash_equals($shaHex, substr($prefixed, 7)))
                ) {
                    $match = true;
                }
            }

            if (!$match) {
                return response()->json(['ok' => false, 'error' => 'invalid_signature'], 401);
            }
        }

        // Basic payload validation
        $validator = Validator::make($request->all(), [
            'status_from' => 'required|string',
            'status_to'   => 'required|string',
            'name'        => 'required|string|max:255',
            'email'       => 'required|email',
            'phone'       => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $from = strtolower($request->input('status_from'));
        $to   = strtolower($request->input('status_to'));

        // Accept various not-paid labels
        $notPaidLabels = ['not_paid', 'unpaid', 'pending'];
        if (!in_array($from, $notPaidLabels, true) || $to !== 'paid') {
            return response()->json(['ok' => true, 'result' => 'ignored', 'reason' => 'no-paid-transition']);
        }

        // Create or update user
        $email = $request->input('email');
        $name  = $request->input('name');
        $phone = $request->input('phone');

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
        $orderId = $request->input('order_id')
            ?? $request->input('OrderId')
            ?? $request->input('orderId')
            ?? ('ORD-'.Str::upper(bin2hex(random_bytes(4))));
        $productName = $request->input('product_name')
            ?? $request->input('ProductName')
            ?? null;
        $variantPrice = $request->input('variant_price')
            ?? $request->input('VariantPrice')
            ?? null;
        $netRevenue = $request->input('net_revenue')
            ?? $request->input('NetRevenue')
            ?? null;
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
