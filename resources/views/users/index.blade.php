@extends('layouts.app')

@section('title','Users')

@section('content')
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title">Users</h4>
        <p class="text-muted">Daftar pengguna (admin-only).</p>
        <table id="tblUsers" class="display nowrap" style="width:100%">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Role</th>
              <th>Dibuat</th>
            </tr>
          </thead>
          <tbody>
            @foreach(($users ?? []) as $u)
              <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->phone }}</td>
                <td>{{ ucfirst(strtolower($u->role ?? 'member')) }}</td>
                <td>{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@push('scripts')
<script>
$(function(){
  $('#tblUsers').DataTable({
    responsive: true
  });
});
</script>
@endpush
@endsection

