<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login - MCM Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    body { min-height: 100vh; display:flex; align-items:center; justify-content:center; background: linear-gradient(135deg,#0aa2ff,#12c2a4); }
    .card { width: 100%; max-width: 420px; box-shadow: 0 10px 30px rgba(0,0,0,.2); }
    .brand { font-weight:700; color:#0f2b3a; }
  </style>
  <link rel="icon" type="image/png" href="/favicon.ico">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script>window.csrfToken='{{ csrf_token() }}';</script>
  @stack('head')
  </head>
<body>
  <div class="card">
    <div class="card-body">
      <h4 class="brand mb-3">Masuk ke MCM Admin</h4>
      @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
      @endif
      <form method="POST" action="/login">
        @csrf
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="form-group form-check">
          <input type="checkbox" class="form-check-input" id="remember" name="remember">
          <label class="form-check-label" for="remember">Ingat saya</label>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Masuk</button>
      </form>
    </div>
    <div class="card-footer text-center text-muted">&copy; {{ date('Y') }} MesinCuanMaximal</div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

