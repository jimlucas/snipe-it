@extends('layouts/default')

@section('title')
    Checkout Labels - {{ ucfirst($type) }}
    @parent
@stop

@section('content')
    <x-container>
        <x-box>
            <div class="box-header with-border">
                <h3 class="box-title">Select {{ ucfirst($type) }} items to label</h3>
            </div>

            <div class="box-body">
                <form method="POST" action="{{ route('qr-checkout.labels.print', ['type' => $type]) }}" target="_blank">
                    @csrf

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all-labels" aria-label="Select all items on this page">
                                    </th>
                                    <th>Name</th>
                                    <th>Identifier</th>
                                    <th>ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="checkout-label-item" name="ids[]" value="{{ $item->id }}" aria-label="Select {{ $item->name ?: ('#'.$item->id) }}">
                                        </td>
                                        <td>{{ $item->name ?: trans('general.none') }}</td>
                                        <td>{{ $item->asset_tag ?? $item->model_number ?? '' }}</td>
                                        <td>{{ $item->id }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-muted">No items available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-print fa-fw" aria-hidden="true"></i>
                        Print Selected Labels
                    </button>
                </form>

                <div class="text-center">
                    {{ $items->links() }}
                </div>
            </div>
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
    document.getElementById('select-all-labels')?.addEventListener('change', function () {
        document.querySelectorAll('.checkout-label-item').forEach(function (checkbox) {
            checkbox.checked = document.getElementById('select-all-labels').checked;
        });
    });
</script>
@stop
