<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\GenerateVoJob;

class VoiceOverController extends Controller
{
    public function generate(Request $request)
    {
        $text = (string)($request->input('text') ?? '');
        $voice = (string)($request->input('voice') ?? '');
        $speed = (float)($request->input('speed') ?? 1.0);
        if (!$text) return response()->json(['ok'=>false,'message'=>'Text required'],400);
        $id = DB::table('voice_jobs')->insertGetId([
            'text' => $text,
            'voice' => $voice ?: null,
            'speed' => $speed,
            'status' => 'queued',
            'path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        GenerateVoJob::dispatch($id)->onQueue('tts');
        return response()->json(['ok'=>true,'job_id'=>$id]);
    }

    public function status($jobId)
    {
        $row = DB::table('voice_jobs')->where('id',$jobId)->first();
        if (!$row) return response()->json(['ok'=>false,'message'=>'Not found'],404);
        $fileUrl = $row->path ? asset('storage/'.$row->path) : null;
        return response()->json([
            'ok' => true,
            'job_id' => $row->id,
            'status' => $row->status,
            'file_url' => $fileUrl,
        ]);
    }
}