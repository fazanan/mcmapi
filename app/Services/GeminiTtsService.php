<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function testTts(string $text, $keyRow)
    {
        $model = 'gemini-2.0-tts';

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$keyRow->ApiKey}";

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
                "responseModalities" => ["AUDIO"],
            ],
            "speechConfig" => [
                "voiceConfig" => [
                    "prebuiltVoiceConfig" => [
                        "voiceName" => "Zephyr"
                    ]
                ]
            ]
        ];

        $resp = Http::timeout(30)->post($url, $payload);

        if ($resp->status() !== 200) {
            return [
                'error' => $resp->status(),
                'body' => $resp->json()
            ];
        }

        // Ambil inline audio
        $inline = data_get($resp->json(), 'candidates.0.content.parts.0.inlineData.data');

        if (!$inline) {
            return [
                'error' => 'no_audio',
                'body' => $resp->json()
            ];
        }

        return base64_decode($inline);
    }
}
