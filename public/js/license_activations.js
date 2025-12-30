document.addEventListener('DOMContentLoaded', () => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const api = {
    list: q => fetch(`/api/license-activations${q ? ('?q=' + encodeURIComponent(q)) : ''}`).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    one: id => fetch(`/api/license-activations/${encodeURIComponent(id)}`).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    create: payload => fetch(`/api/license-activations`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload) }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    update: (id, payload) => fetch(`/api/license-activations/${encodeURIComponent(id)}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload) }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    del: id => fetch(`/api/license-activations/${encodeURIComponent(id)}`, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); }))
  };
  
  let dt = null;
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toUtcString(isoUtc) { if (!isoUtc) return ''; const s = isoUtc.endsWith('Z') ? isoUtc : isoUtc + 'Z'; const d = new Date(s); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())} ${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`; }
  function toLocalInputFromUtc(isoUtc) { if (!isoUtc) return ''; const s = isoUtc.endsWith('Z') ? isoUtc : isoUtc + 'Z'; const d = new Date(s); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`; }
  function localInputToUtcIso(localVal) { if (!localVal) return null; const d = new Date(localVal); return new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes(), 0, 0)).toISOString(); }
  function toIntOrNull(v) { if (v == null || v === '') return null; const n = parseInt(v, 10); return isNaN(n) ? null : n; }

  async function renderRows(rows) {
    if (dt) { dt.destroy(); dt = null; }
    const tbody = document.querySelector('#tblActivations tbody'); tbody.innerHTML = '';
    for (const r of rows) {
      const tr = document.createElement('tr');
      tr.innerHTML =
        `<td><div class="btn-group btn-group-sm"><button class="btn btn-primary btn-edit" data-id="${r.id}">Edit</button><button class="btn btn-outline-danger btn-del" data-id="${r.id}">Del</button></div></td>` +
        `<td>${r.id}</td>` +
        `<td>${r.license_key}</td>` +
        `<td>${r.device_id}</td>` +
        `<td>${r.product_name}</td>` +
        `<td>${toUtcString(r.activated_at)}</td>` +
        `<td>${toUtcString(r.last_seen_at)}</td>` +
        `<td>${r.revoked ? 'Yes' : 'No'}</td>`;
      tbody.appendChild(tr);
    }
    dt = $('#tblActivations').DataTable({ responsive: true, pageLength: 25, order: [[1, 'desc']] });
  }

  $('#tblActivations tbody').on('click', '.btn-edit', async function () {
    const id = this.getAttribute('data-id');
    try {
      const data = await api.one(id);
      fillForm(data);
      $('#editModal').modal('show');
    } catch(e) { alert('Gagal memuat: ' + e.message); }
  });

  $('#tblActivations tbody').on('click', '.btn-del', async function () {
    const id = this.getAttribute('data-id');
    if (confirm(`Delete ID ${id}?`)) {
        try {
            await api.del(id);
            await reload();
        } catch(e) { alert('Gagal hapus: ' + e.message); }
    }
  });

  async function reload() { const q = document.getElementById('txtSearch').value.trim(); try { const rows = await api.list(q || null); renderRows(rows); } catch(e){ alert('Gagal reload: ' + e.message); } }
  
  function fillForm(d) {
    document.getElementById('ActivationId').value = d.id;
    document.getElementById('LicenseKey').value = d.license_key;
    document.getElementById('DeviceId').value = d.device_id;
    document.getElementById('ProductName').value = d.product_name;
    document.getElementById('chkRevoked').checked = d.revoked;
    document.getElementById('ActivatedAtLocal').value = toLocalInputFromUtc(d.activated_at);
    document.getElementById('LastSeenAtLocal').value = toLocalInputFromUtc(d.last_seen_at);
  }

  document.getElementById('btnSearch').addEventListener('click', reload);
  document.getElementById('btnReload').addEventListener('click', () => { document.getElementById('txtSearch').value = ''; reload(); });

  document.getElementById('frmEdit').addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('ActivationId').value;
    const payload = {
        license_key: document.getElementById('LicenseKey').value,
        device_id: document.getElementById('DeviceId').value,
        product_name: document.getElementById('ProductName').value,
        revoked: document.getElementById('chkRevoked').checked,
        activated_at: localInputToUtcIso(document.getElementById('ActivatedAtLocal').value),
        last_seen_at: localInputToUtcIso(document.getElementById('LastSeenAtLocal').value)
    };
    try {
        await api.update(id, payload);
        $('#editModal').modal('hide');
        await reload();
    } catch(ex) { alert('Error: ' + ex.message); }
  });

  document.getElementById('btnDelete').addEventListener('click', async () => {
    const id = document.getElementById('ActivationId').value;
    if (!id) return;
    if (confirm(`Delete ID ${id}?`)) { await api.del(id); $('#editModal').modal('hide'); await reload(); }
  });

  document.getElementById('btnAdd').addEventListener('click', () => {
    document.getElementById('frmCreate').reset();
    document.getElementById('NewActivatedAtLocal').value = toLocalInputFromUtc(new Date().toISOString());
    document.getElementById('NewLastSeenAtLocal').value = toLocalInputFromUtc(new Date().toISOString());
    $('#createModal').modal('show');
  });

  document.getElementById('frmCreate').addEventListener('submit', async function(e) {
    e.preventDefault();
    const payload = {
        license_key: document.getElementById('NewLicenseKey').value,
        device_id: document.getElementById('NewDeviceId').value,
        product_name: document.getElementById('NewProductName').value,
        revoked: document.getElementById('NewChkRevoked').checked,
        activated_at: localInputToUtcIso(document.getElementById('NewActivatedAtLocal').value),
        last_seen_at: localInputToUtcIso(document.getElementById('NewLastSeenAtLocal').value)
    };
    try {
        await api.create(payload);
        $('#createModal').modal('hide');
        await reload();
    } catch(ex) { alert('Error: ' + ex.message); }
  });

  reload();
});
