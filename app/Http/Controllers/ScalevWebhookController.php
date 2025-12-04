<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CustomerLicense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

        // Accept various not-paid labels and determine if we have a not-paid -> paid transition
        $notPaidLabels = ['not_paid', 'unpaid', 'pending'];
        $isPaidTransition = in_array($from, $notPaidLabels, true) && $to === 'paid';

        // Extract customer info from destination_address or fallback
        $dest = $data['destination_address'] ?? [];
        $email = $dest['email'] ?? $data['customer_email'] ?? $data['email'] ?? null;
        $name  = $dest['name']  ?? $data['customer_name']  ?? $data['name']  ?? null;
        $phone = $dest['phone'] ?? $data['customer_phone'] ?? $data['phone'] ?? null;

        // Normalize and safe defaults
        $email = $email ? strtolower(trim($email)) : null;
        $name = $name ? trim($name) : null;
        $phone = $phone ? trim($phone) : null;

        $user = null;
        $created = false;
        $plainPassword = null;

        if ($isPaidTransition) {
            // Create or update user only when transitioning to paid
            if ($email !== null && $email !== '') {
                $user = User::where('email', $email)->first();
                if (!$user) {
                    $created = true;
                    $plainPassword = Str::random(12);
                    $user = new User();
                    // Fallback name if missing: use local part of email or 'Member'
                    if (!$name) {
                        $local = strstr($email, '@', true);
                        $name = $local ?: 'Member';
                    }
                    $user->name = $name ?: (strstr($email, '@', true) ?: 'Member');
                    $user->email = $email;
                    $user->phone = $phone;
                    $user->role = 'member';
                    $user->password = Hash::make($plainPassword);
                    $user->save();

                    Log::info('ScaleV webhook created member', [
                        'email' => $email,
                        // Do NOT log plaintext password in production.
                    ]);
                } else {
                    // Ensure role and profile data are up to date; also fallback name if currently null
                    if (!$name) {
                        $local = strstr($email, '@', true);
                        $name = $local ?: ($user->name ?: 'Member');
                    }
                    $user->name = $name ?: ($user->name ?: (strstr($email, '@', true) ?: 'Member'));
                    $user->phone = $phone;
                    $user->role = 'member';
                    $user->save();
                }
            } else {
                // Missing email: cannot create user, but order will still be logged below
                Log::warning('ScaleV paid event missing email; skipping user creation', [
                    'order_id' => $data['order_id'] ?? null,
                ]);
            }
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
            // Saat status sudah menjadi paid, jangan kosongkan kolom lain: hanya update Status dan UpdatedAt
            if ($to === 'paid') {
                DB::table('OrderData')->where('OrderId', $orderId)->update([
                    'Status' => $statusText,
                    'UpdatedAt' => $now,
                ]);
            } else {
                // Untuk event non-paid, update hanya kolom yang ada nilainya agar tidak menimpa dengan null
                $update = [
                    'Status' => $statusText,
                    'UpdatedAt' => $now,
                ];
                if ($email !== null) { $update['Email'] = $email; }
                if ($phone !== null) { $update['Phone'] = $phone; }
                if ($name !== null) { $update['Name'] = $name; }
                if ($productName !== null) { $update['ProductName'] = $productName; }
                if ($variantPrice !== null) { $update['VariantPrice'] = $variantPrice; }
                if ($netRevenue !== null) { $update['NetRevenue'] = $netRevenue; }
                DB::table('OrderData')->where('OrderId', $orderId)->update($update);
            }
        }

        if (!$isPaidTransition) {
            // For non-paid events, still log/save order row for visibility
            return response()->json([
                'ok' => true,
                'result' => 'logged',
                'order_id' => $orderId,
                'status' => $statusText,
            ], 200);
        }

        // Paid transition: generate or update license tied to this order
        // Ambil data order dari tabel OrderData agar konsisten (Owner/Email/Phone/Product)
        $orderRow = DB::table('OrderData')->where('OrderId', $orderId)->first();
        $orderEmail = $orderRow->Email ?? null;
        $orderName  = $orderRow->Name ?? null;
        $orderPhone = $orderRow->Phone ?? null;
        $productFromOrder = $orderRow->ProductName ?? null;

        // Gunakan Product dari tabel OrderData sebagai sumber utama parsing
        $edition = null;
        $rawProduct = (string)($productFromOrder ?? $productName ?? '');
        if (preg_match('/\s-\s*([^\-\)]+?)\s-\s*/u', $rawProduct, $m)) {
            $edition = trim($m[1]);
        }
        // Fallback bila parsing gagal: gunakan heuristik lama
        if (!$edition) {
            $pnameLc = strtolower($rawProduct);
            if (str_contains($pnameLc, 'pro')) { $edition = 'Pro'; }
            elseif (str_contains($pnameLc, 'basic') || str_contains($pnameLc, 'lite')) { $edition = 'Basic'; }
            else { $edition = (string)($data['edition'] ?? 'Basic'); }
        }

        // 2) Hitung tenor hari dari pola "Akses X bulan" => X*30 hari
        $validityDays = 180;
        if (preg_match('/Akses\s+(\d+)\s+bulan/i', $rawProduct, $tm)) {
            $months = (int)$tm[1];
            if ($months > 0) { $validityDays = $months * 30; }
        }
        $expires = now('UTC')->addDays((int)$validityDays);
        $featuresInput = $data['features'] ?? null;
        $featuresJson = is_array($featuresInput) ? json_encode($featuresInput) : ($featuresInput ?: json_encode(['Batch','TextOverlay']));

        $lic = CustomerLicense::query()->where('order_id', $orderId)->first();
        if (!$lic) {
            // License format: MCM-(kombinasi angka dan huruf random) 8 karakter
            $newKey = 'MCM-' . Str::upper(Str::random(8));
            // Owner/Email/Phone ambil dari OrderData berdasarkan OrderId; fallback ke payload bila kosong
            $ownerSourceEmail = $orderEmail ?? $email;
            $ownerName = $orderName ?: ($ownerSourceEmail ? (strstr($ownerSourceEmail, '@', true) ?: 'Member') : 'Member');
            $finalEmail = $orderEmail ?? $email;
            $finalPhone = $orderPhone ?? $phone;
            $finalProduct = $productFromOrder ?? $productName;
            $lic = CustomerLicense::create([
                'order_id' => $orderId,
                'license_key' => $newKey,
                'owner' => $ownerName,
                'email' => $finalEmail,
                'phone' => $finalPhone,
                'edition' => $edition,
                'payment_status' => 'paid',
                'product_name' => $finalProduct,
                'tenor_days' => $validityDays,
                'expires_at_utc' => $expires,
                'max_seats' => 1,
                'features' => $featuresJson,
                'max_video' => 2147483647,
                'vo_seconds_remaining' => 100000,
                'status' => 'InActive',
            ]);
            DB::table('license_actions')->insert([
                'license_key' => $lic->license_key,
                'order_id' => $lic->order_id,
                'email' => $finalEmail,
                'action' => 'Generate',
                'result' => 'Success',
                'message' => 'License '.$edition.' generated for paid order.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $updates = [
                // Prefer data dari OrderData; fallback ke payload
                'owner' => ($orderName ?: $name) ?: ($lic->owner ?: (($orderEmail ?? $email) ? (strstr(($orderEmail ?? $email), '@', true) ?: 'Member') : 'Member')),
                'email' => ($orderEmail ?? $email) ?? $lic->email,
                'phone' => ($orderPhone ?? $phone) ?? $lic->phone,
                'edition' => $lic->edition ?: $edition,
                'payment_status' => 'paid',
                'product_name' => ($productFromOrder ?? $productName) ?? $lic->product_name,
                'tenor_days' => $lic->tenor_days ?: $validityDays,
                'features' => $lic->features ?: $featuresJson,
                'status' => $lic->status ?: 'InActive',
            ];
            // Only set license key and expires if currently empty
            if (empty($lic->license_key)) { $updates['license_key'] = 'MCM-' . Str::upper(Str::random(8)); }
            if (empty($lic->expires_at_utc)) { $updates['expires_at_utc'] = $expires; }
            if (empty($lic->max_seats)) { $updates['max_seats'] = 1; }
            if (empty($lic->max_video)) { $updates['max_video'] = 2147483647; }
            $lic->fill($updates);
            $lic->save();
        }

        // Pastikan user dibuat/setelah license di-generate: ambil dari OrderData
        $targetEmail = $orderEmail ?? $email;
        $targetPhone = $orderPhone ?? $phone;
        $targetName = ($orderName ?: $name) ?: null;
        if ($targetEmail) {
            $existing = User::where('email', $targetEmail)->first();
            if (!$existing) {
                // Password acak 8 digit angka
                $plainPassword = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
                $safeName = $targetName ?: (strstr($targetEmail, '@', true) ?: 'Member');
                $user = new User();
                $user->name = $safeName;
                $user->email = $targetEmail;
                $user->phone = $targetPhone;
                $user->role = 'member';
                $user->password = Hash::make($plainPassword);
                $user->save();
                $created = true; // tandai bahwa user baru dibuat
            } else {
                // Update profil agar name tidak null dan role member
                $safeName = $targetName ?: ($existing->name ?: (strstr($targetEmail, '@', true) ?: 'Member'));
                $existing->name = $safeName;
                $existing->phone = $targetPhone ?? $existing->phone;
                $existing->role = 'member';
                $existing->save();
                $user = $existing;
            }
        }

        // If user is missing, still respond OK with license info
        if (!$user) {
            return response()->json([
                'ok' => true,
                'result' => 'logged_no_user',
                'reason' => 'missing_email',
                'order_id' => $orderId,
                'status' => $statusText,
                'license_key' => $lic->license_key,
                'edition' => $lic->edition,
            ], 200);
        }

        // Kirim informasi login via WhatsApp jika user baru dibuat dan nomor telepon tersedia
        try {
            if ($created && $user && !empty($user->phone) && !empty($plainPassword)) {
                $this->sendWhatsappLogin($user->phone, $user->name, $user->email, $plainPassword);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim WhatsApp info login', [
                'error' => $e->getMessage(),
                'user_email' => $user ? $user->email : null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'result' => $created ? 'created' : 'updated',
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'order_id' => $orderId,
            'license_key' => $lic->license_key,
            'edition' => $lic->edition,
            // Return temporary password only when newly created
            'temporary_password' => $created ? $plainPassword : null,
        ], $created ? 201 : 200);
    }

    /**
     * Mengirim informasi login ke nomor WhatsApp user via Whapify.
     * Menggunakan field: secret (ApiSecret), account (AccountUniqueId), recipient, type=text, message.
     * Jika konfigurasi tidak lengkap, hanya melakukan logging tanpa error fatal.
     */
    private function sendWhatsappLogin(string $phone, string $name, string $email, string $plainPassword): void
    {
        // Ambil konfigurasi WhatsApp terbaru
        $cfg = DB::table('WhatsAppConfig')
            ->orderByDesc('UpdatedAt')
            ->orderByDesc('Id')
            ->first();

        if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
            Log::info('WA login not sent: missing ApiSecret/AccountUniqueId in WhatsAppConfig');
            return;
        }

        // Normalisasi nomor telepon: hapus spasi/simbol, ganti awalan 0 -> 62
        $target = preg_replace('/\s+/', '', $phone);
        $target = preg_replace('/[^0-9+]/', '', $target);
        if (preg_match('/^0\d+$/', $target)) {
            $target = '62' . substr($target, 1);
        }
        if (!$target) {
            Log::info('WA login not sent: phone empty');
            return;
        }

        $appUrl = rtrim(env('APP_URL', url('/')), '/');
        $loginUrl = $appUrl . '/login';
        $message = "Halo {$name}, akun Anda sudah dibuat.\n\n".
            "Email: {$email}\n".
            "Password: {$plainPassword}\n".
            "Login: {$loginUrl}\n\n".
            "Simpan password ini dan segera login.";

        // Kirim via Whapify API (https://whapify.id/api/send/whatsapp)
        try {
            Log::info('Attempting WA login via Whapify', ['phone' => $target, 'email' => $email]);

            // Whapify membutuhkan multipart/form-data
            $multipart = [
                ['name' => 'secret', 'contents' => $cfg->ApiSecret],
                ['name' => 'account', 'contents' => $cfg->AccountUniqueId],
                ['name' => 'recipient', 'contents' => $target],
                ['name' => 'type', 'contents' => 'text'],
                ['name' => 'message', 'contents' => $message],
            ];

            $resp = Http::timeout(20)
                ->withOptions(['multipart' => $multipart])
                ->post('https://whapify.id/api/send/whatsapp');

            if ($resp->ok()) {
                Log::info('WA login sent via Whapify', ['phone' => $target, 'email' => $email]);
            } else {
                Log::warning('WA login send failed via Whapify', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WA login exception via Whapify', ['error' => $e->getMessage()]);
        }
    }
}
