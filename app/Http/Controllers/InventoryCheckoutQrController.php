<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\PredefinedKit;
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

    private const LABEL_PRESETS = [
        'dymo_11354' => ['width' => 57.0, 'height' => 32.0, 'qr' => 25.0],
        '225x125' => ['width' => 57.15, 'height' => 31.75, 'qr' => 25.4],
        '250x150' => ['width' => 63.5, 'height' => 38.1, 'qr' => 27.94],
        '300x200' => ['width' => 76.2, 'height' => 50.8, 'qr' => 34.29],
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
        return view('qr-checkout.labels', ['type' => $type, 'items' => $model::query()->orderBy('name')->paginate(100)]);
    }

    public function labelsPrint(Request $request, string $type): View
    {
        $config = $this->typeConfig($type);
        $model = $config['model'];

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'label_preset' => ['required', 'in:dymo_11354,225x125,250x150,300x200,custom'],
            'units' => ['required', 'in:mm,in'],
            'label_width' => ['required_if:label_preset,custom', 'nullable', 'numeric', 'min:15', 'max:300'],
            'label_height' => ['required_if:label_preset,custom', 'nullable', 'numeric', 'min:15', 'max:300'],
            'qr_size' => ['required_if:label_preset,custom', 'nullable', 'numeric', 'min:10', 'max:200'],
            'orientation' => ['required', 'in:auto,portrait,landscape'],
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validated['label_preset'] === 'custom') {
            $factor = $validated['units'] === 'in' ? 25.4 : 1;
            $labelWidth = (float) $validated['label_width'] * $factor;
            $labelHeight = (float) $validated['label_height'] * $factor;
            $qrSize = (float) $validated['qr_size'] * $factor;
        } else {
            $preset = self::LABEL_PRESETS[$validated['label_preset']];
            $labelWidth = $preset['width'];
            $labelHeight = $preset['height'];
            $qrSize = $preset['qr'];
        }

        if ($validated['orientation'] === 'portrait' && $labelWidth > $labelHeight) {
            [$labelWidth, $labelHeight] = [$labelHeight, $labelWidth];
        } elseif ($validated['orientation'] === 'landscape' && $labelHeight > $labelWidth) {
            [$labelWidth, $labelHeight] = [$labelHeight, $labelWidth];
        }

        $maxQr = max(10.0, min($labelWidth, $labelHeight) - 6.0);
        $qrSize = min($qrSize, $maxQr);

        $items = $model::query()->whereIn('id', $validated['ids'])->orderBy('name')->get();
        abort_if($items->isEmpty(), 404);
        foreach ($items as $item) {
            $this->authorize('view', $item);
        }

        return view('qr-checkout.labels-print', [
            'type' => $type, 'items' => $items, 'labelWidth' => $labelWidth,
            'labelHeight' => $labelHeight, 'qrSize' => $qrSize, 'copies' => (int) $validated['copies'],
        ]);
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
