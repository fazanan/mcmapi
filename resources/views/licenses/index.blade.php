@extends('layouts.app')

@section('title','Customer Licenses')

@section('content')
<div class="d-flex align-items-center mb-3">
  <h3 class="mb-0 mr-3">Customer Licenses</h3>
  <button id="btnAdd" class="btn btn-success mr-3">+ Add License</button>
  <div class="input-group" style="max-width:420px;">
    <input id="txtSearch" type="text" class="form-control" placeholder="Search OrderId / Owner / Email / Product...">
    <div class="input-group-append">
      <button id="btnSearch" class="btn btn-primary">Search</button>
      <button id="btnReload" class="btn btn-outline-secondary">Reload</button>
    </div>
  </div>
</div>

<table id="tblLicenses" class="display nowrap table table-striped table-hover" style="width:100%">
  <thead>
    <tr>
      <th>Actions</th>
      <th>Status</th>
      <th>OrderId</th>
      <th>LicenseKey</th>
      <th>Owner</th>
      <th>Version</th>
      <th>Email</th>
      <th>Phone</th>
      <th>Edition</th>
      <th>Payment</th>
      <th>Delivery</th>
      <th>Delivery Log</th>
      <th>Product</th>
      <th>TenorDays</th>
      <th>Activated</th>
      <th>ActivationDate (UTC)</th>
      <th>ExpiresAt (UTC)</th>
      <th>MaxhineId</th>
      <th>DeviceId</th>
      <th>MaxSeatsShopee</th>
      <th>UsedSeatsShopee</th>
      <th>MaxSeatsTikTok</th>
      <th>UsedSeatsTikTok</th>
      <th>MassVoSeat</th>
      <th>Last Used</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>

<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="frmEdit" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit License</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-6"><label>OrderId</label><input id="OrderId" class="form-control" readonly></div>
          <div class="form-group col-md-6"><label>LicenseKey</label><input id="LicenseKey" class="form-control" maxlength="200"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><label>Owner</label><input id="Owner" class="form-control"></div>
          <div class="form-group col-md-4"><label>Email</label><input id="Email" type="email" class="form-control"></div>
          <div class="form-group col-md-4"><label>Phone</label><input id="Phone" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><label>Edition</label><input id="Edition" class="form-control"></div>
          <div class="form-group col-md-4"><label>Payment Status</label><input id="PaymentStatus" class="form-control"></div>
          <div class="form-group col-md-4"><label>Product Name</label><input id="ProductName" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-3"><label>TenorDays</label><input id="TenorDays" type="number" min="0" class="form-control"></div>
          <div class="form-group col-md-3"><label>MaxSeats</label><input id="MaxSeats" type="number" min="0" class="form-control"></div>
          <div class="form-group col-md-3"><label>MaxVideo</label><input id="MaxVideo" type="number" min="0" class="form-control"></div>
          <div class="form-group col-md-3"><label>Status</label><input id="Status" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label>MaxSeats Shopee</label><input id="MaxSeatsShopeeScrap" type="number" min="0" class="form-control"></div>
            <div class="form-group col-md-6"><label>UsedSeats Shopee</label><input id="UsedSeatsShopeeScrap" type="number" min="0" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label>MaxSeats TikTok</label><input id="MaxSeatUploadTiktok" type="number" min="0" class="form-control"></div>
            <div class="form-group col-md-6"><label>UsedSeats TikTok</label><input id="UsedSeatUploadTiktok" type="number" min="0" class="form-control"></div>
        </div>
        <div class="form-group"><label>MassVoSeat</label><input id="MassVoSeat" type="number" min="0" class="form-control"></div>
        <div class="form-group"><label>Features</label><input id="Features" class="form-control" placeholder="comma separated"></div>
        <div class="form-row">
          <div class="form-group col-md-6"><label>ActivationDate (Local)</label><input id="ActivationDateLocal" type="datetime-local" class="form-control"></div>
          <div class="form-group col-md-6"><label>ExpiresAt (Local)</label><input id="ExpiresAtLocal" type="datetime-local" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><div class="form-check mt-4"><input id="IsActivated" type="checkbox" class="form-check-input"><label class="form-check-label" for="IsActivated">Is Activated</label></div></div>
          <div class="form-group col-md-4"><label>MaxhineId</label><input id="MachineId" class="form-control"></div>
          <div class="form-group col-md-4"><label>DeviceId</label><input id="DeviceId" class="form-control"></div>
        </div>
        <input type="hidden" id="RowVerBase64">
      </div>
      <div class="modal-footer">
        <button type="button" id="btnDelete" class="btn btn-danger mr-auto">Delete</button>
        <button type="submit" class="btn btn-primary">Save changes</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
      </div>
    </form>
  </div>
