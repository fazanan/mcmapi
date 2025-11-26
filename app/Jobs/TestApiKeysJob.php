<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TestApiKeysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $rows = DB::table('ConfigApiKey')
            ->whereRaw('LOWER(JenisApiKey) = ?', ['gemini'])
            ->whereNotNull('ApiKey')
            ->where('ApiKey','<>','')
            ->orderByDesc('UpdatedAt')
            ->get();

        foreach ($rows as $row) {
            $apiKey = $row->ApiKey;
            $model = $row->Model ?: 'gemini-2.0-flash';
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.urlencode($model).':generateContent?key='.$apiKey;

            try {
                $resp = Http::timeout(10)->acceptJson()->asJson()->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [ ['text' => 'ping'] ]
                        ]
                    ]
                ]);

                $code = $resp->status();
                if ($code === 200) {
                    DB::table('ConfigApiKey')->where('ApiKeyId',$row->ApiKeyId)->update([
                        'Status' => 'AVAILABLE',
                        'CooldownUntilPT' => null,
                        'UpdatedAt' => now('UTC'),
                    ]);
                } else if ($code === 429) {
                    DB::table('ConfigApiKey')->where('ApiKeyId',$row->ApiKeyId)->update([
                        'Status' => 'KENA LIMIT',
                        'CooldownUntilPT' => now('UTC')->addMinutes(5),
                        'UpdatedAt' => now('UTC'),
                    ]);
                } else if ($code === 400 || $code === 401) {
                    DB::table('ConfigApiKey')->where('ApiKeyId',$row->ApiKeyId)->update([
                        'Status' => 'INVALID',
                        'UpdatedAt' => now('UTC'),
                    ]);
                } else {
                    DB::table('ConfigApiKey')->where('ApiKeyId',$row->ApiKeyId)->update([
                        'Status' => 'UNKNOWN',
                        'UpdatedAt' => now('UTC'),
                    ]);
                }
            } catch (\Throwable $e) {
                DB::table('ConfigApiKey')->where('ApiKeyId',$row->ApiKeyId)->update([
                    'Status' => 'INVALID',
                    'UpdatedAt' => now('UTC'),
                ]);
            }
        }
    }
}