<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>Checkout Labels - {{ ucfirst($type) }}</title>
    <style>
        @page { margin: 0.25in; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111; }
        .toolbar { margin-bottom: 12px; }
        .labels { display: flex; flex-wrap: wrap; gap: 0.12in; align-items: flex-start; }
        .label { width: 2.5in; min-height: 1.35in; border: 1px solid #999; box-sizing: border-box; padding: 0.08in; display: flex; gap: 0.08in; page-break-inside: avoid; }
        .label img { width: 1.05in; height: 1.05in; object-fit: contain; }
        .details { flex: 1; min-width: 0; }
        .type { font-size: 9pt; text-transform: uppercase; color: #555; margin-bottom: 3px; }
        .name { font-size: 11pt; font-weight: bold; overflow-wrap: anywhere; }
        .identifier { font-size: 9pt; margin-top: 5px; overflow-wrap: anywhere; }
        .id { font-size: 8pt; margin-top: 4px; color: #666; }
        @media print {
            .toolbar { display: none; }
            .label { border-color: #bbb; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="labels">
        @foreach ($items as $item)
            <div class="label">
                <img src="{{ route('qr-checkout.image', ['type' => $type, 'id' => $item->id]) }}" alt="QR code for {{ $item->name ?: ('#'.$item->id) }}">
                <div class="details">
                    <div class="type">{{ ucfirst($type) }}</div>
                    <div class="name">{{ $item->name ?: trans('general.none') }}</div>
                    @if (($item->asset_tag ?? $item->model_number ?? null))
                        <div class="identifier">{{ $item->asset_tag ?? $item->model_number }}</div>
                    @endif
                    <div class="id">ID: {{ $item->id }}</div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
