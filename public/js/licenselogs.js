document.addEventListener('DOMContentLoaded', () => {
  const api = {
    list: q => fetch(`/api/license-logs${q ? ('?q=' + encodeURIComponent(q)) : ''}`).then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t); })),
  };

  let dt = null;
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toUtcString(isoUtc) { 
    if (!isoUtc) return ''; 
    const s = isoUtc.endsWith('Z') ? isoUtc : isoUtc + 'Z'; 
    const d = new Date(s); 
    return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())} ${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:${pad(d.getUTCSeconds())}`; 
  }

  async function renderRows(rows) {
    if (dt) { dt.destroy(); dt = null; }
    const tbody = document.querySelector('#tblLogs tbody');
    tbody.innerHTML = '';
    for (const r of rows) {
      const tr = document.createElement('tr');
      // Style row based on result
      if (r.result === 'Failed') tr.classList.add('table-danger');
      else if (r.result === 'Success') tr.classList.add('table-success');

      tr.innerHTML =
        `<td>${toUtcString(r.created_at)}</td>` +
        `<td>${r.action || ''}</td>` +
        `<td>${r.result || ''}</td>` +
        `<td>${r.email || ''}</td>` +
        `<td>${r.license_key || ''}</td>` +
        `<td>${r.order_id || ''}</td>` +
        `<td>${r.message || ''}</td>`;
      tbody.appendChild(tr);
    }
    dt = $('#tblLogs').DataTable({ 
      responsive: true, 
      pageLength: 25, 
      order: [[0, 'desc']] // Sort by timestamp desc
    });
  }

  async function reload() {
    try {
      const q = document.getElementById('txtSearch').value.trim();
      const rows = await api.list(q || null);
      renderRows(rows);
    } catch (err) {
      alert('Gagal memuat log: ' + err.message);
    }
  }

  document.getElementById('btnSearch').addEventListener('click', reload);
  document.getElementById('btnReload').addEventListener('click', () => { 
    document.getElementById('txtSearch').value = ''; 
    reload(); 
  });

  // Initial load
  reload();
});
