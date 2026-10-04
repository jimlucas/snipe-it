@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.consumables') }}
@parent
@stop

@section('header_right')
    <a href="{{ route('qr-checkout.labels.index', ['type' => 'consumable']) }}" class="btn btn-primary pull-right">
        <i class="fas fa-qrcode fa-fw" aria-hidden="true"></i> Checkout Labels
    </a>
@endsection

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box name="consumables" sr_only_title>
            <x-table.consumables :route="route('api.consumables.index')" />
        </x-box>
    </x-container>
@can('update', \App\Models\Consumable::class)
    <x-modals.adjust-quantity />
@endcan
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'consumables-export', 'search' => true,'showFooter' => true, 'columns' => \App\Presenters\ConsumablePresenter::dataTableLayout()])
@stop
