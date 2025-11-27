<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CheckApiKeyCooldownJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $now = now('UTC');

        // Ambil semua key yang sedang cooldown
        $keys = DB::table('ConfigApiKey')
            ->where('Status', 'COOLDOWN')
            ->whereNotNull('CooldownUntilPT')
            ->get();

        foreach ($keys as $key) {

            // Jika cooldown sudah selesai → ubah jadi AVAILABLE
            if ($now->greaterThanOrEqualTo($key->CooldownUntilPT)) {

                DB::table('ConfigApiKey')
                    ->where('ApiKeyId', $key->ApiKeyId)
                    ->update([
                        'Status' => 'AVAILABLE',
                        'CooldownUntilPT' => null,
                        'MinuteCount' => 0,
                        'DayCount' => 0,
                        'UpdatedAt' => now()
                    ]);

                echo "API Key {$key->ApiKeyId} kembali AVAILABLE.\n";
            }
        }
    }
}
