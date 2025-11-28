<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, string $voice, $keyRow)
    {
        // MODEL YANG PASTI SUPPORT
        $model = 'gemini-2.0-tts';

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$keyRow->ApiKey}";

        // PAYLOAD WAJIB FORMAT GOOGLE AI STUDIO REST API (snake_case)
        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        ["text" => $text]
                    ]
                ]
            ],

            "generationConfig" => [
                "responseModalities" => ["audio"],

                // optional agar natural
                "temperature" => 1,

                // FORMAT BARU — WAJIB SNAKE_CASE
                "speech_config" => [
                    "voice_config" => [
                        "prebuilt_voice_config" => [
                            "voice_name" => $voice
                        ]
                    ]
                ],
            ]
        ];

        try {
            $resp = Http::timeout(40)->post($url, $payload);

            // HANDLE ERROR 429 (quota)
            if ($resp->status() === 429) {
                return [
                    'error' => 429,
                    'retry' => true,
                    'body' => $resp->json(),
                ];
            }

            // HANDLE ERROR LAINNYA
            if ($resp->status() !== 200) {
                return [
                    'error' => $resp->status(),
                    'body' => $resp->json(),
                ];
            }

            // AUDIO BERADA DI inlineData.data
            $b64 = data_get($resp->json(), "candidates.0.content.parts.0.inlineData.data");

            if (!$b64) {
                return [
                    'error' => 'no_audio',
                    'body'  => $resp->json()
                ];
            }

            return base64_decode($b64);
        }

        catch (\Throwable $e) {
            return [
                'error' => 'exception',
                'message' => $e->getMessage(),
            ];
        }
    }
}
