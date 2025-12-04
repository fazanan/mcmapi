@extends('layouts.app')

@section('title','Produk')

@section('content')
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title">Produk Saya</h4>
        <p class="text-muted">Menampilkan produk yang sudah dibeli sesuai email terdaftar.</p>

        <div class="mb-3">
          <span class="badge badge-info">Email: {{ auth()->user()->email }}</span>
        </div>

        <table id="tblProduk" class="display nowrap" style="width:100%">
          <thead>
            <tr>
              <th>Nama Produk</th>
              <th>License</th>
              <th>Tenor (hari)</th>
              <th>Harga</th>
              <th>Status License</th>
              <th>Installer Version</th>
              <th>Link Installer</th>
              <th>Link Group</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@push('scripts')
<script>
$(function(){
  const userEmail = @json(auth()->user()->email);
  const tbl = $('#tblProduk').DataTable({
    responsive: true,
    columns: [
      { title: 'Nama Produk' },
      { title: 'License' },
      { title: 'Tenor (hari)' },
      { title: 'Harga' },
      { title: 'Status License' },
      { title: 'Installer Version' },
      { title: 'Link Installer' },
      { title: 'Link Group' }
    ]
  });

  async function api(url, method = 'GET') {
    const r = await fetch(url, { method, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    const ok = r.ok; const code = r.status;
    let json = null; let text = null;
    try { json = await r.json(); } catch { text = await r.text(); }
    return { ok, code, json, text };
  }

  function fmtPrice(n){
    if (n === null || n === undefined) return '-';
    const x = Number(n);
    if (!isFinite(x)) return '-';
    return 'Rp ' + x.toLocaleString('id-ID');
  }

  async function loadConfig(){
    const r = await api('/api/whatsappconfig');
    if (!r.ok || !Array.isArray(r.json)) return { InstallerLink: null, GroupLink: null, InstallerVersion: null };
    // Sudah diurutkan desc UpdatedAt; ambil entri pertama sebagai latest
    const latest = r.json[0] || {};
    return {
      InstallerLink: latest.InstallerLink || null,
      GroupLink: latest.GroupLink || null,
      InstallerVersion: latest.InstallerVersion || null,
    };
  }

  async function loadLicenses(){
    const r = await api('/api/customerlicense?q=' + encodeURIComponent(userEmail));
    if (!r.ok || !Array.isArray(r.json)) { alert('Gagal mengambil license: ' + (r.text || r.code)); return []; }
    return r.json;
  }

  async function loadOrder(orderId){
    if (!orderId) return null;
    const r = await api('/api/orderdata/' + encodeURIComponent(orderId));
    if (!r.ok) return null;
    return r.json;
  }

  async function reload(){
    tbl.clear();
    const cfg = await loadConfig();
    const rows = await loadLicenses();
    for (const x of rows) {
      const order = await loadOrder(x.OrderId);
      const price = order ? (order.VariantPrice ?? order.NetRevenue ?? null) : null;
      const lic = (x.LicenseKey || '').trim();
      const installerLink = cfg.InstallerLink ? `<a href="${cfg.InstallerLink}" target="_blank" rel="noopener">Download</a>` : '-';
      const groupLink = cfg.GroupLink ? `<a href="${cfg.GroupLink}" target="_blank" rel="noopener">Join Group</a>` : '-';
      tbl.row.add([
        x.ProductName || '-',
        lic || '-',
        (x.TenorDays ?? '-') ,
        fmtPrice(price),
        x.Status || '-',
        cfg.InstallerVersion || '-',
        installerLink,
        groupLink
      ]);
    }
    tbl.draw();
  }

  reload();
});
</script>
@endpush
@endsection
