<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, ?string $voice, ?float $speed, $keyRow)
    {
        $model = $keyRow->Model ?? 'gemini-2.5-pro-preview-tts';

        $voiceName = $voice ?: ($keyRow->DefaultVoiceId ?? 'Verse');

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . urlencode($model)
            . ':generateSpeech?key='
            . $keyRow->ApiKey;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ],
            'audioConfig' => [
                'voiceName' => $voiceName,
                'audioEncoding' => 'MP3',
                'speakingRate' => $speed ?? 1.0
            ]
        ];

        try {
            $resp = Http::timeout(25)->post($url, $payload);

            // Jika berhasil → audioBase64 ada di sini
            if ($resp->successful()) {
                $json = $resp->json();
                $base64 = data_get($json, 'audioContent');

                if ($base64) {
                    return base64_decode($base64);
                }
            }

            // Jika gagal → kembalikan error detailnya untuk debugging
            return [
                'status' => $resp->status(),
                'body' => $resp->json(),
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'exception',
                'error' => $e->getMessage()
            ];
        }
    }
}
