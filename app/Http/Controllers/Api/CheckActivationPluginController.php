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

        // 4. Check Plugin Activation / Seats
        // Check if this device is already activated
        $activation = LicenseActivationsPlugin::where('license_key', $licenseKey)
            ->where('device_id', $deviceId)
            ->first();

        if ($activation) {
            // Update last seen
            $activation->last_seen_at = Carbon::now();
            $activation->save();
            
            // Also update main license last_used
            $license->last_used = Carbon::now();
            $license->save();

            return response()->json([
                'isValid' => true,
                'message' => 'Activated.',
                'activation' => $activation
            ]);
        }

        // New Device: Check seats availability
        // Determine max seats based on product name if needed, or use column
        $maxSeats = $license->max_seats_shopee_scrap ?? $license->max_seats ?? 1;
        $usedSeats = LicenseActivationsPlugin::where('license_key', $licenseKey)->count();

        if ($usedSeats >= $maxSeats) {
             return response()->json([
                'isValid' => false,
                'message' => 'Max seats reached.',
                'max_seats' => $maxSeats,
                'used_seats' => $usedSeats
            ], 403);
        }

        // Register new activation
        $newActivation = LicenseActivationsPlugin::create([
            'license_key' => $licenseKey,
            'device_id' => $deviceId,
            'product_name' => $productName ?? $license->product_name,
            'activated_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
            'revoked' => false
        ]);

        // Update license stats
        $license->used_seats_shopee_scrap = $usedSeats + 1; // Or just increment
        $license->last_used = Carbon::now();
        $license->save();

        return response()->json([
            'isValid' => true,
            'message' => 'Activation successful.',
            'activation' => $newActivation
        ]);
    }
}
