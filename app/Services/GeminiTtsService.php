<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, $keyRow)
    {
        // model TTS yang memang ada di API key kamu
        $model = "gemini-2.5-flash-preview-tts";
        $url = "https://generativelanguage.googleapis.com/v1beta/model/{$model}:streamGenerateContent?key={$keyRow->ApiKey}";

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
                "temperature" => 1
            ]
        ];

        $resp = Http::timeout(30)->post($url, $payload);

        if ($resp->status() !== 200) {
            return [
                "error"  => $resp->status(),
                "body"   => $resp->json(),
            ];
        }

        $b64 = data_get($resp->json(), 'candidates.0.content.parts.0.inline_data.data');

        if (!$b64) return ["error" => "NO_AUDIO"];

        return base64_decode($b64);
    }
}