</div>
@push('scripts')
<script src="/js/licenses.js?v=20260113_3"></script>
@endpush

<div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="frmCreate" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add License</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-4"><label>Owner</label><input id="C_Owner" class="form-control"></div>
          <div class="form-group col-md-4"><label>Email</label><input id="C_Email" type="email" class="form-control"></div>
          <div class="form-group col-md-4"><label>Phone</label><input id="C_Phone" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><label>Edition</label><input id="C_Edition" class="form-control"></div>
          <div class="form-group col-md-4"><label>Payment Status</label><input id="C_PaymentStatus" class="form-control"></div>
          <div class="form-group col-md-4"><label>Product Name</label><input id="C_ProductName" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><label>TenorDays</label><input id="C_TenorDays" type="number" min="0" class="form-control"></div>
          <div class="form-group col-md-4"><label>MaxSeats</label><input id="C_MaxSeats" type="number" min="0" class="form-control"></div>
          <div class="form-group col-md-4"><label>MaxVideo</label><input id="C_MaxVideo" type="number" min="0" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label>MaxSeats Shopee</label><input id="C_MaxSeatsShopeeScrap" type="number" min="0" class="form-control"></div>
            <div class="form-group col-md-6"><label>UsedSeats Shopee</label><input id="C_UsedSeatsShopeeScrap" type="number" min="0" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label>MaxSeats TikTok</label><input id="C_MaxSeatUploadTiktok" type="number" min="0" class="form-control"></div>
            <div class="form-group col-md-6"><label>UsedSeats TikTok</label><input id="C_UsedSeatUploadTiktok" type="number" min="0" class="form-control"></div>
        </div>
        <div class="form-group"><label>MassVoSeat</label><input id="C_MassVoSeat" type="number" min="0" class="form-control"></div>
        <div class="form-group"><label>Features</label><input id="C_Features" class="form-control" placeholder="comma separated"></div>
        <div class="form-row">
          <div class="form-group col-md-6"><label>ExpiresAt (Local)</label><input id="C_ExpiresAtLocal" type="datetime-local" class="form-control"></div>
          <div class="form-group col-md-6"><div class="form-check mt-4"><input id="C_IsActivated" type="checkbox" class="form-check-input"><label class="form-check-label" for="C_IsActivated">Is Activated</label></div></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label>MachineId</label><input id="C_MachineId" class="form-control"></div>
            <div class="form-group col-md-6"><label>DeviceId</label><input id="C_DeviceId" class="form-control"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="voModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">Top-Up Voice Over</h4>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div><b>OrderId:</b> <span id="voOrderText">-</span></div>
        <div class="text-muted mt-1">Sisa: <b><span id="voSec">0</span> detik</b> (<span id="voMin">0</span> menit)</div>
        <hr>
        <div class="form-group"><label>Top-up (detik)</label><input id="voAddSeconds" type="number" class="form-control" value="1800" min="1"></div>
        <button id="btnDoTopup" class="btn btn-success btn-block">Top-Up</button>
        <hr>
        <div class="form-group"><label>Test Debit (detik)</label><input id="voUseSeconds" type="number" class="form-control" value="60" min="1" max="60"></div>
        <button id="btnDoDebit" class="btn btn-warning btn-block">Debit</button>
        <div id="voMsg" class="text-info mt-2"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>
@endsection
