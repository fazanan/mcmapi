<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CustomerLicense;

class TestResetLicenseSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'rudianto2008@gmail.com';
        $key = '2F5BEAC9-97D5-4794-AA09-43C09AE401A3';
        $lic = CustomerLicense::query()->where('license_key',$key)->first();
        if (!$lic) {
            CustomerLicense::create([
                'order_id' => 'ORD-TEST-RESET-001',
                'license_key' => $key,
                'owner' => null,
                'email' => $email,
                'phone' => null,
                'edition' => 'Pro',
                'payment_status' => 'paid',
                'product_name' => 'MesinCuan',
                'tenor_days' => null,
                'is_activated' => true,
                'activation_date_utc' => now('UTC'),
                'expires_at_utc' => '2026-02-04 00:00:00',
                'machine_id' => '178BFBFF00810F81|0000_0000_0100_0000_E4D2_5C7B_2F9B_5101',
                'max_seats' => 1,
                'max_video' => 2147483647,
                'features' => json_encode(['Batch','TextOverlay']),
                'vo_seconds_remaining' => 0,
                'status' => 'Active',
            ]);
        } else {
            $lic->email = $email;
            $lic->edition = 'Pro';
            $lic->payment_status = 'paid';
            $lic->is_activated = true;
            $lic->activation_date_utc = now('UTC');
            $lic->expires_at_utc = '2026-02-04 00:00:00';
            $lic->machine_id = '178BFBFF00810F81|0000_0000_0100_0000_E4D2_5C7B_2F9B_5101';
            $lic->status = 'Active';
            $lic->save();
        }
    }
}