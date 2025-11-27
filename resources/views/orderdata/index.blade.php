@extends('layouts.app')

@section('title','Orders')

@section('content')
<div class="d-flex align-items-center mb-3">
  <h3 class="mb-0 mr-3">Orders</h3>
  <button id="btnAdd" class="btn btn-success mr-3">+ Add Order</button>
  <div class="input-group" style="max-width:460px;">
    <input id="txtSearch" type="text" class="form-control" placeholder="Search OrderId / Email / Phone / Name / Product">
    <div class="input-group-append">
      <button id="btnSearch" class="btn btn-primary">Search</button>
      <button id="btnReload" class="btn btn-outline-secondary">Reload</button>
    </div>
  </div>
</div>

<table id="tblOrders" class="display nowrap table table-striped table-hover" style="width:100%">
  <thead>
    <tr>
      <th>Actions</th>
      <th>OrderId</th>
      <th>Email</th>
      <th>Phone</th>
      <th>Name</th>
      <th>Product</th>
      <th>VariantPrice</th>
      <th>NetRevenue</th>
      <th>Status</th>
      <th>CreatedAt (UTC)</th>
      <th>UpdatedAt (UTC)</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>

<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="frmEdit" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Order</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body">
        <div class="form-group"><label>OrderId</label><input id="OrderId" class="form-control" readonly></div>
        <div class="form-group"><label>Email</label><input id="Email" class="form-control"></div>
        <div class="form-group"><label>Phone</label><input id="Phone" class="form-control"></div>
        <div class="form-group"><label>Name</label><input id="Name" class="form-control"></div>
        <div class="form-group"><label>ProductName</label><input id="ProductName" class="form-control"></div>
        <div class="form-group"><label>VariantPrice</label><input id="VariantPrice" type="number" step="0.01" class="form-control"></div>
        <div class="form-group"><label>NetRevenue</label><input id="NetRevenue" type="number" step="0.01" class="form-control"></div>
        <div class="form-group"><label>Status</label><input id="Status" class="form-control"></div>
        <div class="form-group"><label>CreatedAt (UTC)</label><input id="CreatedAtLocal" type="datetime-local" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save</button><button type="button" id="btnDelete" class="btn btn-danger">Delete</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="frmCreate" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Order</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body">
        <div class="form-group"><label>OrderId</label><input id="C_OrderId" class="form-control"></div>
        <div class="form-group"><label>Email</label><input id="C_Email" class="form-control"></div>
        <div class="form-group"><label>Phone</label><input id="C_Phone" class="form-control"></div>
        <div class="form-group"><label>Name</label><input id="C_Name" class="form-control"></div>
        <div class="form-group"><label>ProductName</label><input id="C_ProductName" class="form-control"></div>
        <div class="form-group"><label>VariantPrice</label><input id="C_VariantPrice" type="number" step="0.01" class="form-control"></div>
        <div class="form-group"><label>NetRevenue</label><input id="C_NetRevenue" type="number" step="0.01" class="form-control"></div>
        <div class="form-group"><label>Status</label><input id="C_Status" class="form-control" value="Not Paid"></div>
        <div class="form-group"><label>CreatedAt (UTC)</label><input id="C_CreatedAtLocal" type="datetime-local" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Create</button></div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script src="/js/orderdata.js"></script>
@endpush