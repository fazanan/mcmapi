@extends('layouts.app')

@section('title','Produk')

@section('content')
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title">Produk</h4>
        <p class="text-muted">Halaman produk untuk member. Tambahkan daftar produk Anda di sini.</p>

        <table id="tblProduk" class="display nowrap" style="width:100%">
          <thead>
            <tr>
              <th>Nama Produk</th>
              <th>Kategori</th>
              <th>Harga</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Contoh Produk A</td>
              <td>Digital</td>
              <td>Rp 150.000</td>
              <td>Aktif</td>
            </tr>
            <tr>
              <td>Contoh Produk B</td>
              <td>Fisikal</td>
              <td>Rp 300.000</td>
              <td>Preorder</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@push('scripts')
<script>
$(function(){
  $('#tblProduk').DataTable({
    responsive: true
  });
});
</script>
@endpush
@endsection

