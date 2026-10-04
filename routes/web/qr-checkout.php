<?php

use App\Http\Controllers\InventoryCheckoutQrController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'qr-checkout', 'middleware' => ['auth']], function () {
    Route::get('{type}/labels', [InventoryCheckoutQrController::class, 'labels'])
        ->where('type', '[a-z]+')
        ->name('qr-checkout.labels.index');

    Route::post('{type}/labels', [InventoryCheckoutQrController::class, 'labelsPrint'])
        ->where('type', '[a-z]+')
        ->name('qr-checkout.labels.print');

    Route::post('{type}/{id}/transaction', [InventoryCheckoutQrController::class, 'transact'])
        ->where(['type' => '[a-z]+', 'id' => '[0-9]+'])
        ->name('qr-checkout.transaction');

    Route::get('{type}/{id}', [InventoryCheckoutQrController::class, 'show'])
        ->where(['type' => '[a-z]+', 'id' => '[0-9]+'])
        ->name('qr-checkout.show');

    Route::get('{type}/{id}/image', [InventoryCheckoutQrController::class, 'image'])
        ->where(['type' => '[a-z]+', 'id' => '[0-9]+'])
        ->name('qr-checkout.image');

    Route::get('{type}/{id}/label', [InventoryCheckoutQrController::class, 'label'])
        ->where(['type' => '[a-z]+', 'id' => '[0-9]+'])
        ->name('qr-checkout.label');
});
