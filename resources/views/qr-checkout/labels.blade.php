@extends('layouts/default')

@section('title')
    Checkout Labels - {{ ucfirst($type) }}
    @parent
@stop

@section('content')
<x-container><x-box>
<div class="box-header with-border"><h3 class="box-title">Select {{ ucfirst($type) }} items to label</h3></div>
<div class="box-body">

@if (! $settings->label2_enable)
<div class="alert alert-warning">
    <strong>Enhanced Label Engine required.</strong>
    QR Checkout Labels use the label template configured under
    <a href="{{ route('settings.labels.index') }}">Settings &rarr; Barcodes &amp; Labels</a>.
    Enable the Enhanced Label Engine and select a label template before printing checkout labels.
</div>
@else
<div class="callout callout-info">
    <strong>Label template:</strong> {{ $settings->label2_template }}.
    Physical label size, sheet layout, margins, orientation, and printer stock are provided by Snipe-IT's Enhanced Label Engine.
    <a href="{{ route('settings.labels.index') }}">Change label settings</a>.
</div>

<form method="POST" action="{{ route('qr-checkout.labels.print', ['type' => $type]) }}" target="_blank">
@csrf
<div class="row">
<div class="col-md-2 form-group">
<label for="copies">Copies per item</label>
<input class="form-control" id="copies" name="copies" type="number" min="1" max="100" value="1">
</div>
</div>

<div class="table-responsive"><table class="table table-striped"><thead><tr><th style="width:40px;"><input type="checkbox" id="select-all-labels"></th><th>Name</th><th>Identifier</th><th>ID</th></tr></thead><tbody>
@forelse ($items as $item)
<tr><td><input type="checkbox" class="checkout-label-item" name="ids[]" value="{{ $item->id }}"></td><td>{{ $item->name ?: trans('general.none') }}</td><td>{{ $item->asset_tag ?? $item->model_number ?? '' }}</td><td>{{ $item->id }}</td></tr>
@empty <tr><td colspan="4" class="text-muted">No items available.</td></tr> @endforelse
</tbody></table></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf fa-fw"></i> Generate Selected Labels</button>
</form>
@endif

<div class="text-center">{{ $items->links() }}</div>
</div></x-box></x-container>
@stop

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
(function () {
    document.getElementById('select-all-labels')?.addEventListener('change', function () {
        document.querySelectorAll('.checkout-label-item').forEach(c => c.checked = this.checked);
    });
})();
</script>
@stop
