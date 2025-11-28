<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, string $voice, $keyRow)
    {
        $model = $keyRow->Model ?? "gemini-2.5-pro-preview-tts";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/"
             . urlencode($model)
             . ":streamGenerateContent?key={$keyRow->ApiKey}";

        // === INI 100% CONTEKAN RESMI REST GOOGLE ===
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
                "temperature" => 1,
                "speech_config" => [
                    "voice_config" => [
                        "prebuilt_voice_config" => [
                            "voice_name" => $voice
                        ]
                    ]
                ]
            ]
        ];

        // Google streamGenerateContent tetap bisa diproses via HTTP normal
        $resp = Http::timeout(60)->post($url, $payload);

        if (!$resp->successful()) {
            return [
                "error" => $resp->status(),
                "body" => $resp->json()
            ];
        }

        $json = $resp->json();

        // Ambil inline audio (Google selalu pakai inline_data)
        $b64 = data_get($json, "candidates.0.content.parts.0.inline_data.data");

        if (!$b64) {
            return [
                "error" => "NO_AUDIO",
                "body" => $json
            ];
        }

        return base64_decode($b64);
    }
}
