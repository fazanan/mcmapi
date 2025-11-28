<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, string $voiceName, $keyRow)
    {
        $model = $keyRow->Model ?? "gemini-2.5-pro-preview-tts";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/"
             . urlencode($model)
             . ":generateContent?key={$keyRow->ApiKey}";

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
                "temperature" => 1,
                "responseModalities" => ["audio"]
            ],
            "speechConfig" => [
                "voiceConfig" => [
                    "prebuiltVoiceConfig" => [
                        "voiceName" => $voiceName   // contoh: "Zephyr"
                    ]
                ]
            ]
        ];

        $resp = Http::timeout(60)->post($url, $payload);

        if (!$resp->successful()) {
            return [
                "error" => $resp->status(),
                "body" => $resp->json()
            ];
        }

        $json = $resp->json();

        // ambil audio base64
        $b64 = data_get($json, "candidates.0.content.parts.0.inlineData.data");

        if (!$b64) {
            return [
                "error" => "NO_AUDIO",
                "body" => $json
            ];
        }

        return base64_decode($b64);  
    }
}
