document.addEventListener('DOMContentLoaded', () => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const api = {
    list: q => fetch(`/api/orderdata${q ? ('?q=' + encodeURIComponent(q)) : ''}`).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    one: id => fetch(`/api/orderdata/${encodeURIComponent(id)}`).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    create: payload => fetch(`/api/orderdata`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload) }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    update: (id, payload) => fetch(`/api/orderdata/${encodeURIComponent(id)}`, { method: 'PUT', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload) }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
    del: id => fetch(`/api/orderdata/${encodeURIComponent(id)}`, { method: 'DELETE', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } }).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); }))
  };
  let dt = null;
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toUtcString(isoUtc) { if (!isoUtc) return ''; const s = isoUtc.endsWith('Z') ? isoUtc : isoUtc + 'Z'; const d = new Date(s); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())} ${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`; }
  function toLocalInputFromUtc(isoUtc) { if (!isoUtc) return ''; const s = isoUtc.endsWith('Z') ? isoUtc : isoUtc + 'Z'; const d = new Date(s); return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`; }
  function localInputToUtcIso(localVal) { if (!localVal) return null; const d = new Date(localVal); return new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes(), 0, 0)).toISOString(); }
  async function renderRows(rows) {
    if (dt) { dt.destroy(); dt = null; }
    const tbody = document.querySelector('#tblOrders tbody'); tbody.innerHTML = '';
    for (const r of rows) {
      const tr = document.createElement('tr');
      tr.innerHTML =
        `<td><div class="btn-group btn-group-sm"><button class="btn btn-primary btn-edit" data-id="${r.OrderId}">Edit</button><button class="btn btn-outline-danger btn-del" data-id="${r.OrderId}">Del</button></div></td>` +
        `<td>${r.OrderId || ''}</td>` +
        `<td>${r.Email || ''}</td>` +
        `<td>${r.Phone || ''}</td>` +
        `<td>${r.Name || ''}</td>` +
        `<td>${r.ProductName || ''}</td>` +
        `<td>${r.VariantPrice ?? ''}</td>` +
        `<td>${r.NetRevenue ?? ''}</td>` +
        `<td>${r.Status || ''}</td>` +
        `<td>${toUtcString(r.CreatedAt)}</td>` +
        `<td>${toUtcString(r.UpdatedAt)}</td>`;
      tbody.appendChild(tr);
    }
    dt = $('#tblOrders').DataTable({ responsive: true, pageLength: 25, order: [[9, 'desc']] });
    $('#tblOrders .btn-edit').off('click').on('click', async function () { const id = this.getAttribute('data-id'); const data = await api.one(id); fillForm(data); $('#editModal').modal('show'); });
    $('#tblOrders .btn-del').off('click').on('click', async function () { const id = this.getAttribute('data-id'); if (confirm(`Delete ${id}?`)) { await api.del(id); await reload(); } });
  }
  async function reload() { const q = document.getElementById('txtSearch').value.trim(); const rows = await api.list(q || null); renderRows(rows); }
  function fillForm(d) {
    document.getElementById('OrderId').value = d.OrderId || '';
    document.getElementById('Email').value = d.Email || '';
    document.getElementById('Phone').value = d.Phone || '';
    document.getElementById('Name').value = d.Name || '';
    document.getElementById('ProductName').value = d.ProductName || '';
    document.getElementById('VariantPrice').value = d.VariantPrice ?? '';
    document.getElementById('NetRevenue').value = d.NetRevenue ?? '';
    document.getElementById('Status').value = d.Status || '';
    document.getElementById('CreatedAtLocal').value = toLocalInputFromUtc(d.CreatedAt);
  }
  document.getElementById('btnSearch').addEventListener('click', reload);
  document.getElementById('btnReload').addEventListener('click', () => { document.getElementById('txtSearch').value = ''; reload(); });
  document.getElementById('btnAdd').addEventListener('click', () => { document.getElementById('frmCreate').reset(); $('#createModal').modal('show'); });
  document.getElementById('frmCreate').addEventListener('submit', async e => {
    e.preventDefault();
    const payload = {
      OrderId: document.getElementById('C_OrderId').value || null,
      Email: document.getElementById('C_Email').value || null,
      Phone: document.getElementById('C_Phone').value || null,
      Name: document.getElementById('C_Name').value || null,
      ProductName: document.getElementById('C_ProductName').value || null,
      VariantPrice: parseFloat(document.getElementById('C_VariantPrice').value || '0') || null,
      NetRevenue: parseFloat(document.getElementById('C_NetRevenue').value || '0') || null,
      Status: document.getElementById('C_Status').value || null,
      CreatedAtUtc: localInputToUtcIso(document.getElementById('C_CreatedAtLocal').value)
    };
    try { await api.create(payload); $('#createModal').modal('hide'); await reload(); } catch (err) { alert('Gagal membuat: ' + err.message); }
  });
  document.getElementById('frmEdit').addEventListener('submit', async e => {
    e.preventDefault();
    const id = document.getElementById('OrderId').value;
    const payload = {
      Email: document.getElementById('Email').value || null,
      Phone: document.getElementById('Phone').value || null,
      Name: document.getElementById('Name').value || null,
      ProductName: document.getElementById('ProductName').value || null,
      VariantPrice: parseFloat(document.getElementById('VariantPrice').value || '0') || null,
      NetRevenue: parseFloat(document.getElementById('NetRevenue').value || '0') || null,
      Status: document.getElementById('Status').value || null,
      CreatedAtUtc: localInputToUtcIso(document.getElementById('CreatedAtLocal').value)
    };
    try { await api.update(id, payload); $('#editModal').modal('hide'); await reload(); } catch (err) { alert('Gagal menyimpan: ' + err.message); }
  });
  document.getElementById('btnDelete').addEventListener('click', async () => {
    const id = document.getElementById('OrderId').value; if (!id) return;
    if (confirm(`Delete ${id}?`)) { await api.del(id); $('#editModal').modal('hide'); await reload(); }
  });
  reload();
});
