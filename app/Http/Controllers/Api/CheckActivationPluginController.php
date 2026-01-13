<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CustomerLicense;
use App\Models\LicenseActivationsPlugin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckActivationPluginController extends Controller
{
    public function checkActivation(Request $request)
    {
        // 1. Validate Input
        $licenseKey = $request->input('license_key') ?? $request->input('LicenseKey');
        $deviceId   = $request->input('device_id') ?? $request->input('DeviceId');
        $productName = $request->input('product_name') ?? $request->input('ProductName');

        if (!$licenseKey || !$deviceId) {
            return response()->json([
                'isValid' => false,
                'message' => 'License Key and Device ID are required.'
            ], 400);
        }

        // 2. Find License
        $license = CustomerLicense::where('license_key', $licenseKey)->first();

        if (!$license) {
            return response()->json([
                'isValid' => false,
                'message' => 'License key not found.'
            ], 404);
        }

        // 3. Check License Status
        // Assume 'paid' or 'active' is valid. Adjust based on your business logic.
        // Also check expiry if applicable
        $isExpired = $license->expires_at_utc && Carbon::parse($license->expires_at_utc)->isPast();
        if ($isExpired) {
            return response()->json([
                'isValid' => false,
                'message' => 'License expired.'
            ], 403);
        }

        $prodRaw = $productName ?: ($license->product_name ?? '');
        $prodNorm = strtolower(trim($prodRaw));
        if (strpos($prodNorm, 'tiktok') !== false) { $productCode = 'massuploadtiktok'; }
        else if (strpos($prodNorm, 'shopee') !== false) { $productCode = 'shopeescrap'; }
        else { $productCode = $prodNorm ?: 'unknown'; }
        $activation = LicenseActivationsPlugin::where('license_key', $licenseKey)
            ->where('device_id', $deviceId)
            ->where('product_name', $productCode)
            ->where('revoked', false)
            ->first();

        if ($activation) {
            // Update last seen
            $activation->last_seen_at = Carbon::now();
            $activation->save();
            
            // Also update main license last_used
            $license->last_used = Carbon::now();
            $license->save();

            if ($productCode === 'shopeescrap') {
                $maxSeats = (int)($license->max_seats_shopee_scrap ?? 0);
            } else if ($productCode === 'massuploadtiktok') {
                $maxSeats = (int)($license->max_seat_upload_tiktok ?? 0);
            } else {
                $maxSeats = (int)($license->max_seats ?? 0);
            }
            $usedSeats = LicenseActivationsPlugin::where('license_key', $licenseKey)
                ->where('product_name', $productCode)
                ->where('revoked', false)
                ->count();

            return response()->json([
                'isValid' => true,
                'message' => 'Activated.',
                'activation' => $activation,
                'expiredAt' => $license->expires_at_utc,
                'expired_at' => optional($license->expires_at_utc)->toIso8601String(),
                'product' => $productCode,
                'max_seats' => $maxSeats,
                'used_seats' => $usedSeats,
                'maxseatsshopee' => $maxSeats,
                'usedseatshopee' => $usedSeats
            ]);
        }

        if ($productCode === 'shopeescrap') {
            $maxSeats = (int)($license->max_seats_shopee_scrap ?? 0);
        } else if ($productCode === 'massuploadtiktok') {
            $maxSeats = (int)($license->max_seat_upload_tiktok ?? 0);
        } else {
            $maxSeats = (int)($license->max_seats ?? 0);
        }
        $usedSeats = LicenseActivationsPlugin::where('license_key', $licenseKey)
            ->where('product_name', $productCode)
            ->where('revoked', false)
            ->count();

        if ($usedSeats >= $maxSeats) {
             return response()->json([
                'isValid' => false,
                'message' => 'Max seats reached.',
                'max_seats' => $maxSeats,
                'used_seats' => $usedSeats,
                'expired_at' => optional($license->expires_at_utc)->toIso8601String()
            ], 403);
        }

        $newActivation = LicenseActivationsPlugin::create([
            'license_key' => $licenseKey,
            'device_id' => $deviceId,
            'product_name' => $productCode,
            'activated_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
            'revoked' => false
        ]);

        if ($productCode === 'shopeescrap') {
            $license->used_seats_shopee_scrap = $usedSeats + 1;
        } else if ($productCode === 'massuploadtiktok') {
            $license->used_seat_upload_tiktok = $usedSeats + 1;
        }
        $license->last_used = Carbon::now();
        $license->save();

        return response()->json([
            'isValid' => true,
            'message' => 'Activation successful.',
            'activation' => $newActivation,
            'expiredAt' => $license->expires_at_utc,
            'expired_at' => optional($license->expires_at_utc)->toIso8601String(),
            'product' => $productCode,
            'max_seats' => $maxSeats,
            'used_seats' => $usedSeats + 1,
            'maxseatsshopee' => $maxSeats,
            'usedseatshopee' => $usedSeats + 1
        ]);
    }

    public function logout(Request $request)
    {
        $licenseKey = $request->input('license_key') ?? $request->input('LicenseKey');
        $deviceId   = $request->input('device_id') ?? $request->input('DeviceId');
        $productName = $request->input('product_name') ?? $request->input('ProductName');

        if (!$licenseKey || !$deviceId) {
            return response()->json([
                'success' => false,
                'message' => 'License Key and Device ID are required.'
            ], 400);
        }

        $license = CustomerLicense::where('license_key', $licenseKey)->first();
        if (!$license) {
            return response()->json([
                'success' => false,
                'message' => 'License key not found.'
            ], 404);
        }

        $prodRaw = $productName ?: ($license->product_name ?? '');
        $prodNorm = strtolower(trim($prodRaw));
        if (strpos($prodNorm, 'tiktok') !== false) { $productCode = 'massuploadtiktok'; }
        else if (strpos($prodNorm, 'shopee') !== false) { $productCode = 'shopeescrap'; }
        else { $productCode = $prodNorm ?: 'unknown'; }

        $activation = LicenseActivationsPlugin::where('license_key', $licenseKey)
            ->where('device_id', $deviceId)
            ->where('product_name', $productCode)
            ->where('revoked', false)
            ->first();

        if ($activation) {
            $activation->revoked = true;
            $activation->last_seen_at = Carbon::now();
            $activation->save();
        }

        if ($productCode === 'shopeescrap') {
            $maxSeats = (int)($license->max_seats_shopee_scrap ?? 0);
        } else if ($productCode === 'massuploadtiktok') {
            $maxSeats = (int)($license->max_seat_upload_tiktok ?? 0);
        } else {
            $maxSeats = (int)($license->max_seats ?? 0);
        }
        $usedSeats = LicenseActivationsPlugin::where('license_key', $licenseKey)
            ->where('product_name', $productCode)
            ->where('revoked', false)
            ->count();

        if ($productCode === 'shopeescrap') {
            $license->used_seats_shopee_scrap = $usedSeats;
        } else if ($productCode === 'massuploadtiktok') {
            $license->used_seat_upload_tiktok = $usedSeats;
        }
        $license->last_used = Carbon::now();
        $license->save();

        return response()->json([
            'success' => true,
            'message' => $activation ? 'Logout successful.' : 'Already logged out.',
            'product' => $productCode,
            'max_seats' => $maxSeats,
            'used_seats' => $usedSeats
        ]);
    }
}
