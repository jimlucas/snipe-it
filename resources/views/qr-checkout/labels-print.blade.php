<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>Checkout Labels - {{ ucfirst($type) }}</title>
<style>
@page { size: {{ $labelWidth }}mm {{ $labelHeight }}mm; margin: 0; }
* { box-sizing:border-box; }
html,body { margin:0; padding:0; font-family:Arial,Helvetica,sans-serif; color:#111; }
.toolbar { padding:8px; background:#eee; }
.label { width:{{ $labelWidth }}mm; height:{{ $labelHeight }}mm; padding:3mm; display:flex; align-items:center; gap:2mm; overflow:hidden; page-break-after:always; break-after:page; }
.label img { width:{{ $qrSize }}mm; height:{{ $qrSize }}mm; flex:0 0 auto; object-fit:contain; }
.details { flex:1; min-width:0; }
.type { font-size:8pt; text-transform:uppercase; color:#555; }
.name { font-size:10pt; font-weight:bold; overflow-wrap:anywhere; }
.identifier,.id,.scan { font-size:7.5pt; margin-top:1mm; overflow-wrap:anywhere; }
.scan { font-weight:bold; }
@media screen { body{background:#ddd}.label{background:#fff;margin:10px;border:1px solid #999}.toolbar{position:sticky;top:0;z-index:10} }
@media print { .toolbar{display:none}.label{margin:0;border:0}.label:last-child{page-break-after:auto;break-after:auto} }
</style></head><body>
<div class="toolbar"><button type="button" onclick="window.print()">Print</button> {{ $labelWidth }} × {{ $labelHeight }} mm · QR {{ $qrSize }} mm · {{ $copies }} {{ Str::plural('copy', $copies) }} per item</div>
@foreach ($items as $item)
@for ($copy = 0; $copy < $copies; $copy++)
<div class="label"><img src="{{ route('qr-checkout.image', ['type'=>$type,'id'=>$item->id]) }}" alt="QR code">
<div class="details"><div class="type">{{ ucfirst($type) }}</div><div class="name">{{ $item->name ?: trans('general.none') }}</div>
@if (($item->asset_tag ?? $item->model_number ?? null))<div class="identifier">{{ $item->asset_tag ?? $item->model_number }}</div>@endif
<div class="id">ID: {{ $item->id }}</div><div class="scan">SCAN FOR ACTIONS</div></div></div>
@endfor
@endforeach
</body></html>