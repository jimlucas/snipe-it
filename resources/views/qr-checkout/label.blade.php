@extends('layouts/default')

@section('title')
    {{ trans('general.labels') }}
@parent
@stop

@section('header_right')
    <button type="button" class="btn btn-primary pull-right" onclick="window.print();">
        <i class="fas fa-print" aria-hidden="true"></i>
        {{ trans('general.print') }}
    </button>
@stop

@section('content')
<style>
    .qr-checkout-label {
        width: 3in;
        min-height: 2in;
        border: 1px solid #999;
        padding: 0.15in;
        margin: 20px auto;
        text-align: center;
        background: #fff;
        color: #000;
    }

    .qr-checkout-label img {
        width: 1.25in;
        height: 1.25in;
    }

    .qr-checkout-label .item-type {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .qr-checkout-label .item-name {
        font-size: 16px;
        font-weight: 700;
        margin-top: 5px;
    }

    .qr-checkout-label .item-id {
        font-size: 11px;
        margin-top: 3px;
    }

    @media print {
        header,
        footer,
        .main-header,
        .main-sidebar,
        .content-header,
        .btn,
        .no-print {
            display: none !important;
        }

        .content-wrapper,
        .content,
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }

        .qr-checkout-label {
            border: none;
            margin: 0;
        }
    }
</style>

<div class="qr-checkout-label">
    <div class="item-type">{{ ucfirst($type) }}</div>
    <div class="item-name">{{ $item->name ?: ucfirst($type).' #'.$item->id }}</div>

    @if ($type === 'asset' && !empty($item->asset_tag))
        <div class="item-id">{{ trans('general.asset_tag') }}: {{ $item->asset_tag }}</div>
    @else
        <div class="item-id">ID: {{ $item->id }}</div>
    @endif

    <div style="margin-top: 8px;">
        <img src="{{ $qrImageUrl }}" alt="QR checkout code">
    </div>
</div>
@stop
