@extends('layouts/default')

@section('title')
    QR {{ ucfirst($type) }} Transaction
@parent
@stop

@section('header_right')
    <a href="{{ $labelUrl }}" class="btn btn-default pull-right">
        <i class="fas fa-qrcode" aria-hidden="true"></i> {{ trans('general.labels') }}
    </a>
@stop

@section('content')
<x-container columns="2">
<x-page-column class="col-md-7">
<x-box header="{{ $item->name ?: ucfirst($type).' #'.$item->id }}">

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($supportsQuickTransaction)
<form method="POST" action="{{ route('qr-checkout.transaction', ['type' => $type, 'id' => $item->id]) }}" id="qr-transaction-form">
@csrf

<div class="row" style="margin-bottom:20px;">
<div class="col-xs-6 text-center">
<div class="text-muted">Available</div>
<div style="font-size:32px;font-weight:bold;">{{ $available }}</div>
</div>
<div class="col-xs-6 text-center">
<div class="text-muted">Checked out to you</div>
<div id="held-current" style="font-size:32px;font-weight:bold;">{{ $heldByCurrentUser }}</div>
</div>
</div>

<div class="form-group text-center">
<div class="btn-group btn-group-lg" data-toggle="buttons">
<label class="btn btn-primary active" id="action-checkout-label">
<input type="radio" name="action" value="checkout" checked> Check Out
</label>
<label class="btn btn-default" id="action-checkin-label">
<input type="radio" name="action" value="checkin"> Check In
</label>
</div>
</div>

<div class="form-group">
<label for="quantity">Quantity</label>
<div class="input-group input-group-lg" style="max-width:300px;">
<span class="input-group-btn"><button type="button" class="btn btn-default" id="qty-minus">−</button></span>
<input class="form-control text-center" type="number" name="quantity" id="quantity" value="1" min="1" max="{{ max(1, $available) }}">
<span class="input-group-btn"><button type="button" class="btn btn-default" id="qty-plus">+</button></span>
</div>
<div id="qty-presets" style="margin-top:10px;">
@foreach ([1, 2, 5, 10] as $preset)
<button type="button" class="btn btn-default qty-preset" data-qty="{{ $preset }}">{{ $preset }}</button>
@endforeach
<button type="button" class="btn btn-default" id="qty-all">All</button>
</div>
</div>

<div class="form-group">
<label>Checking <span id="direction-word">out to</span></label>
<div id="current-user-display" class="well well-sm">
<strong>{{ $currentUser->display_name }}</strong>
@if ($currentUser->username)
<span class="text-muted">({{ $currentUser->username }})</span>
@endif
</div>
<button type="button" class="btn btn-default" id="override-user-btn">
<i class="fas fa-user-edit fa-fw"></i> Override user
</button>
<div id="override-user-panel" style="display:none;margin-top:15px;">
<x-input.user-select
    :label="trans('general.user')"
    name="assigned_to"
    :selected="old('assigned_to')"
    :companyId="$item->company_id"
/>
<p class="help-block">Leave blank to use the currently logged-in user.</p>
</div>
</div>

<button type="submit" class="btn btn-primary btn-lg btn-block" id="transaction-submit">
<i class="fas fa-sign-out-alt fa-fw"></i> <span id="submit-text">Check Out 1</span>
</button>
</form>
@else
<div class="text-center" style="padding:20px 10px;">
<p class="lead">Quick quantity transactions are currently available for Accessories and Consumables.</p>
<p><a href="{{ $checkoutUrl }}" class="btn btn-primary btn-lg"><i class="fas fa-sign-out-alt"></i> Continue to {{ trans('general.checkout') }}</a></p>
</div>
@endif

</x-box>
</x-page-column>
</x-container>
@stop

@section('moar_scripts')
@if ($supportsQuickTransaction)
<script nonce="{{ csrf_token() }}">
(function () {
    const quantity = document.getElementById('quantity');
    const checkout = document.querySelector('input[name="action"][value="checkout"]');
    const checkin = document.querySelector('input[name="action"][value="checkin"]');
    const submit = document.getElementById('transaction-submit');
    const submitText = document.getElementById('submit-text');
    const direction = document.getElementById('direction-word');
    const available = {{ (int) $available }};
    const held = {{ (int) $heldByCurrentUser }};

    function maxQty() { return checkin.checked ? held : available; }
    function refresh() {
        const max = Math.max(1, maxQty());
        quantity.max = max;
        let value = parseInt(quantity.value || '1', 10);
        value = Math.max(1, Math.min(value, max));
        quantity.value = value;
        direction.textContent = checkin.checked ? 'in from' : 'out to';
        submitText.textContent = (checkin.checked ? 'Check In ' : 'Check Out ') + value;
        submit.classList.toggle('btn-success', checkin.checked);
        submit.classList.toggle('btn-primary', !checkin.checked);
        submit.disabled = maxQty() < 1;
        document.querySelectorAll('.qty-preset').forEach(function (button) {
            button.disabled = parseInt(button.dataset.qty, 10) > maxQty();
        });
        document.getElementById('qty-all').disabled = maxQty() < 1;
    }

    document.querySelectorAll('input[name="action"]').forEach(el => el.addEventListener('change', refresh));
    quantity.addEventListener('input', refresh);
    document.getElementById('qty-minus').addEventListener('click', () => { quantity.value = Math.max(1, parseInt(quantity.value || '1', 10) - 1); refresh(); });
    document.getElementById('qty-plus').addEventListener('click', () => { quantity.value = Math.min(Math.max(1, maxQty()), parseInt(quantity.value || '1', 10) + 1); refresh(); });
    document.querySelectorAll('.qty-preset').forEach(button => button.addEventListener('click', () => { quantity.value = button.dataset.qty; refresh(); }));
    document.getElementById('qty-all').addEventListener('click', () => { if (maxQty() > 0) quantity.value = maxQty(); refresh(); });
    document.getElementById('override-user-btn').addEventListener('click', () => {
        const panel = document.getElementById('override-user-panel');
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    });
    refresh();
})();
</script>
@endif
@stop
