@extends('layouts.app')

@section('title','License Logs')

@section('content')
<div class="d-flex align-items-center mb-3">
  <h3 class="mb-0 mr-3">License Logs</h3>
  <div class="input-group" style="max-width:420px;">
    <input id="txtSearch" type="text" class="form-control" placeholder="Search Key / Email / Result...">
    <div class="input-group-append">
      <button id="btnSearch" class="btn btn-primary">Search</button>
      <button id="btnReload" class="btn btn-outline-secondary">Reload</button>
    </div>
  </div>
</div>

<table id="tblLogs" class="display nowrap table table-striped table-hover" style="width:100%">
  <thead>
    <tr>
      <th>Timestamp (UTC)</th>
      <th>Action</th>
      <th>Result</th>
      <th>Email</th>
      <th>LicenseKey</th>
      <th>OrderId</th>
      <th>Message</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>

@push('scripts')
<script src="/js/licenselogs.js?v=20251227"></script>
@endpush
@endsection
