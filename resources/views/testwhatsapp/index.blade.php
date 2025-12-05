<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test WhatsApp</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .row { display: flex; gap: 12px; }
        .col { flex: 1; }
        label { display:block; font-weight:600; margin-bottom:6px; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; }
        textarea { min-height: 100px; }
        .btn { background: #2c7be5; color: #fff; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; }
        .btn:disabled { opacity: .6; cursor: not-allowed; }
        .muted { color:#666; font-size: .9em; }
        pre { background: #f7f7f7; padding: 10px; border-radius: 6px; overflow:auto; }
        .status-ok { color: #2e7d32; }
        .status-fail { color: #c62828; }
    </style>
    @csrf
    @php($hasApiKey = $statusCfg['hasApiKey'] ?? false)
    @php($cfgHint = ($hasApiKey) ? 'Config OK (ApiSecret digunakan sebagai DripSender api_key)' : 'Config belum lengkap: isi api_key override di bawah atau update WhatsAppConfig.ApiSecret')
</head>
<body>
<div class="container">
    <h2>Test WhatsApp</h2>
    <p class="muted">{{ $cfgHint }}</p>

    <form class="card" method="post" action="/test-whatsapp">
        {{ csrf_field() }}
        <div class="row">
            <div class="col">
                <label>Recipient</label>
                <input type="text" name="recipient" value="{{ old('recipient', $recipient ?? '') }}" placeholder="contoh: 081234567890 atau 6281234567890" />
            </div>
            <div class="col">
                <label>Type</label>
                <input type="text" value="text" disabled />
            </div>
        </div>
        <div class="row">
            <div class="col">
                <label>Message</label>
                <textarea name="message" placeholder="Isi pesan">{{ old('message', $message ?? '') }}</textarea>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <label>Secret (override, opsional)</label>
                <input type="text" name="secret" value="{{ old('secret', $overrideSecret ?? '') }}" placeholder="Jika kosong, pakai dari WhatsAppConfig.ApiSecret" />
            </div>
            <div class="col">
                <label>Account (override, opsional)</label>
                <input type="text" name="account" value="{{ old('account', $overrideAccount ?? '') }}" placeholder="Jika kosong, pakai dari WhatsAppConfig.AccountUniqueId" />
            </div>
        </div>
        <div style="margin-top:12px">
            <button class="btn" type="submit">Kirim Test</button>
            <a class="btn" style="background:#6c757d;margin-left:8px" href="/debug/whatsapp-log" target="_blank">Tuliskan Log Manual</a>
        </div>
    </form>

    <div class="card">
        <h3>Hasil Kirim</h3>
        @if($result)
            @if($result['ok'])
                <p class="status-ok">Sukses (status {{ $result['status'] }})</p>
            @else
                <p class="status-fail">Gagal (status {{ $result['status'] }})</p>
            @endif
            <pre>{{ is_string($result['body']) ? $result['body'] : json_encode($result['body'], JSON_PRETTY_PRINT) }}</pre>
        @else
            <p class="muted">Belum ada percobaan kirim pada sesi ini.</p>
        @endif
    </div>

    <div class="card">
        <h3>Request Preview (cURL)</h3>
        @if(!empty($curlPreview))
            <pre>{{ $curlPreview }}</pre>
            <p class="muted">Catatan: api_key dimasking untuk keamanan.</p>
        @else
            <p class="muted">Preview akan muncul setelah kamu menekan "Kirim Test".</p>
        @endif
    </div>

    <div class="card">
        <h3>Tail Log WhatsApp (100 baris terakhir)</h3>
        @if(!empty($logTail))
            <pre>@foreach($logTail as $ln){{ $ln }}
@endforeach</pre>
        @else
            <p class="muted">Belum ada file log atau tidak ada isi. Coba klik "Tuliskan Log Manual" atau kirim test.</p>
        @endif
    </div>
</div>
</body>
</html>
