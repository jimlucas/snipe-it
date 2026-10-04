@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.components') }}
@parent
@stop

@section('header_right')
    <a href="{{ route('qr-checkout.labels.index', ['type' => 'component']) }}" class="btn btn-primary pull-right">
        <i class="fas fa-qrcode fa-fw" aria-hidden="true"></i> Checkout Labels
    </a>
@endsection

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box name="components" sr_only_title>
            <x-table.components :route="route('api.components.index')" />
        </x-box>
    </x-container>
@can('update', \App\Models\Component::class)
    <x-modals.adjust-quantity />
@endcan
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'components-export', 'search' => true, 'showFooter' => true, 'columns' => \App\Presenters\ComponentPresenter::dataTableLayout()])



@stop
