@extends('layouts/default')

@section('title')
    {{ trans('general.checkout') }}
@parent
@stop

@section('header_right')
    <a href="{{ $labelUrl }}" class="btn btn-default pull-right">
        <i class="fas fa-qrcode" aria-hidden="true"></i>
        {{ trans('general.labels') }}
    </a>
@stop

@section('content')
<x-container columns="2">
    <x-page-column class="col-md-7">
        <x-box header="{{ $item->name ?: ucfirst($type).' #'.$item->id }}">
            <div class="text-center" style="padding: 20px 10px;">
                <p class="lead">
                    {{ trans('general.checkout') }} {{ ucfirst($type) }}
                </p>

                <p>
                    <strong>{{ $item->name ?: ucfirst($type).' #'.$item->id }}</strong>
                </p>

                @if ($type === 'asset' && !empty($item->asset_tag))
                    <p>{{ trans('general.asset_tag') }}: {{ $item->asset_tag }}</p>
                @endif

                <p style="margin-top: 25px;">
                    <a href="{{ $checkoutUrl }}" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        {{ trans('general.checkout') }}
                    </a>
                </p>
            </div>
        </x-box>
    </x-page-column>
</x-container>
@stop
