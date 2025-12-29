document.addEventListener('DOMContentLoaded', () => {
  const api = {
    list: q => fetch(`/api/customerlicense${q ? ('?q=' + encodeURIComponent(q)) : ''}`).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    one: id => fetch(`/api/customerlicense/${encodeURIComponent(id)}`).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    update: (id, payload) => fetch(`/api/customerlicense/${encodeURIComponent(id)}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    del: (id, hard = false) => fetch(`/api/customerlicense/${encodeURIComponent(id)}?hard=${hard}`, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    create: payload => fetch(`/api/customerlicense`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); }))
  };
  let dt = null;
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toUtcString(isoUtc) { if (!isoUtc) return ''; const s = isoUtc.endsWith('Z') ? isoUtc : isoUtc + 'Z'; const d = new Date(s); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())} ${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`; }
  function toLocalInputFromUtc(isoUtc) { if (!isoUtc) return ''; const s = isoUtc.endsWith('Z') ? isoUtc : isoUtc + 'Z'; const d = new Date(s); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`; }
  function localInputToUtcIso(localVal) { if (!localVal) return null; const d = new Date(localVal); return new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes(), 0, 0)).toISOString(); }
  function toIntOrNull(v) { if (v == null || v === '') return null; const n = parseInt(v, 10); return isNaN(n) ? null : n; }
  async function renderRows(rows) {
    if (dt) { dt.destroy(); dt = null; }
    const tbody = document.querySelector('#tblLicenses tbody'); tbody.innerHTML = '';
    for (const r of rows) {
      const tr = document.createElement('tr');
      tr.innerHTML =
        `<td><div class=\"btn-group btn-group-sm\"><button class=\"btn btn-primary btn-edit\" data-id=\"${r.OrderId}\">Edit</button><button class=\"btn btn-outline-danger btn-del\" data-id=\"${r.OrderId}\">Del</button><button class=\"btn btn-success btn-topup\" data-id=\"${r.OrderId}\">TopUp</button></div></td>` +
        `<td>${r.Status || ''}</td>` +
        `<td>${r.OrderId || ''}</td>` +
        `<td>${r.LicenseKey || ''}</td>` +
        `<td>${r.Owner || ''}</td>` +
        `<td>${r.Version || ''}</td>` +
        `<td>${r.Email || ''}</td>` +
        `<td>${r.Phone || ''}</td>` +
        `<td>${r.Edition || ''}</td>` +
        `<td>${r.PaymentStatus || ''}</td>` +
        `<td>${r.DeliveryStatus || ''}</td>` +
        `<td>${(r.DeliveryLog || '').substring(0,180)}</td>` +
        `<td>${r.ProductName || ''}</td>` +
        `<td>${r.TenorDays ?? ''}</td>` +
        `<td>${r.IsActivated ? 'Yes' : 'No'}</td>` +
        `<td>${toUtcString(r.ActivationDate)}</td>` +
        `<td>${toUtcString(r.ExpiresAt)}</td>` +
        `<td>${r.MaxhineId || ''}</td>` +
        `<td>${r.DeviceId || ''}</td>` +
        `<td>${r.MaxSeatsShopeeScrap ?? ''}</td>` +
        `<td>${r.UsedSeatsShopeeScrap ?? ''}</td>` +
        `<td>${toUtcString(r.LastUsed)}</td>`;
      tbody.appendChild(tr);
    }
    dt = $('#tblLicenses').DataTable({ responsive: true, pageLength: 25, order: [[1, 'desc']] });
  }
  
  // Delegated events untuk tombol di dalam tabel (aman untuk DataTables paging/sorting)
  $('#tblLicenses tbody').on('click', '.btn-edit', async function () {
      const id = this.getAttribute('data-id');
      const data = await api.one(id);
      fillForm(data);
      $('#editModal').modal('show');
  });

  $('#tblLicenses tbody').on('click', '.btn-del', async function () {
      const id = this.getAttribute('data-id');
      if (confirm(`Delete ${id}?`)) {
          try {
              await api.del(id, false);
              await reload();
          } catch(e) {
              alert('Gagal menghapus: ' + e.message);
          }
      }
  });

  $('#tblLicenses tbody').on('click', '.btn-topup', async function () {
      const id = this.getAttribute('data-id');
      openVoModal(id);
  });

  async function reload() { const q = document.getElementById('txtSearch').value.trim(); const rows = await api.list(q || null); renderRows(rows); }
  function fillForm(d) {
    document.getElementById('OrderId').value = d.OrderId || '';
    document.getElementById('LicenseKey').value = d.LicenseKey || '';
    document.getElementById('Owner').value = d.Owner || '';
    document.getElementById('Email').value = d.Email || '';
    document.getElementById('Phone').value = d.Phone || '';
    document.getElementById('Edition').value = d.Edition || '';
    document.getElementById('PaymentStatus').value = d.PaymentStatus || '';
    document.getElementById('ProductName').value = d.ProductName || '';
    document.getElementById('TenorDays').value = d.TenorDays ?? '';
    document.getElementById('MaxSeats').value = d.MaxSeats ?? '';
    document.getElementById('MaxVideo').value = d.MaxVideo ?? '';
    document.getElementById('MaxSeatsShopeeScrap').value = d.MaxSeatsShopeeScrap ?? '';
    document.getElementById('UsedSeatsShopeeScrap').value = d.UsedSeatsShopeeScrap ?? '';
    document.getElementById('Features').value = d.Features || '';
    document.getElementById('Status').value = d.Status || '';
    document.getElementById('IsActivated').checked = !!d.IsActivated;
    document.getElementById('ActivationDateLocal').value = toLocalInputFromUtc(d.ActivationDate);
    document.getElementById('ExpiresAtLocal').value = toLocalInputFromUtc(d.ExpiresAt);
    document.getElementById('MachineId').value = d.MaxhineId || '';
    document.getElementById('DeviceId').value = d.DeviceId || '';
    document.getElementById('RowVerBase64').value = d.RowVerBase64 || '';
  }
  document.getElementById('btnSearch').addEventListener('click', reload);
  document.getElementById('btnReload').addEventListener('click', () => { document.getElementById('txtSearch').value = ''; reload(); });
  document.getElementById('frmEdit').addEventListener('submit', async e => {
    e.preventDefault();
    const orderId = document.getElementById('OrderId').value;
    const payload = {
      OrderId: orderId,
      LicenseKey: document.getElementById('LicenseKey').value || null,
      Owner: document.getElementById('Owner').value || null,
      Email: document.getElementById('Email').value || null,
      Phone: document.getElementById('Phone').value || null,
      Edition: document.getElementById('Edition').value || null,
      PaymentStatus: document.getElementById('PaymentStatus').value || null,
      ProductName: document.getElementById('ProductName').value || null,
      TenorDays: toIntOrNull(document.getElementById('TenorDays').value),
      MaxSeats: toIntOrNull(document.getElementById('MaxSeats').value),
      MaxVideo: toIntOrNull(document.getElementById('MaxVideo').value),
      MaxSeatsShopeeScrap: toIntOrNull(document.getElementById('MaxSeatsShopeeScrap').value),
      UsedSeatsShopeeScrap: toIntOrNull(document.getElementById('UsedSeatsShopeeScrap').value),
      Features: document.getElementById('Features').value || null,
      Status: document.getElementById('Status').value || null,
      IsActivated: document.getElementById('IsActivated').checked,
      ActivationDateUtc: localInputToUtcIso(document.getElementById('ActivationDateLocal').value),
      ExpiresAtUtc: localInputToUtcIso(document.getElementById('ExpiresAtLocal').value),
      MachineId: document.getElementById('MachineId').value || null,
      DeviceId: document.getElementById('DeviceId').value || null
    };
    try { await api.update(orderId, payload); $('#editModal').modal('hide'); await reload(); } catch (err) { alert('Gagal menyimpan: ' + err.message); }
  });
  document.getElementById('btnDelete').addEventListener('click', async () => {
    const id = document.getElementById('OrderId').value;
    if (!id) return;
    if (confirm(`Delete ${id}?`)) { await api.del(id, false); $('#editModal').modal('hide'); await reload(); }
  });
  document.getElementById('btnAdd').addEventListener('click', () => { document.getElementById('frmCreate').reset(); $('#createModal').modal('show'); });
  document.getElementById('frmCreate').addEventListener('submit', async e => {
    e.preventDefault();
    const payload = {
      Owner: document.getElementById('C_Owner').value || null,
      Email: document.getElementById('C_Email').value || null,
      Phone: document.getElementById('C_Phone').value || null,
      Edition: document.getElementById('C_Edition').value || null,
      PaymentStatus: document.getElementById('C_PaymentStatus').value || null,
      ProductName: document.getElementById('C_ProductName').value || null,
      TenorDays: toIntOrNull(document.getElementById('C_TenorDays').value),
      MaxSeats: toIntOrNull(document.getElementById('C_MaxSeats').value),
      MaxVideo: toIntOrNull(document.getElementById('C_MaxVideo').value),
      MaxSeatsShopeeScrap: toIntOrNull(document.getElementById('C_MaxSeatsShopeeScrap').value),
      UsedSeatsShopeeScrap: toIntOrNull(document.getElementById('C_UsedSeatsShopeeScrap').value),
      Features: document.getElementById('C_Features').value || null,
      ExpiresAtUtc: localInputToUtcIso(document.getElementById('C_ExpiresAtLocal').value),
      IsActivated: document.getElementById('C_IsActivated').checked,
      MachineId: document.getElementById('C_MachineId').value || null,
      DeviceId: document.getElementById('C_DeviceId').value || null
    };
    try { await api.create(payload); $('#createModal').modal('hide'); await reload(); } catch (err) { alert('Gagal menambah license: ' + err.message); }
  });
  let currentVoOrderId = null;
  function voFmtMin(sec) { return Math.ceil(sec / 60.0); }
  function voMsg(txt, ok) { const el = document.getElementById('voMsg'); el.className = ok ? 'text-success' : 'text-danger'; el.textContent = txt; }
  async function voApi(url, method, body) { const res = await fetch(url, { method: method || 'GET', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: body ? JSON.stringify(body) : undefined }); const txt = await res.text(); try { return { ok: res.ok, code: res.status, json: JSON.parse(txt) }; } catch { return { ok: res.ok, code: res.status, text: txt }; } }
  async function voLoad(orderId) { const r = await voApi('/api/customerlicense/' + encodeURIComponent(orderId) + '/vo', 'GET'); if (r.ok) { const sec = r.json.seconds || 0; document.getElementById('voSec').innerText = sec; document.getElementById('voMin').innerText = voFmtMin(sec); voMsg('Kuota berhasil diambil.', true); } else { voMsg('Gagal ambil kuota (' + r.code + ')', false); } }
  async function openVoModal(orderId) { currentVoOrderId = orderId; document.getElementById('voOrderText').innerText = orderId; document.getElementById('voAddSeconds').value = 1800; document.getElementById('voUseSeconds').value = 60; document.getElementById('voMsg').textContent = ''; await voLoad(orderId); $('#voModal').modal('show'); }
  document.getElementById('btnDoTopup').addEventListener('click', async function () { if (!currentVoOrderId) return voMsg('OrderId kosong.', false); const add = parseInt(document.getElementById('voAddSeconds').value, 10) || 0; if (add <= 0) return voMsg('addSeconds harus > 0', false); this.disabled = true; const r = await voApi('/api/customerlicense/' + encodeURIComponent(currentVoOrderId) + '/vo/topup', 'POST', { addSeconds: add }); this.disabled = false; if (r.ok) { const sec = r.json.seconds_remaining || 0; document.getElementById('voSec').innerText = sec; document.getElementById('voMin').innerText = voFmtMin(sec); voMsg('Top-Up berhasil (+' + add + ' detik).', true); } else { voMsg('Top-Up gagal (' + r.code + ')', false); } });
  document.getElementById('btnDoDebit').addEventListener('click', async function () { if (!currentVoOrderId) return voMsg('OrderId kosong.', false); const use = parseInt(document.getElementById('voUseSeconds').value, 10) || 0; if (use <= 0) return voMsg('secondsUsed harus > 0', false); this.disabled = true; const r = await voApi('/api/customerlicense/' + encodeURIComponent(currentVoOrderId) + '/vo/debit', 'POST', { secondsUsed: use }); this.disabled = false; if (r.ok) { const sec = r.json.seconds_remaining || 0; document.getElementById('voSec').innerText = sec; document.getElementById('voMin').innerText = voFmtMin(sec); voMsg('Debit OK.', true); } else { const msg = (r.json && r.json.message) ? r.json.message : 'Debit gagal (' + r.code + ')'; voMsg(msg, false); } });
  reload();
});