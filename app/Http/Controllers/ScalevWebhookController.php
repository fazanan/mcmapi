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
use Illuminate\Support\Facades\Mail;

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
        $secret = 'xaqE3BwP3VWzYj8lfVVWEu4GDylPyRGR';
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
            $count = count($hist);
            // Ambil status SEBELUM yang terbaru: index count-2 jika ada, kalau hanya satu entry gunakan entry tersebut
            if ($count >= 2) {
                $statusFrom = strtolower((string)($hist[$count - 2]['status'] ?? ''));
            } elseif ($count === 1) {
                $statusFrom = strtolower((string)($hist[0]['status'] ?? ''));
            }
        }
        // Fallback: jika masih kosong dan ada unpaid_time, anggap sebelumnya 'unpaid'
        if ($statusFrom === null || $statusFrom === '') {
            $statusFrom = (isset($data['unpaid_time']) && !empty($data['unpaid_time'])) ? 'unpaid' : '';
        }

        $from = $statusFrom ?: 'unpaid';
        $to   = $statusTo ?: '';

        // Accept various not-paid labels and determine if we have a not-paid -> paid transition
        $notPaidLabels = ['not_paid', 'unpaid', 'pending'];
        $isPaidTransition = in_array($from, $notPaidLabels, true) && $to === 'paid';
        Log::info('ScaleV payment transition', [
            'event' => $event,
            'from' => $from,
            'to' => $to,
            'isPaidTransition' => $isPaidTransition,
        ]);

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

        // Normalisasi nama produk: ubah "Akses Gratis" menjadi "Akses 3 Hari"
        if ($productName) {
            $productName = preg_replace('/Akses\s+Gratis/i', 'Akses 3 Hari', (string)$productName);
        }

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
                $updatePaid = [
                    'Status' => $statusText,
                    'UpdatedAt' => $now,
                ];
                if ($productName !== null) {
                    $updatePaid['ProductName'] = $productName;
                }
                DB::table('OrderData')->where('OrderId', $orderId)->update($updatePaid);
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
            // Untuk event non-paid, tetap log/save order row.

            // LOGIC KHUSUS: "Akses 3 Hari" pada saat order.created
            // REQUEST BARU: Akses 3 Hari HANYA dikirim saat status berubah ke PAID.
            // Saat order.created, kirim pesan standar seperti produk lain.
            
            if ($event === 'order.created' && !empty($phone)) {
                if ($productName && stripos($productName, 'LIHAT DEMO') !== false) {
                    $this->sendWhatsappLihatDemo($phone);
                } else {
                    // Normal behavior for ALL products (including Akses 3 Hari) -> Payment Reminder
                    $this->sendWhatsappOrderCreated(
                        $phone,
                        $name,
                        $productName,
                        $variantPrice ?? $netRevenue
                    );
                }
            }

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
        // Pastikan normalisasi juga diterapkan di sini
        if ($rawProduct !== '') {
            $rawProduct = preg_replace('/Akses\s+Gratis/i', 'Akses 3 Hari', $rawProduct);
        }
        $isMassVoProduct = stripos($rawProduct, 'MCM Mass Voice Over') !== false;
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

        // 2) Hitung tenor dari nama produk:
        //    - "Akses X hari"  => tenor = X hari
        //    - "Akses X bulan" => tenor = X*30 hari
        //    Default tetap 180 hari bila tidak ditemukan pola
        $validityDays = 180;
        if (preg_match('/Akses\s+(\d+)\s+hari/i', $rawProduct, $tmHari)) {
            $days = (int)$tmHari[1];
            if ($days > 0) { $validityDays = $days; }
        } elseif (preg_match('/Akses\s+(\d+)\s+bulan/i', $rawProduct, $tmBulan)) {
            $months = (int)$tmBulan[1];
            if ($months > 0) { $validityDays = $months * 30; }
        }
        $expires = now('UTC')->addDays((int)$validityDays);
        $featuresInput = $data['features'] ?? null;
        $featuresJson = is_array($featuresInput) ? json_encode($featuresInput) : ($featuresInput ?: json_encode(['Batch','TextOverlay']));

        $licenseTargetEmail = $orderEmail ?? $email;
        if ($isMassVoProduct && $licenseTargetEmail) {
            $lic = CustomerLicense::query()
                ->where('email', $licenseTargetEmail)
                ->where('payment_status', 'paid')
                ->where(function ($q) {
                    $q->where('product_name', 'like', '%MCM Mass Voice Over%');
                })
                ->orderByDesc('created_at')
                ->first();
        } else {
            $lic = CustomerLicense::query()->where('order_id', $orderId)->first();
        }

        if (!$lic) {
            // License format: MCM-(kombinasi angka dan huruf random) 8 karakter
            $newKey = 'MCM-' . Str::upper(Str::random(8));
            // Owner/Email/Phone ambil dari OrderData berdasarkan OrderId; fallback ke payload bila kosong
            $ownerSourceEmail = $orderEmail ?? $email;
            $ownerName = $orderName ?: ($ownerSourceEmail ? (strstr($ownerSourceEmail, '@', true) ?: 'Member') : 'Member');
            $finalEmail = $orderEmail ?? $email;
            $finalPhone = $orderPhone ?? $phone;
            $finalProduct = $rawProduct;
            $payload = [
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
            ];
            if ($isMassVoProduct) {
                $payload['massvoseat'] = 1;
            }
            $lic = CustomerLicense::create($payload);
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
            if ($isMassVoProduct) {
                if ($lic->massvoseat != 1) {
                    $lic->massvoseat = 1;
                }
                if ($lic->payment_status !== 'paid') {
                    $lic->payment_status = 'paid';
                }
                $lic->save();
            } else {
                $updates = [
                    // Prefer data dari OrderData; fallback ke payload
                    'owner' => ($orderName ?: $name) ?: ($lic->owner ?: (($orderEmail ?? $email) ? (strstr(($orderEmail ?? $email), '@', true) ?: 'Member') : 'Member')),
                    'email' => ($orderEmail ?? $email) ?? $lic->email,
                    'phone' => ($orderPhone ?? $phone) ?? $lic->phone,
                    'edition' => $lic->edition ?: $edition,
                    'payment_status' => 'paid',
                    'product_name' => ($rawProduct ?: $lic->product_name),
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
                $this->sendWhatsappLogin(
                    $user->phone,
                    $user->name,
                    $user->email,
                    $plainPassword,
                    $lic,
                    $orderRow,
                    $statusText
                );
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('Gagal mengirim WhatsApp info login', [
                'error' => $e->getMessage(),
                'user_email' => $user ? $user->email : null,
            ]);
        }

        // Selalu kirim email berisi informasi login & license setelah status paid
        try {
            $this->sendEmailLoginLicense($user, $created ? $plainPassword : null, $lic, $orderRow, $statusText);
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim email info login/license', [
                'error' => $e->getMessage(),
                'user_email' => $user ? $user->email : null,
            ]);
        }

        // Kirim WhatsApp berisi data license setelah payment status berubah (paid)
        try {
            if ($event === 'order.payment_status_changed' && $to === 'paid') {
                $this->sendWhatsappPaymentStatusChanged(
                    $orderId,
                    $orderPhone ?? $phone,
                    $orderName ?: $name ?: $user->name,
                    $orderEmail ?? $email ?? $user->email,
                    $lic->license_key ?? ''
                );
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('WA payment_status_changed exception', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
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
        ], 200);
    }







    /**
     * Mengirim informasi login ke nomor WhatsApp user via Whapify.
     * Menggunakan field: secret (ApiSecret), account (AccountUniqueId), recipient, type=text, message.
     * Jika konfigurasi tidak lengkap, hanya melakukan logging tanpa error fatal.
     */
    private function sendWhatsappLogin(string $phone, string $name, string $email, string $plainPassword, $lic, $orderRow, string $statusText): void
    {
        // Ambil detail license & pembelian untuk menentukan produk
        $licenseKey = $lic ? ($lic->license_key ?? '') : '';
        $productName = $orderRow ? ($orderRow->ProductName ?? '') : ($lic ? ($lic->product_name ?? '') : '');
        if ($productName) {
            $productName = preg_replace('/Akses\s+Gratis/i', 'Akses 3 Hari', (string)$productName);
        }

        // Tentukan Config ID berdasarkan nama produk
        $cfg = null;
        if ($productName && (stripos($productName, 'Akses Gratis') !== false || stripos($productName, 'Akses 3 Hari') !== false)) {
            $cfg = DB::table('WhatsAppConfig')->where('Id', 2)->first();
            if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
                $cfg = DB::table('WhatsAppConfig')
                    ->orderByDesc('UpdatedAt')
                    ->orderByDesc('Id')
                    ->first();
            }
        } else {
            // Untuk produk berbayar, gunakan Config ID=1
            $cfg = DB::table('WhatsAppConfig')->where('Id', 1)->first();
            if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
                $cfg = DB::table('WhatsAppConfig')
                    ->where('Id', '<>', 2)
                    ->orderByDesc('UpdatedAt')
                    ->first();
                if (!$cfg) {
                    $cfg = DB::table('WhatsAppConfig')->orderByDesc('UpdatedAt')->first();
                }
            }
        }

        if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
            Log::channel('whatsapp')->info('WA login not sent: missing ApiSecret/AccountUniqueId in WhatsAppConfig');
            return;
        }

        // Normalisasi nomor telepon: hapus spasi/simbol, ganti awalan 0 -> 62
        $target = preg_replace('/\s+/', '', $phone);
        $target = preg_replace('/[^0-9+]/', '', $target);
        if (preg_match('/^0\d+$/', $target)) {
            $target = '62' . substr($target, 1);
        }
        if (!$target) {
            Log::channel('whatsapp')->info('WA login not sent: phone empty');
            return;
        }

        $appUrl = rtrim(env('APP_URL', url('/')), '/');
        $loginUrl = $appUrl . '/login';
        $tenorDays = $lic ? ((int)($lic->tenor_days ?? 0)) : 0;
        $expiresAt = $lic && !empty($lic->expires_at_utc) ? Carbon::parse($lic->expires_at_utc)->setTimezone('Asia/Jakarta')->format('d-m-Y H:i') : null;
        $price = $orderRow ? ($orderRow->VariantPrice ?? null) : null;
        $installerVersion = $cfg ? ($cfg->InstallerVersion ?? null) : null;
        $installerLink = $cfg ? ($cfg->InstallerLink ?? null) : null;
        $groupLink = $cfg ? ($cfg->GroupLink ?? null) : null;

        $priceText = $price !== null ? $this->formatRupiah((float)$price) : '-';
        $statusLicense = $lic ? ($lic->status ?? 'InActive') : 'InActive';

        // Susun pesan WhatsApp lengkap
        $message = "Halo {$name}, akun Anda sudah dibuat.\n\n".
            "Email: {$email}\n".
            "Password: {$plainPassword}\n".
            "Login: {$loginUrl}\n\n".
            "Detail Pembelian:\n".
            "- Produk: {$productName}\n".
            "- License: {$licenseKey}\n".
            "- Tenor: {$tenorDays} hari".
            ($expiresAt ? " (berlaku sampai {$expiresAt} WIB)" : "") . "\n".
            "- Harga: {$priceText}\n".
            "- Status License: {$statusLicense}\n".
            ($installerVersion ? "- Installer Version: {$installerVersion}\n" : "") .
            ($installerLink ? "- Link Installer: {$installerLink}\n" : "") .
            ($groupLink ? "- Link Group: {$groupLink}\n" : "") .
            "\nSimpan password ini dan segera login.";

        // Kirim via Whapify API (https://whapify.id/api/send/whatsapp)
        try {
            Log::channel('whatsapp')->info('Attempting WA login via Whapify', ['phone' => $target, 'email' => $email]);

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
                Log::channel('whatsapp')->info('WA login sent via Whapify', ['phone' => $target, 'email' => $email]);
            } else {
                Log::channel('whatsapp')->warning('WA login send failed via Whapify', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('WA login exception via Whapify', ['error' => $e->getMessage()]);
        }
    }

    private function formatRupiah(float $amount): string
    {
        $formatted = number_format($amount, 0, ',', '.');
        return 'Rp ' . $formatted;
    }

    /**
     * Mengirim WhatsApp untuk event order.created agar user menyelesaikan pembayaran.
     * Menggunakan field: secret, account, recipient, type=text, message.
     */
    private function sendWhatsappOrderCreated(string $phone, ?string $name, ?string $productName, $price): void
    {
        // Ambil konfigurasi WhatsApp untuk produk berbayar (ID 1 atau selain 2)
        $cfg = DB::table('WhatsAppConfig')->where('Id', 1)->first();
        if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
            $cfg = DB::table('WhatsAppConfig')
                ->where('Id', '<>', 2)
                ->orderByDesc('UpdatedAt')
                ->first();
            
            // Fallback terakhir: ambil apa saja
            if (!$cfg) {
                $cfg = DB::table('WhatsAppConfig')
                    ->orderByDesc('UpdatedAt')
                    ->orderByDesc('Id')
                    ->first();
            }
        }

        if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
            Log::channel('whatsapp')->info('WA order.created not sent: missing ApiSecret/AccountUniqueId in WhatsAppConfig');
            return;
        }

        // Normalisasi nomor telepon: hapus spasi/simbol, ganti awalan 0 -> 62
        $target = preg_replace('/\s+/', '', $phone);
        $target = preg_replace('/[^0-9+]/', '', $target);
        if (preg_match('/^0\d+$/', $target)) {
            $target = '62' . substr($target, 1);
        }
        if (preg_match('/^\+62\d+$/', $target)) {
            $target = substr($target, 1);
        }
        if (!$target) {
            Log::channel('whatsapp')->info('WA order.created not sent: phone empty');
            return;
        }

        $safeName = $name ? $name : 'Kak';
        $prod = $productName ? $productName : 'Produk MCM';
        $priceText = is_numeric($price) ? $this->formatRupiah((float)$price) : '-';

        // Susun pesan sesuai template yang diminta
        $message = "Hai kak {$safeName}, 👋😊\n\n".
            "Pesanan {$prod} sudah berhasil kami terima.\n\n".
            "Supaya kakak bisa langsung pakai aplikasinya, silakan selesaikan pembayarannya ya.🙏\n\n".
            "Totalnya dari hanya ~897.000~ menjadi hanya {$priceText}.\n\n".
            "Sudah dapat semua Fitur MCM.🚀\n\n".
            "Ada yang ingin ditanyakan?\n".
            "Balas chat ini ya, kami siap bantu.😊\n\n".
            "Salam,\n".
            "*MCM Admin*";

        try {
            $maskedSecret = strlen((string)$cfg->ApiSecret) > 8
                ? substr($cfg->ApiSecret, 0, 6) . '•••' . substr($cfg->ApiSecret, -2)
                : '•••';
            Log::channel('whatsapp')->info('Attempting WA order.created via Whapify', [
                'phone' => $target,
                'product' => $prod,
                'price' => $priceText,
                'account' => $cfg->AccountUniqueId,
                'secret_masked' => $maskedSecret,
            ]);

            // Kirim sebagai multipart/form-data ke Whapify (identik dengan test page)
            $resp = Http::timeout(20)
                ->asMultipart()
                ->post('https://whapify.id/api/send/whatsapp', [
                    'secret' => $cfg->ApiSecret,
                    'account' => $cfg->AccountUniqueId,
                    'accountUniqueId' => $cfg->AccountUniqueId,
                    'recipient' => $target,
                    'type' => 'text',
                    'message' => $message,
                    'text' => $message,
                ]);

            // Evaluasi keberhasilan berdasarkan HTTP dan body JSON seperti di test page
            $bodyText = $resp->body();
            $json = null;
            try { $json = json_decode($bodyText, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { $json = null; }
            $logicalOk = $resp->ok();
            $logicalStatus = $resp->status();
            if (is_array($json)) {
                if (isset($json['status']) && is_numeric($json['status'])) { $logicalStatus = (int)$json['status']; }
                if (array_key_exists('data', $json)) { $logicalOk = $logicalOk && (bool)$json['data']; }
            }

            if ($logicalOk && $logicalStatus === 200) {
                Log::channel('whatsapp')->info('WA order.created sent via Whapify', [
                    'phone' => $target,
                    'http' => $resp->status(),
                    'account' => $cfg->AccountUniqueId,
                ]);
            } else {
                Log::channel('whatsapp')->warning('WA order.created send failed via Whapify', [
                    'status' => $resp->status(),
                    'json_status' => is_array($json) ? ($json['status'] ?? null) : null,
                    'body' => $bodyText,
                    'account' => $cfg->AccountUniqueId,
                ]);

                // Retry dengan awalan '+' jika belum ada
                if (strpos($target, '+') !== 0) {
                    $targetPlus = '+' . $target;
                    $resp2 = Http::timeout(20)
                        ->asMultipart()
                        ->post('https://whapify.id/api/send/whatsapp', [
                            'secret' => $cfg->ApiSecret,
                            'account' => $cfg->AccountUniqueId,
                            'accountUniqueId' => $cfg->AccountUniqueId,
                            'recipient' => $targetPlus,
                            'type' => 'text',
                            'message' => $message,
                            'text' => $message,
                        ]);
                    if ($resp2->successful()) {
                        Log::channel('whatsapp')->info('WA order.created sent via Whapify after + retry', [
                            'phone' => $targetPlus,
                            'http' => $resp2->status(),
                            'account' => $cfg->AccountUniqueId,
                        ]);
                    } else {
                        Log::channel('whatsapp')->warning('WA order.created failed after + retry via Whapify', [
                            'status' => $resp2->status(),
                            'body' => $resp2->body(),
                            'account' => $cfg->AccountUniqueId,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('WA order.created exception via Whapify', [
                'error' => $e->getMessage(),
                'account' => $cfg->AccountUniqueId ?? null,
            ]);
        }
    }

    /**
     * Kirim WhatsApp berisi informasi license ketika payment status berubah (paid).
     * Format pesan:
     * Data License Mesin Cuan Maximal
     * Nama: {name}
     * Email: {email}
     * License: {license}
     * Version: {InstallerVersion}
     * Link Installer:
     * {InstallerLink}
     * Untuk tutorial ada digroup telegram ya kak silakan bergabung
     * {GroupLink}
     * Terimakasih
     */
    private function sendWhatsappPaymentStatusChanged(string $orderId, ?string $phone, string $name, string $email, string $licenseKey): void
    {
        $cfg = null;
        $productNameCandidate = null;
        $rowForProduct = DB::table('OrderData')->where('OrderId', $orderId)->first();
        if ($rowForProduct && !empty($rowForProduct->ProductName)) {
            $productNameCandidate = (string)$rowForProduct->ProductName;
        }
        if (!$productNameCandidate && !empty($licenseKey)) {
            $licRowTmp = \App\Models\CustomerLicense::where('license_key', $licenseKey)->first();
            if ($licRowTmp && !empty($licRowTmp->product_name)) {
                $productNameCandidate = (string)$licRowTmp->product_name;
            }
        }
        if ($productNameCandidate && stripos($productNameCandidate, 'MCM Mass Voice Over') !== false) {
            $cfg = DB::table('WhatsAppConfig')->where('Id', 3)->first();
            if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
                $cfg = DB::table('WhatsAppConfig')->where('Id', 1)->first();
                if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
                    $cfg = DB::table('WhatsAppConfig')
                        ->where('Id', '<>', 2)
                        ->orderByDesc('UpdatedAt')
                        ->first();
                    if (!$cfg) {
                        $cfg = DB::table('WhatsAppConfig')->orderByDesc('UpdatedAt')->first();
                    }
                }
            }
        } elseif ($productNameCandidate && (stripos($productNameCandidate, 'Akses Gratis') !== false || stripos($productNameCandidate, 'Akses 3 Hari') !== false)) {
            $cfg = DB::table('WhatsAppConfig')->where('Id', 2)->first();
            if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
                $cfg = DB::table('WhatsAppConfig')
                    ->orderByDesc('UpdatedAt')
                    ->orderByDesc('Id')
                    ->first();
            }
        } else {
            // Untuk produk berbayar (Akses 3 Bulan, 6 Bulan, 1 Tahun, Lifetime), gunakan Config ID=1
            $cfg = DB::table('WhatsAppConfig')->where('Id', 1)->first();
            // Fallback bila ID=1 tidak ditemukan/rusak: cari yang bukan ID=2, atau ambil terbaru
            if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
                $cfg = DB::table('WhatsAppConfig')
                    ->where('Id', '<>', 2)
                    ->orderByDesc('UpdatedAt')
                    ->first();
                // Jika masih kosong, ambil apa saja yang ada
                if (!$cfg) {
                    $cfg = DB::table('WhatsAppConfig')->orderByDesc('UpdatedAt')->first();
                }
            }
        }

        if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
            Log::channel('whatsapp')->info('WA payment_status_changed not sent: missing ApiSecret/AccountUniqueId in WhatsAppConfig');
            return;
        }

        // Logging awal untuk membantu diagnosa
        Log::channel('whatsapp')->info('WA payment_status_changed: initial input', [
            'order_id' => $orderId,
            'phone_payload' => $phone,
            'email' => $email,
        ]);

        // Jika phone kosong, coba ambil dari tabel OrderData berdasarkan OrderId
        if (empty($phone)) {
            $row = DB::table('OrderData')->where('OrderId', $orderId)->first();
            if ($row && !empty($row->Phone)) {
                $phone = (string)$row->Phone;
                Log::channel('whatsapp')->info('WA payment_status_changed: phone from OrderData', ['phone' => $phone]);
            }
        }

        // Fallback: coba ambil dari User berdasarkan email
        if (empty($phone) && !empty($email)) {
            $u = \App\Models\User::where('email', $email)->first();
            if ($u && !empty($u->phone)) {
                $phone = (string)$u->phone;
                Log::channel('whatsapp')->info('WA payment_status_changed: phone from User', ['phone' => $phone]);
            }
        }

        // Fallback: coba ambil dari CustomerLicense berdasarkan license key
        if (empty($phone) && !empty($licenseKey)) {
            $licRow = \App\Models\CustomerLicense::where('license_key', $licenseKey)->first();
            if ($licRow && !empty($licRow->phone)) {
                $phone = (string)$licRow->phone;
                Log::channel('whatsapp')->info('WA payment_status_changed: phone from License', ['phone' => $phone]);
            }
        }

        // Normalisasi nomor telepon: hapus spasi/simbol, ganti awalan 0 -> 62
        $target = preg_replace('/\s+/', '', (string)$phone);
        $target = preg_replace('/[^0-9+]/', '', $target);
        if (preg_match('/^0\d+$/', $target)) {
            $target = '62' . substr($target, 1);
        }
        if (!$target) {
            Log::channel('whatsapp')->info('WA payment_status_changed not sent: phone empty');
            return;
        }

        $installerVersion = $cfg->InstallerVersion ?? '';
        $installerLink = $cfg->InstallerLink ?? '';
        $groupLink = $cfg->GroupLink ?? '';

        $safeName = $name ?: (strstr($email, '@', true) ?: 'Member');
        $productLine = '';
        $isFreeAccess = false;
        if (!empty($productNameCandidate)) {
            $productNormalized = preg_replace('/Akses\s+Gratis/i', 'Akses 3 Hari', (string)$productNameCandidate);
            $productLine = "Produk: {$productNormalized}\n";
            $isFreeAccess = (stripos($productNameCandidate, 'Akses Gratis') !== false || stripos($productNameCandidate, 'Akses 3 Hari') !== false);
        }

        if ($isFreeAccess) {
            $message = "Halo Kak,\n\n" .
                "*Data License Mesin Cuan Maximal*\n\n" .
                "Nama: {$safeName}\n" .
                "Email: {$email}\n" .
                $productLine .
                "License: {$licenseKey}\n\n" .
                "Untuk Aplikasi, Tutorial dan Tanya Jawab ada digroup whatsapp ya kak silakan bergabung klik dibawah ini\n" .
                "https://s.id/mcmtrial\n\n" .
                "Terimakasih\n" .
                "*Admin MCM*";
        } else {
            $message = "Data License Mesin Cuan Maximal\n\n".
                "Nama: {$safeName}\n".
                "Email: {$email}\n".
                $productLine .
                "License: {$licenseKey}\n".
                "Version: {$installerVersion}\n".
                "Link Installer:\n\n".
                "{$installerLink}\n\n".
                "Untuk tutorial ada digroup telegram ya kak silakan bergabung\n".
                "{$groupLink}\n\n".
                "Terimakasih";
        }

        // Kirim via Whapify API (multipart/form-data)
        try {
            $maskedSecret = strlen((string)$cfg->ApiSecret) > 8
                ? substr($cfg->ApiSecret, 0, 6) . '•••' . substr($cfg->ApiSecret, -2)
                : '•••';
            Log::channel('whatsapp')->info('Attempting WA payment_status_changed via Whapify', [
                'phone' => $target,
                'license' => $licenseKey,
                'version' => $installerVersion,
                'account' => $cfg->AccountUniqueId,
                'secret_masked' => $maskedSecret,
            ]);

            $multipart = [
                ['name' => 'secret', 'contents' => $cfg->ApiSecret],
                // Kirim kedua field untuk kompatibilitas variasi Whapify
                ['name' => 'account', 'contents' => $cfg->AccountUniqueId],
                ['name' => 'accountUniqueId', 'contents' => $cfg->AccountUniqueId],
                ['name' => 'recipient', 'contents' => $target],
                ['name' => 'type', 'contents' => 'text'],
                ['name' => 'message', 'contents' => $message],
                ['name' => 'text', 'contents' => $message],
            ];

            $resp = Http::timeout(20)
                ->asMultipart()
                ->post('https://whapify.id/api/send/whatsapp', [
                    'secret' => $cfg->ApiSecret,
                    'account' => $cfg->AccountUniqueId,
                    'accountUniqueId' => $cfg->AccountUniqueId,
                    'recipient' => $target,
                    'type' => 'text',
                    'message' => $message,
                    'text' => $message,
                ]);

            // Evaluasi seperti test page: cek HTTP dan body JSON
            $bodyText = $resp->body();
            $json = null; try { $json = json_decode($bodyText, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { $json = null; }
            $logicalOk = $resp->ok();
            $logicalStatus = $resp->status();
            if (is_array($json)) {
                if (isset($json['status']) && is_numeric($json['status'])) { $logicalStatus = (int)$json['status']; }
                if (array_key_exists('data', $json)) { $logicalOk = $logicalOk && (bool)$json['data']; }
            }

            if ($logicalOk && $logicalStatus === 200) {
                Log::channel('whatsapp')->info('WA payment_status_changed sent via Whapify', [
                    'phone' => $target,
                    'license' => $licenseKey,
                    'http' => $resp->status(),
                    'account' => $cfg->AccountUniqueId,
                ]);
                // Update delivery status pada license
                if (!empty($licenseKey)) {
                    try {
                        $licRow = \App\Models\CustomerLicense::where('license_key', $licenseKey)->first();
                        if ($licRow) {
                            $licRow->delivery_status = 'Terkirim';
                            $licRow->delivery_log = 'OK http=' . $resp->status() . ' json_status=' . $logicalStatus . ' phone=' . $target . ' at=' . now()->toDateTimeString();
                            $licRow->save();
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Gagal update delivery status (success)', ['license' => $licenseKey, 'error' => $e->getMessage()]);
                    }
                }
            } else {
                Log::channel('whatsapp')->warning('WA payment_status_changed send failed via Whapify', [
                    'status' => $resp->status(),
                    'json_status' => is_array($json) ? ($json['status'] ?? null) : null,
                    'body' => $bodyText,
                    'account' => $cfg->AccountUniqueId,
                ]);
                // Jangan update status gagal dulu; coba retry pakai '+'

                // Retry with '+' prefix if not already present
                if (strpos($target, '+') !== 0) {
                    $targetPlus = '+' . $target;
                    $multipartPlus = [
                        ['name' => 'secret', 'contents' => $cfg->ApiSecret],
                        ['name' => 'account', 'contents' => $cfg->AccountUniqueId],
                        ['name' => 'accountUniqueId', 'contents' => $cfg->AccountUniqueId],
                        ['name' => 'recipient', 'contents' => $targetPlus],
                        ['name' => 'type', 'contents' => 'text'],
                        ['name' => 'message', 'contents' => $message],
                        ['name' => 'text', 'contents' => $message],
                    ];

                    $resp2 = Http::timeout(20)
                        ->asMultipart()
                        ->post('https://whapify.id/api/send/whatsapp', [
                            'secret' => $cfg->ApiSecret,
                            'account' => $cfg->AccountUniqueId,
                            'accountUniqueId' => $cfg->AccountUniqueId,
                            'recipient' => $targetPlus,
                            'type' => 'text',
                            'message' => $message,
                            'text' => $message,
                        ]);

                    $bodyText2 = $resp2->body();
                    $json2 = null; try { $json2 = json_decode($bodyText2, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { $json2 = null; }
                    $logicalOk2 = $resp2->ok();
                    $logicalStatus2 = $resp2->status();
                    if (is_array($json2)) {
                        if (isset($json2['status']) && is_numeric($json2['status'])) { $logicalStatus2 = (int)$json2['status']; }
                        if (array_key_exists('data', $json2)) { $logicalOk2 = $logicalOk2 && (bool)$json2['data']; }
                    }
                    if ($logicalOk2 && $logicalStatus2 === 200) {
                        Log::channel('whatsapp')->info('WA payment_status_changed sent via Whapify after + retry', [
                            'phone' => $targetPlus,
                            'license' => $licenseKey,
                            'http' => $resp2->status(),
                            'account' => $cfg->AccountUniqueId,
                        ]);
                        // Update delivery status pada license (berhasil setelah retry)
                        if (!empty($licenseKey)) {
                            try {
                                $licRow = \App\Models\CustomerLicense::where('license_key', $licenseKey)->first();
                                if ($licRow) {
                                    $licRow->delivery_status = 'Terkirim';
                                    $licRow->delivery_log = 'OK(after +) http=' . $resp2->status() . ' json_status=' . $logicalStatus2 . ' phone=' . $targetPlus . ' at=' . now()->toDateTimeString();
                                    $licRow->save();
                                }
                            } catch (\Throwable $e) {
                                Log::warning('Gagal update delivery status (retry success)', ['license' => $licenseKey, 'error' => $e->getMessage()]);
                            }
                        }
                    } else {
                        Log::channel('whatsapp')->warning('WA payment_status_changed failed after + retry via Whapify', [
                            'status' => $resp2->status(),
                            'json_status' => is_array($json2) ? ($json2['status'] ?? null) : null,
                            'body' => $bodyText2,
                            'account' => $cfg->AccountUniqueId,
                        ]);
                        // Update delivery status pada license (gagal setelah retry)
                        if (!empty($licenseKey)) {
                            try {
                                $licRow = \App\Models\CustomerLicense::where('license_key', $licenseKey)->first();
                                if ($licRow) {
                                    $licRow->delivery_status = 'Gagal';
                                    $licRow->delivery_log = 'FAIL http=' . $resp->status() . ' json_status=' . (is_array($json) ? ($json['status'] ?? null) : 'null') . ' body=' . substr($bodyText,0,500) . ' | RETRY http=' . $resp2->status() . ' json_status=' . (is_array($json2) ? ($json2['status'] ?? null) : 'null') . ' body=' . substr($bodyText2,0,500) . ' at=' . now()->toDateTimeString();
                                    $licRow->save();
                                }
                            } catch (\Throwable $e) {
                                Log::warning('Gagal update delivery status (retry failed)', ['license' => $licenseKey, 'error' => $e->getMessage()]);
                            }
                        }
                    }
                }
                // Jika tidak ada retry (nomor sudah punya +), update sebagai gagal langsung
                if (strpos($target, '+') === 0) {
                    if (!empty($licenseKey)) {
                        try {
                            $licRow = \App\Models\CustomerLicense::where('license_key', $licenseKey)->first();
                            if ($licRow) {
                                $licRow->delivery_status = 'Gagal';
                                $licRow->delivery_log = 'FAIL http=' . $resp->status() . ' json_status=' . (is_array($json) ? ($json['status'] ?? null) : 'null') . ' body=' . substr($bodyText,0,500) . ' phone=' . $target . ' at=' . now()->toDateTimeString();
                                $licRow->save();
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Gagal update delivery status (no retry)', ['license' => $licenseKey, 'error' => $e->getMessage()]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('WA payment_status_changed exception via Whapify', [
                'error' => $e->getMessage(),
                'account' => $cfg->AccountUniqueId ?? null,
            ]);
            // Update delivery status pada license (exception)
            if (!empty($licenseKey)) {
                try {
                    $licRow = \App\Models\CustomerLicense::where('license_key', $licenseKey)->first();
                    if ($licRow) {
                        $licRow->delivery_status = 'Error';
                        $licRow->delivery_log = 'EXCEPTION ' . $e->getMessage() . ' at=' . now()->toDateTimeString();
                        $licRow->save();
                    }
                } catch (\Throwable $e2) {
                    Log::warning('Gagal update delivery status (exception)', ['license' => $licenseKey, 'error' => $e2->getMessage()]);
                }
            }
        }
 
        $needsBonus = !empty($productNameCandidate) && stripos($productNameCandidate, 'Bonus Exclusive') !== false;
        if ($needsBonus) {
            $bonusMessage = "*Bonus Pembelian MCM*\nSilakan akses bonusnya disini ya kak\n\nhttps://drive.google.com/drive/folders/1k78yZYLD7rHWoOigOLb78MaJEXD8W0JX?usp=drive_link";
            try {
                $respB = Http::timeout(20)
                    ->asMultipart()
                    ->post('https://whapify.id/api/send/whatsapp', [
                        'secret' => $cfg->ApiSecret,
                        'account' => $cfg->AccountUniqueId,
                        'accountUniqueId' => $cfg->AccountUniqueId,
                        'recipient' => $target,
                        'type' => 'text',
                        'message' => $bonusMessage,
                        'text' => $bonusMessage,
                    ]);
                Log::channel('whatsapp')->info('WA bonus sent via Whapify', [
                    'phone' => $target,
                    'http' => $respB->status(),
                    'account' => $cfg->AccountUniqueId,
                ]);
            } catch (\Throwable $e) {
                Log::channel('whatsapp')->warning('WA bonus exception via Whapify', [
                    'error' => $e->getMessage(),
                    'account' => $cfg->AccountUniqueId ?? null,
                ]);
            }
        }
    }

    /**
     * Mengirim email berisi informasi login & license setelah status paid.
     * Password hanya disertakan ketika user baru dibuat (temporary password).
     */
    private function sendEmailLoginLicense($user, ?string $plainPassword, $lic, $orderRow, string $statusText): void
    {
        if (!$user || empty($user->email)) { return; }

        $appUrl = rtrim(env('APP_URL', url('/')), '/');
        $loginUrl = $appUrl . '/login';

        $licenseKey = $lic ? ($lic->license_key ?? '') : '';
        $productName = $orderRow ? ($orderRow->ProductName ?? '') : ($lic ? ($lic->product_name ?? '') : '');
        if ($productName) {
            $productName = preg_replace('/Akses\s+Gratis/i', 'Akses 3 Hari', (string)$productName);
        }

        // Informasi tambahan (installer, group) dari WhatsAppConfig bila tersedia
        if ($productName && stripos($productName, 'MCM Mass Voice Over') !== false) {
            $cfg = DB::table('WhatsAppConfig')->where('Id', 3)->first();
            if (!$cfg) {
                $cfg = DB::table('WhatsAppConfig')
                    ->orderByDesc('UpdatedAt')
                    ->orderByDesc('Id')
                    ->first();
            }
        } else {
            $cfg = DB::table('WhatsAppConfig')
                ->orderByDesc('UpdatedAt')
                ->orderByDesc('Id')
                ->first();
        }
        $tenorDays = $lic ? ((int)($lic->tenor_days ?? 0)) : 0;
        $expiresAt = $lic && !empty($lic->expires_at_utc)
            ? Carbon::parse($lic->expires_at_utc)->setTimezone('Asia/Jakarta')->format('d-m-Y H:i')
            : null;
        $price = $orderRow ? ($orderRow->VariantPrice ?? null) : null;
        $installerVersion = $cfg ? ($cfg->InstallerVersion ?? null) : null;
        $installerLink = $cfg ? ($cfg->InstallerLink ?? null) : null;
        $groupLink = $cfg ? ($cfg->GroupLink ?? null) : null;
        $statusLicense = $lic ? ($lic->status ?? 'InActive') : 'InActive';
        $priceText = $price !== null ? $this->formatRupiah((float)$price) : '-';

        $passwordLine = $plainPassword ? "<li><strong>Password</strong>: {$plainPassword}</li>" : '';
        $expiresLine = $expiresAt ? "<li><strong>Berlaku sampai</strong>: {$expiresAt} WIB</li>" : '';
        $installerVerLine = $installerVersion ? "<li><strong>Installer Version</strong>: {$installerVersion}</li>" : '';
        $installerLinkLine = $installerLink ? "<li><strong>Link Installer</strong>: <a href=\"{$installerLink}\" target=\"_blank\">{$installerLink}</a></li>" : '';
        $groupLinkLine = $groupLink ? "<li><strong>Link Group</strong>: <a href=\"{$groupLink}\" target=\"_blank\">{$groupLink}</a></li>" : '';

        $html = <<<HTML
<div style="font-family: Arial, Helvetica, sans-serif; line-height:1.6;">
  <p>Halo {$user->name},</p>
  <p>Status pembayaran: <strong>{$statusText}</strong>. Berikut informasi akun dan license Anda:</p>
  <ul>
    <li><strong>Email</strong>: {$user->email}</li>
    {$passwordLine}
    <li><strong>Login</strong>: <a href="{$loginUrl}" target="_blank">{$loginUrl}</a></li>
  </ul>
  <p><strong>Detail Pembelian</strong></p>
  <ul>
    <li><strong>Produk</strong>: {$productName}</li>
    <li><strong>License</strong>: {$licenseKey}</li>
    <li><strong>Tenor</strong>: {$tenorDays} hari</li>
    {$expiresLine}
    <li><strong>Harga</strong>: {$priceText}</li>
    <li><strong>Status License</strong>: {$statusLicense}</li>
    {$installerVerLine}
    {$installerLinkLine}
    {$groupLinkLine}
  </ul>
  <p>Silakan login dan mulai menggunakan fitur. Jika butuh bantuan, balas email ini.</p>
</div>
HTML;

        Mail::html($html, function ($m) use ($user) {
            $m->to($user->email, $user->name)
              ->subject('Info Akun & License - Pembayaran Berhasil');
        });

        Log::info('Email info login/license dikirim', [
            'email' => $user->email,
            'license_key' => $licenseKey,
        ]);
    }

    private function sendWhatsappLihatDemo(string $phone): void
    {
        // Config retrieval matching sendWhatsappOrderCreated logic
        $cfg = DB::table('WhatsAppConfig')->where('Id', 1)->first();
        if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
            $cfg = DB::table('WhatsAppConfig')
                ->where('Id', '<>', 2)
                ->orderByDesc('UpdatedAt')
                ->first();
            
            if (!$cfg) {
                $cfg = DB::table('WhatsAppConfig')
                    ->orderByDesc('UpdatedAt')
                    ->orderByDesc('Id')
                    ->first();
            }
        }

        if (!$cfg || empty($cfg->ApiSecret) || empty($cfg->AccountUniqueId)) {
            Log::channel('whatsapp')->info('WA Lihat Demo not sent: missing ApiSecret/AccountUniqueId');
            return;
        }

        // Phone normalization matching sendWhatsappOrderCreated
        $target = preg_replace('/\s+/', '', $phone);
        $target = preg_replace('/[^0-9+]/', '', $target);
        if (preg_match('/^0\d+$/', $target)) {
            $target = '62' . substr($target, 1);
        }
        if (preg_match('/^\+62\d+$/', $target)) {
            $target = substr($target, 1);
        }
        if (!$target) return;

        $message = "*Dengan MCM anda bisa*:\n\n" .
                   "1. *Gandakan 2-3 video sumber* menjadi puluhan bahkan ratusan video Unik\n" .
                   "2. Buat banyak _Script Voice Over_ sekali klik\n" .
                   "3. Buat _Voice Over_ sekali klik\n" .
                   "4. Buat _Auto Caption_ video sekali klik\n" .
                   "5. _Upload massal video_ konten dan tinggal ngopi :)\n\n" .
                   "Silakan *Join ke Group Whatsapp MCM Demo* untuk informasi jadwal demo dan tanya jawab seputar MCM klik disini : https://chat.whatsapp.com/HZ4aaRBFt7L65Dj6cz9b6w\n\n" .
                   "*Admin MCM*";

        try {
            $maskedSecret = strlen((string)$cfg->ApiSecret) > 8
                ? substr($cfg->ApiSecret, 0, 6) . '•••' . substr($cfg->ApiSecret, -2)
                : '•••';
            
            Log::channel('whatsapp')->info('Attempting WA Lihat Demo via Whapify', [
                'phone' => $target,
                'account' => $cfg->AccountUniqueId,
                'secret_masked' => $maskedSecret,
            ]);

            // Use asMultipart() and include redundant fields matching sendWhatsappOrderCreated
            $resp = Http::timeout(20)
                ->asMultipart()
                ->post('https://whapify.id/api/send/whatsapp', [
                    'secret' => $cfg->ApiSecret,
                    'account' => $cfg->AccountUniqueId,
                    'accountUniqueId' => $cfg->AccountUniqueId,
                    'recipient' => $target,
                    'type' => 'text',
                    'message' => $message,
                    'text' => $message,
                ]);
            
            // Detailed logging of response
            $bodyText = $resp->body();
            $json = null;
            try { $json = json_decode($bodyText, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { $json = null; }
            $logicalOk = $resp->ok();
            $logicalStatus = $resp->status();
            if (is_array($json)) {
                if (isset($json['status']) && is_numeric($json['status'])) { $logicalStatus = (int)$json['status']; }
                if (array_key_exists('data', $json)) { $logicalOk = $logicalOk && (bool)$json['data']; }
            }

            if ($logicalOk && $logicalStatus === 200) {
                Log::channel('whatsapp')->info('WA Lihat Demo sent via Whapify', [
                    'phone' => $target,
                    'http' => $resp->status(),
                    'account' => $cfg->AccountUniqueId,
                ]);
            } else {
                Log::channel('whatsapp')->warning('WA Lihat Demo send failed via Whapify', [
                    'status' => $resp->status(),
                    'json_status' => is_array($json) ? ($json['status'] ?? null) : null,
                    'body' => $bodyText,
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('WA Lihat Demo exception', ['error' => $e->getMessage()]);
        }
    }
}
