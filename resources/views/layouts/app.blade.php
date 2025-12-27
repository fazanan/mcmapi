<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','Admin')</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
  <link rel="stylesheet" href="/css/theme.css">
  
</head>
<body>
  <div class="d-flex">
    <aside class="mcm-sidebar">
      <div class="mcm-brand">
        <span class="mcm-logo"></span>
        <div>
          <div class="mcm-title">MCM Admin</div>
          <div class="mcm-subtitle">MesinCuanMaximal</div>
        </div>
      </div>
      <ul class="nav flex-column">
        @auth
          <li class="nav-item"><a class="nav-link {{ Request::is('produk') ? 'active' : '' }}" href="/produk">Produk</a></li>
          @if(strtolower(auth()->user()->role ?? '') === 'admin')
            <li class="nav-item"><a class="nav-link {{ Request::is('users') ? 'active' : '' }}" href="/users">Users</a></li>
            <li class="nav-item"><a class="nav-link {{ Request::is('licenses') ? 'active' : '' }}" href="/licenses">Licenses</a></li>
            <li class="nav-item"><a class="nav-link {{ Request::is('license-logs') ? 'active' : '' }}" href="/license-logs">Logs</a></li>
            <li class="nav-item"><a class="nav-link {{ Request::is('orders') ? 'active' : '' }}" href="/orders">Orders</a></li>
            <li class="nav-item"><a class="nav-link {{ Request::is('config-keys') ? 'active' : '' }}" href="/config-keys">API Keys</a></li>
            <li class="nav-item"><a class="nav-link {{ Request::is('whatsapp-config') ? 'active' : '' }}" href="/whatsapp-config">WhatsApp Config</a></li>
          @endif
        @endauth
      </ul>
      <div class="mcm-user mt-auto">
        @auth
          <div class="small text-muted">{{ auth()->user()->name }} <span class="badge badge-light ml-1">{{ strtoupper(auth()->user()->role ?? 'MEMBER') }}</span></div>
          <form method="POST" action="/logout" class="mt-2">
            @csrf
            <button class="btn btn-sm btn-outline-light btn-block">Keluar</button>
          </form>
        @else
          <a class="btn btn-sm btn-light btn-block" href="/login">Masuk</a>
        @endauth
      </div>
    </aside>
    <main class="mcm-content container-fluid py-3">
      @yield('content')
    </main>
  </div>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  @stack('scripts')
</body>
</html>
