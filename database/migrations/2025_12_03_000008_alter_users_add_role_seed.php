<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // Add role column if not exists
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('member')->after('password');
            }
        });

        // Seed a default admin and member if they don't exist
        try {
            $now = now();
            $adminEmail = 'admin@mcm.local';
            $memberEmail = 'member@mcm.local';

            if (!DB::table('users')->where('email', $adminEmail)->exists()) {
                DB::table('users')->insert([
                    'name' => 'Administrator',
                    'email' => $adminEmail,
                    'password' => Hash::make('admin123'),
                    'role' => 'admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (!DB::table('users')->where('email', $memberEmail)->exists()) {
                DB::table('users')->insert([
                    'name' => 'Member',
                    'email' => $memberEmail,
                    'password' => Hash::make('member123'),
                    'role' => 'member',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            // No-op: seeding is best-effort only
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });

        // Optionally remove seeded accounts
        try {
            DB::table('users')->whereIn('email', ['admin@mcm.local', 'member@mcm.local'])->delete();
        } catch (\Throwable $e) {
            // ignore
        }
    }
};

