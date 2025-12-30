@extends('layouts.app')

@section('title','ConfigApiKey')

@section('content')
<div class="d-flex align-items-center mb-3">
  <h3 class="mb-0 mr-3">ConfigApiKey</h3>
  <button id="btnAdd" class="btn btn-success mr-3">+ Add API Key</button>
  <div class="input-group" style="max-width:420px;">
    <input id="txtSearch" type="text" class="form-control" placeholder="Search Provider / ApiKey / Model / Status">
    <div class="input-group-append">
      <button id="btnSearch" class="btn btn-primary">Search</button>
      <button id="btnReload" class="btn btn-outline-secondary">Reload</button>
    </div>
  </div>
  <button id="btnTest" class="btn btn-outline-info ml-3">Test Keys</button>
  <button id="btnCooldown" class="btn btn-outline-warning ml-2">End Cooldown</button>
  <button id="btnAvailable" class="btn btn-outline-success ml-2">Set AVAILABLE</button>
  <button id="btnInvalid" class="btn btn-outline-danger ml-2">Set INVALID</button>
</div>

<table id="tblKeys" class="display nowrap table table-striped table-hover" style="width:100%">
  <thead>
    <tr>
      <th>Actions</th>
      <th>ApiKeyId</th>
      <th>Provider</th>
      <th>ApiKey</th>
      <th>Model</th>
      <th>DefaultVoiceId</th>
      <th>Status</th>
      <th>CooldownUntil</th>
      <th>UpdatedAt</th>
    </tr>
  </thead>
  <tbody></tbody>
  </table>

<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="frmEdit" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit API Key</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body">
        <input type="hidden" id="ApiKeyId">
        <div class="form-group"><label>Provider</label><input id="JenisApiKey" class="form-control"></div>
        <div class="form-group"><label>ApiKey</label><input id="ApiKey" class="form-control"></div>
        <div class="form-group"><label>Model</label><input id="Model" class="form-control"></div>
        <div class="form-group"><label>DefaultVoiceId</label><input id="DefaultVoiceId" class="form-control"></div>
        <div class="form-group"><label>Status</label><input id="Status" class="form-control"></div>
        <div class="form-group"><label>CooldownUntil (UTC)</label><input id="CooldownUntilPT" type="datetime-local" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save</button><button type="button" id="btnDelete" class="btn btn-danger">Delete</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="frmCreate" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add API Key</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body">
        <div class="form-group"><label>Provider</label><input id="C_JenisApiKey" class="form-control"></div>
        <div class="form-group"><label>ApiKey</label><input id="C_ApiKey" class="form-control"></div>
        <div class="form-group"><label>Model</label><input id="C_Model" class="form-control" value="gemini-2.0-flash"></div>
        <div class="form-group"><label>DefaultVoiceId</label><input id="C_DefaultVoiceId" class="form-control"></div>
        <div class="form-group"><label>Status</label><input id="C_Status" class="form-control" value="AVAILABLE"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Create</button></div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script src="/js/configapikey.js?v=20251230"></script>
@endpush
