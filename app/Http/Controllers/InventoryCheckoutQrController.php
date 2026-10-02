<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\PredefinedKit;
use App\Models\Setting;
use App\View\QrCheckoutLabel;
use Com\Tecnick\Barcode\Barcode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryCheckoutQrController extends Controller
{
    private const TYPES = [
        'asset' => ['model' => Asset::class, 'checkout_route' => 'hardware.checkout.create'],
        'accessory' => ['model' => Accessory::class, 'checkout_route' => 'accessories.checkout.show'],
        'component' => ['model' => Component::class, 'checkout_route' => 'components.checkout.show'],
        'consumable' => ['model' => Consumable::class, 'checkout_route' => 'consumables.checkout.show'],
        'license' => ['model' => License::class, 'checkout_route' => 'licenses.checkout'],
        'kit' => ['model' => PredefinedKit::class, 'checkout_route' => 'kits.checkout.show'],
    ];


    public function show(string $type, int $id): View
    {
        $item = $this->findItem($type, $id);
        $this->authorize('view', $item);
        return view('qr-checkout.show', ['item' => $item, 'type' => $type, 'checkoutUrl' => $this->checkoutUrl($type, $id), 'labelUrl' => route('qr-checkout.label', ['type' => $type, 'id' => $id])]);
    }

    public function labels(string $type): View
    {
        $config = $this->typeConfig($type);
        $model = $config['model'];
        $this->authorize('index', $model);

        return view('qr-checkout.labels', [
            'type' => $type,
            'items' => $model::query()->orderBy('name')->paginate(100),
            'settings' => Setting::getSettings(),
        ]);
    }

    public function labelsPrint(Request $request, string $type)
    {
        $config = $this->typeConfig($type);
        $model = $config['model'];
        $settings = Setting::getSettings();

        abort_unless($settings->label2_enable, 422, 'QR Checkout Labels require the Enhanced Label Engine.');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $items = $model::query()->whereIn('id', $validated['ids'])->orderBy('name')->get();
        abort_if($items->isEmpty(), 404);

        foreach ($items as $item) {
            $this->authorize('view', $item);
        }

        return (new QrCheckoutLabel)
            ->with('settings', $settings)
            ->with('items', $items)
            ->with('type', $type)
            ->with('copies', (int) $validated['copies']);
    }

    public function label(string $type, int $id): View
    {
        $item = $this->findItem($type, $id);
        $this->authorize('view', $item);
        return view('qr-checkout.label', ['item' => $item, 'type' => $type, 'qrImageUrl' => route('qr-checkout.image', ['type' => $type, 'id' => $id])]);
    }

    public function image(string $type, int $id): Response|BinaryFileResponse
    {
        $item = $this->findItem($type, $id);
        $this->authorize('view', $item);

        $directory = public_path('uploads/barcodes');
        $filename = 'qr-checkout-'.str_slug($type).'-'.$id.'.png';
        $path = $directory.'/'.$filename;
        if (file_exists($path)) {
            return response()->file($path, ['Content-type' => 'image/png']);
        }
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $barcode = new Barcode;
        $barcodeObject = $barcode->getBarcodeObj(
            'QRCODE,H',
            route('qr-checkout.show', ['type' => $type, 'id' => $id]),
            -4, -4, 'black', [4, 4, 4, 4]
        );
        file_put_contents($path, $barcodeObject->getPngData());
        return response($barcodeObject->getPngData())->header('Content-type', 'image/png');
    }

    private function typeConfig(string $type): array
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        return self::TYPES[$type];
    }

    private function findItem(string $type, int $id)
    {
        $model = $this->typeConfig($type)['model'];
        $item = $model::find($id);
        abort_if(is_null($item), 404);
        return $item;
    }

    private function checkoutUrl(string $type, int $id): string
    {
        return route($this->typeConfig($type)['checkout_route'], $id);
    }
}
