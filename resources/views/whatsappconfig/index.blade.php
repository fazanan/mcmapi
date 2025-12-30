@extends('layouts.app')

@section('title','WhatsApp API Config')

@section('content')
<div class="d-flex align-items-center mb-3">
  <h3 class="mb-0 mr-3">WhatsApp API Config</h3>
  <button id="btnAdd" class="btn btn-success mr-3">+ Add Config</button>
  <div class="input-group" style="max-width:420px;">
    <input id="txtSearch" type="text" class="form-control" placeholder="Search Secret / Account">
    <div class="input-group-append">
      <button id="btnSearch" class="btn btn-primary">Search</button>
      <button id="btnReload" class="btn btn-outline-secondary">Reload</button>
    </div>
  </div>
  <button id="btnLatest" class="btn btn-outline-info ml-3">Use Latest</button>
  <span id="msg" class="ml-3"></span>
  </div>

<table id="tblWA" class="display nowrap table table-striped table-hover" style="width:100%">
  <thead>
    <tr>
      <th>Actions</th>
      <th>Id</th>
      <th>ApiSecret</th>
      <th>AccountUniqueId</th>
      <th>GroupLink</th>
      <th>InstallerLink</th>
      <th>InstallerVersion</th>
      <th>UpdatedAt (UTC)</th>
    </tr>
  </thead>
  <tbody></tbody>
  </table>

<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="frmEdit" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit WhatsApp Config</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body">
        <input type="hidden" id="E_Id">
        <div class="form-group"><label>ApiSecret</label><input id="E_ApiSecret" type="text" class="form-control"></div>
        <div class="form-group"><label>AccountUniqueId</label><input id="E_AccountUniqueId" type="text" class="form-control"></div>
        <div class="form-group"><label>GroupLink</label><input id="E_GroupLink" type="text" class="form-control" placeholder="https://chat.whatsapp.com/..."/></div>
        <div class="form-group"><label>InstallerLink</label><input id="E_InstallerLink" type="text" class="form-control" placeholder="https://..."/></div>
        <div class="form-group"><label>InstallerVersion</label><input id="E_InstallerVersion" type="text" class="form-control" placeholder="mis. v1.2.3"/></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="frmCreate" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add WhatsApp Config</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body">
        <div class="form-group"><label>ApiSecret</label><input id="C_ApiSecret" type="text" class="form-control"></div>
        <div class="form-group"><label>AccountUniqueId</label><input id="C_AccountUniqueId" type="text" class="form-control"></div>
        <div class="form-group"><label>GroupLink</label><input id="C_GroupLink" type="text" class="form-control" placeholder="https://chat.whatsapp.com/..."/></div>
        <div class="form-group"><label>InstallerLink</label><input id="C_InstallerLink" type="text" class="form-control" placeholder="https://..."/></div>
        <div class="form-group"><label>InstallerVersion</label><input id="C_InstallerVersion" type="text" class="form-control" placeholder="mis. v1.2.3"/></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-success">Create</button><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button></div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script src="/js/whatsappconfig.js?v=20251230"></script>
@endpush
