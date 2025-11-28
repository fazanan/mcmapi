<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, ?string $voice, ?float $speed, $keyRow)
    {
        $model = strtolower($keyRow->Model ?? 'gemini-2.5-flash-preview-tts');
        if (!in_array($model, ['gemini-2.5-flash-preview-tts', 'gemini-2.5-pro-preview-tts'])) {
            $model = 'gemini-2.5-flash-preview-tts';
        }
        usleep(1100 * 1000);
        $urlAudio = 'https://generativelanguage.googleapis.com/v1beta/models/'.urlencode($model).':generateAudio?key='.$keyRow->ApiKey;
        $payloadAudio = [
            'input' => [ 'text' => $text ],
            'voiceConfig' => [ 'voiceName' => $voice ?: ($keyRow->DefaultVoiceId ?? 'Puck') ],
            'audioConfig' => [ 'audioEncoding' => 'MP3', 'speakingRate' => ($speed ?? 1.0) ]
        ];
        try {
            $resp = Http::timeout(20)->acceptJson()->asJson()->post($urlAudio, $payloadAudio);
            if ($resp->status() === 200) {
                $json = $resp->json();
                $data = data_get($json, 'audioContent');
                if ($data) {
                    return base64_decode($data);
                }
            } elseif ($resp->status() === 429) {
                return ['error' => 429];
            }
        } catch (\Throwable $e) {
        }
        $urlContent = 'https://generativelanguage.googleapis.com/v1beta/models/'.urlencode($model).':generateContent?key='.$keyRow->ApiKey;
        $payloadContent = [
            'contents' => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $text ] ] ] ]
        ];
        try {
            $resp = Http::timeout(20)->acceptJson()->asJson()->post($urlContent, $payloadContent);
            if ($resp->status() === 200) {
                $json = $resp->json();
                $inline = data_get($json, 'candidates.0.content.parts.0.inline_data.data');
                if ($inline) {
                    return base64_decode($inline);
                }
                return null;
            } elseif ($resp->status() === 429) {
                return ['error' => 429];
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}