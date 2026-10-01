@extends('layouts/default')

@section('title')
    Checkout Labels - {{ ucfirst($type) }}
    @parent
@stop

@section('content')
<x-container><x-box>
<div class="box-header with-border"><h3 class="box-title">Select {{ ucfirst($type) }} items to label</h3></div>
<div class="box-body">
<form method="POST" action="{{ route('qr-checkout.labels.print', ['type' => $type]) }}" target="_blank">
@csrf
<div class="row">
<div class="col-md-4 form-group">
<label for="label-preset">Label format</label>
<select class="form-control" id="label-preset" name="label_preset">
<option value="dymo_11354">Dymo 11354 — 57 × 32 mm</option>
<option value="225x125">2.25 × 1.25 in</option>
<option value="250x150">2.5 × 1.5 in</option>
<option value="300x200">3 × 2 in</option>
<option value="custom">Custom dimensions</option>
</select>
</div>
<div class="col-md-3 form-group"><label for="orientation">Orientation</label>
<select class="form-control" id="orientation" name="orientation"><option value="auto">Automatic</option><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></div>
<div class="col-md-2 form-group"><label for="copies">Copies per item</label><input class="form-control" id="copies" name="copies" type="number" min="1" max="100" value="1"></div>
</div>
<div id="custom-label-options" class="well well-sm" style="display:none;">
<div class="row">
<div class="col-md-2 form-group"><label for="label-width">Label width</label><input class="form-control" id="label-width" name="label_width" type="number" min="0.6" max="300" step="0.01" value="57"></div>
<div class="col-md-2 form-group"><label for="label-height">Label height</label><input class="form-control" id="label-height" name="label_height" type="number" min="0.6" max="300" step="0.01" value="32"></div>
<div class="col-md-2 form-group"><label for="qr-size">QR size</label><input class="form-control" id="qr-size" name="qr_size" type="number" min="0.4" max="200" step="0.01" value="25"></div>
<div class="col-md-2 form-group"><label for="units">Units</label><select class="form-control" id="units" name="units"><option value="mm">mm</option><option value="in">inches</option></select></div>
<div class="col-md-4"><label>Preview</label><div id="label-preview" style="width:228px;height:128px;border:1px solid #999;background:#fff;padding:8px;display:flex;align-items:center;gap:8px;"><div id="qr-preview" style="width:80px;height:80px;border:4px solid #111;background:repeating-linear-gradient(45deg,#111 0,#111 5px,#fff 5px,#fff 10px);"></div><small>QR checkout<br>label preview</small></div></div>
</div></div>

<div class="table-responsive"><table class="table table-striped"><thead><tr><th style="width:40px;"><input type="checkbox" id="select-all-labels"></th><th>Name</th><th>Identifier</th><th>ID</th></tr></thead><tbody>
@forelse ($items as $item)
<tr><td><input type="checkbox" class="checkout-label-item" name="ids[]" value="{{ $item->id }}"></td><td>{{ $item->name ?: trans('general.none') }}</td><td>{{ $item->asset_tag ?? $item->model_number ?? '' }}</td><td>{{ $item->id }}</td></tr>
@empty <tr><td colspan="4" class="text-muted">No items available.</td></tr> @endforelse
</tbody></table></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-print fa-fw"></i> Print Selected Labels</button>
</form>
<div class="text-center">{{ $items->links() }}</div>
</div></x-box></x-container>
@stop

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
(function () {
 const preset=document.getElementById('label-preset'), custom=document.getElementById('custom-label-options');
 const unit=document.getElementById('units'), w=document.getElementById('label-width'), h=document.getElementById('label-height'), q=document.getElementById('qr-size');
 const preview=document.getElementById('label-preview'), qr=document.getElementById('qr-preview');
 document.getElementById('select-all-labels')?.addEventListener('change',function(){document.querySelectorAll('.checkout-label-item').forEach(c=>c.checked=this.checked);});
 function update(){
   custom.style.display=preset.value==='custom'?'block':'none';
   if(preset.value!=='custom') return;
   const factor=unit.value==='in'?25.4:1, wm=parseFloat(w.value||0)*factor, hm=parseFloat(h.value||0)*factor, qm=parseFloat(q.value||0)*factor;
   if(!wm||!hm) return;
   const scale=Math.min(240/wm,140/hm);
   preview.style.width=Math.max(60,wm*scale)+'px'; preview.style.height=Math.max(40,hm*scale)+'px';
   const qs=Math.min(qm*scale,Math.min(wm,hm)*scale-12); qr.style.width=Math.max(20,qs)+'px'; qr.style.height=Math.max(20,qs)+'px';
 }
 [preset,unit,w,h,q].forEach(e=>e.addEventListener('change',update)); [w,h,q].forEach(e=>e.addEventListener('input',update)); update();
})();
</script>
@stop
