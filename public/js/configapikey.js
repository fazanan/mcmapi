document.addEventListener('DOMContentLoaded', () => {
  const api = {
    list: q => fetch(`/api/configapikey${q ? ('?q=' + encodeURIComponent(q)) : ''}`).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    one: id => fetch(`/api/configapikey/${encodeURIComponent(id)}`).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    create: payload => fetch(`/api/configapikey`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    update: (id, payload) => fetch(`/api/configapikey/${encodeURIComponent(id)}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    del: id => fetch(`/api/configapikey/${encodeURIComponent(id)}`, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); }))
  };
  let dt = null;
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toUtcString(isoUtc) { if (!isoUtc) return ''; const s = isoUtc.endsWith('Z') ? isoUtc : isoUtc + 'Z'; const d = new Date(s); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())} ${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`; }
  function toLocalInputFromUtc(isoUtc) { if (!isoUtc) return ''; const s = isoUtc.endsWith('Z') ? isoUtc : isoUtc + 'Z'; const d = new Date(s); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`; }
  function localInputToUtcIso(localVal) { if (!localVal) return null; const d = new Date(localVal); return new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes(), 0, 0)).toISOString(); }
  async function renderRows(rows) {
    if (dt) { dt.destroy(); dt = null; }
    const tbody = document.querySelector('#tblKeys tbody'); tbody.innerHTML = '';
    for (const r of rows) {
      const tr = document.createElement('tr');
      const mask = r.ApiKey ? (r.ApiKey.slice(0, 4) + '...' + r.ApiKey.slice(-4)) : '';
      tr.innerHTML =
        `<td><div class="btn-group btn-group-sm"><button class="btn btn-primary btn-edit" data-id="${r.ApiKeyId}">Edit</button><button class="btn btn-outline-danger btn-del" data-id="${r.ApiKeyId}">Del</button></div></td>` +
        `<td>${r.ApiKeyId ?? ''}</td>` +
        `<td>${r.JenisApiKey || ''}</td>` +
        `<td>${mask}</td>` +
        `<td>${r.Model || ''}</td>` +
        `<td>${r.DefaultVoiceId || ''}</td>` +
        `<td>${r.Status || ''}</td>` +
        `<td>${toUtcString(r.CooldownUntilPT)}</td>` +
        `<td>${toUtcString(r.UpdatedAt)}</td>`;
      tbody.appendChild(tr);
    }
    dt = $('#tblKeys').DataTable({ responsive: true, pageLength: 25, order: [[1, 'desc']] });
    $('#tblKeys .btn-edit').off('click').on('click', async function () { const id = this.getAttribute('data-id'); const data = await api.one(id); fillForm(data); $('#editModal').modal('show'); });
    $('#tblKeys .btn-del').off('click').on('click', async function () { const id = this.getAttribute('data-id'); if (confirm(`Delete ${id}?`)) { await api.del(id); await reload(); } });
  }
  async function reload() { const q = document.getElementById('txtSearch').value.trim(); const rows = await api.list(q || null); renderRows(rows); }
  function fillForm(d) {
    document.getElementById('ApiKeyId').value = d.ApiKeyId ?? '';
    document.getElementById('JenisApiKey').value = d.JenisApiKey || '';
    document.getElementById('ApiKey').value = d.ApiKey || '';
    document.getElementById('Model').value = d.Model || '';
    document.getElementById('DefaultVoiceId').value = d.DefaultVoiceId || '';
    document.getElementById('Status').value = d.Status || '';
    document.getElementById('CooldownUntilPT').value = toLocalInputFromUtc(d.CooldownUntilPT);
  }
  document.getElementById('btnSearch').addEventListener('click', reload);
  document.getElementById('btnReload').addEventListener('click', () => { document.getElementById('txtSearch').value = ''; reload(); });
  document.getElementById('btnAdd').addEventListener('click', () => { document.getElementById('frmCreate').reset(); $('#createModal').modal('show'); });
  document.getElementById('frmCreate').addEventListener('submit', async e => {
    e.preventDefault();
    const payload = {
      JenisApiKey: document.getElementById('C_JenisApiKey').value || null,
      ApiKey: document.getElementById('C_ApiKey').value || null,
      Model: document.getElementById('C_Model').value || null,
      DefaultVoiceId: document.getElementById('C_DefaultVoiceId').value || null,
      Status: document.getElementById('C_Status').value || null,
    };
    try { await api.create(payload); $('#createModal').modal('hide'); await reload(); } catch (err) { alert('Gagal membuat: ' + err.message); }
  });
  document.getElementById('frmEdit').addEventListener('submit', async e => {
    e.preventDefault();
    const id = document.getElementById('ApiKeyId').value;
    const payload = {
      JenisApiKey: document.getElementById('JenisApiKey').value || null,
      ApiKey: document.getElementById('ApiKey').value || null,
      Model: document.getElementById('Model').value || null,
      DefaultVoiceId: document.getElementById('DefaultVoiceId').value || null,
      Status: document.getElementById('Status').value || null,
      CooldownUntilPT: localInputToUtcIso(document.getElementById('CooldownUntilPT').value)
    };
    try { await api.update(id, payload); $('#editModal').modal('hide'); await reload(); } catch (err) { alert('Gagal menyimpan: ' + err.message); }
  });
  document.getElementById('btnDelete').addEventListener('click', async () => {
    const id = document.getElementById('ApiKeyId').value; if (!id) return;
    if (confirm(`Delete ${id}?`)) { await api.del(id); $('#editModal').modal('hide'); await reload(); }
  });
  document.getElementById('btnTest').addEventListener('click', async () => { try { await fetch('/artisan/apikey:test'); alert('Job dispatched'); } catch { alert('Gagal dispatch'); } });
  document.getElementById('btnCooldown').addEventListener('click', async () => { try { await fetch('/artisan/check:cooldown'); alert('Job dispatched'); } catch { alert('Gagal dispatch'); } });
  document.getElementById('btnAvailable').addEventListener('click', async () => {
    const tbody = document.querySelector('#tblKeys tbody'); const sel = tbody.querySelector('tr.selected'); if (!sel) { alert('Pilih baris'); return; }
  });
  $('#tblKeys tbody').on('click', 'tr', function () { $(this).toggleClass('selected'); });
  reload();
});