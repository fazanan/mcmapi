<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ApiKeySelector
{
    public function select()
    {
        return DB::transaction(function () {
            $row = DB::table('ConfigApiKey')
                ->where('Status', 'AVAILABLE')
                ->whereColumn('MinuteCount', '<', 'RpmLimit')
                ->whereColumn('DayCount', '<', 'RpdLimit')
                ->where(function ($q) {
                    $q->whereNull('CooldownUntilPT')
                      ->orWhere('CooldownUntilPT', '<', now());
                })
                ->orderBy('MinuteCount', 'asc')
                ->orderBy('DayCount', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$row) return null;

            $minuteEnd = $row->MinuteWindowEndPT ? \Illuminate\Support\Carbon::parse($row->MinuteWindowEndPT) : now();
            $dayEnd = $row->DayWindowEndPT ? \Illuminate\Support\Carbon::parse($row->DayWindowEndPT) : now();
            $upd = [];
            if ($minuteEnd->lt(now())) {
                $upd['MinuteCount'] = 0;
                $upd['MinuteWindowEndPT'] = now()->addMinute();
            }
            if ($dayEnd->lt(now())) {
                $upd['DayCount'] = 0;
                $upd['DayWindowEndPT'] = now()->addDay();
            }
            if (!empty($upd)) {
                $upd['UpdatedAt'] = now('UTC');
                DB::table('ConfigApiKey')->where('ApiKeyId', $row->ApiKeyId)->update($upd);
                $row = DB::table('ConfigApiKey')->where('ApiKeyId', $row->ApiKeyId)->first();
            }
            return $row;
        }, 5);
    }

    public function mark429($apiKeyId)
    {
        DB::table('ConfigApiKey')->where('ApiKeyId', $apiKeyId)->update([
            'Status' => 'KENA LIMIT',
            'CooldownUntilPT' => now('UTC')->addSeconds(5),
            'UpdatedAt' => now('UTC'),
        ]);
    }

    public function incrementUsage($apiKeyId)
    {
        $row = DB::table('ConfigApiKey')->where('ApiKeyId', $apiKeyId)->first();
        if (!$row) return;
        DB::table('ConfigApiKey')->where('ApiKeyId', $apiKeyId)->update([
            'MinuteCount' => ($row->MinuteCount ?? 0) + 1,
            'DayCount' => ($row->DayCount ?? 0) + 1,
            'UpdatedAt' => now('UTC'),
        ]);
    }
}