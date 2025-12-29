@extends('layouts.app')

@section('title','License Activations')

@section('content')
<div class="d-flex align-items-center mb-3">
  <h3 class="mb-0 mr-3">License Activations</h3>
  <button id="btnAdd" class="btn btn-success mr-3">+ Add Activation</button>
  <div class="input-group" style="max-width:420px;">
    <input id="txtSearch" type="text" class="form-control" placeholder="Search Product / DeviceId / LicenseId...">
    <div class="input-group-append">
      <button id="btnSearch" class="btn btn-primary">Search</button>
      <button id="btnReload" class="btn btn-outline-secondary">Reload</button>
    </div>
  </div>
</div>

<table id="tblActivations" class="display nowrap table table-striped table-hover" style="width:100%">
  <thead>
    <tr>
      <th>Actions</th>
      <th>ID</th>
      <th>License ID</th>
      <th>Device ID</th>
      <th>Product Name</th>
      <th>Activated At</th>
      <th>Last Seen At</th>
      <th>Revoked</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>

<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="frmEdit" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Activation</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ActivationId">
        <div class="form-row">
          <div class="form-group col-md-6"><label>License ID</label><input id="LicenseId" class="form-control" type="number" required></div>
          <div class="form-group col-md-6"><label>Product Name</label><input id="ProductName" class="form-control" maxlength="100" required></div>
        </div>
        <div class="form-group"><label>Device ID</label><input id="DeviceId" class="form-control" maxlength="36" required></div>
        <div class="form-row">
          <div class="form-group col-md-6"><label>Activated At (Local)</label><input id="ActivatedAtLocal" type="datetime-local" class="form-control"></div>
          <div class="form-group col-md-6"><label>Last Seen At (Local)</label><input id="LastSeenAtLocal" type="datetime-local" class="form-control"></div>
        </div>
        <div class="form-group">
            <div class="form-check">
                <input id="Revoked" type="checkbox" class="form-check-input">
                <label class="form-check-label" for="Revoked">Revoked</label>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnDelete" class="btn btn-danger mr-auto">Delete</button>
        <button type="submit" class="btn btn-primary">Save changes</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="frmCreate" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Activation</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-6"><label>License ID</label><input id="C_LicenseId" class="form-control" type="number" required></div>
          <div class="form-group col-md-6"><label>Product Name</label><input id="C_ProductName" class="form-control" maxlength="100" required></div>
        </div>
        <div class="form-group"><label>Device ID</label><input id="C_DeviceId" class="form-control" maxlength="36" required></div>
        <div class="form-row">
            <div class="form-group col-md-6"><label>Activated At (Local)</label><input id="C_ActivatedAtLocal" type="datetime-local" class="form-control"></div>
            <div class="form-group col-md-6"><label>Last Seen At (Local)</label><input id="C_LastSeenAtLocal" type="datetime-local" class="form-control"></div>
        </div>
        <div class="form-group">
            <div class="form-check">
                <input id="C_Revoked" type="checkbox" class="form-check-input">
                <label class="form-check-label" for="C_Revoked">Revoked</label>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script src="/js/license_activations.js?v=20251230"></script>
@endpush
@endsection
