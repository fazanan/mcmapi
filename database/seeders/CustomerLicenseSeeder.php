<?php

namespace Database\Seeders;

use App\Models\CustomerLicense;
use App\Models\VoiceOverTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class CustomerLicenseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        VoiceOverTransaction::query()->truncate();
        CustomerLicense::query()->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        CustomerLicense::create([
            'order_id' => 'ORD-001',
            'license_key' => 'LIC-ABC-123',
            'owner' => 'Budi',
            'email' => 'budi@example.com',
            'phone' => '0812345678',
            'edition' => 'Pro',
            'payment_status' => 'paid',
            'product_name' => 'MesinCuan',
            'tenor_days' => 30,
            'is_activated' => true,
            'activation_date_utc' => '2024-10-01 00:00:00',
            'expires_at_utc' => '2025-12-31 00:00:00',
            'machine_id' => 'MACHINE-123',
            'vo_seconds_remaining' => 1800,
            'status' => 'active',
        ]);

        CustomerLicense::create([
            'order_id' => 'ORD-002',
            'license_key' => 'LIC-XYZ-789',
            'owner' => 'Siti',
            'email' => 'siti@example.com',
            'phone' => '0819876543',
            'edition' => 'Basic',
            'payment_status' => 'unpaid',
            'product_name' => 'MesinCuan Lite',
            'tenor_days' => 0,
            'is_activated' => false,
            'activation_date_utc' => null,
            'expires_at_utc' => null,
            'machine_id' => null,
            'vo_seconds_remaining' => 600,
            'status' => 'inactive',
        ]);
    }
}