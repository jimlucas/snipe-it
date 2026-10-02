@extends('layouts/default', [
    'helpTitle' => trans('admin/kits/general.about_kits_title'),
    'helpText' => trans('admin/kits/general.about_kits_text')])

{{-- Web site Title --}}
@section('title')
  {{ trans('general.kits') }}
@parent
@stop

@section('header_right')
    <a href="{{ route('qr-checkout.labels.index', ['type' => 'kit']) }}" class="btn btn-primary pull-right">
        <i class="fas fa-qrcode fa-fw" aria-hidden="true"></i> Checkout Labels
    </a>
@endsection

{{-- Content --}}
@section('content')
    <x-container>
        <x-box name="kits" sr_only_title>

            <x-slot:table_header>{{ trans('general.kits') }}</x-slot:table_header>

            <x-table
                :presenter="\App\Presenters\PredefinedKitPresenter::dataTableLayout()"
                :fixed_number="1"
                :fixed_right_number="2"
                buttons="kitButtons"
                api_url="{{ route('api.kits.index') }}"
                export_filename="export-kits-{{ date('Y-m-d') }}"
            />
        </x-box>
    </x-container>
@stop
@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'kits-export', 'search' => true])
@stop
