<?php

namespace App\Http\Controllers;

use App\Events\CheckoutableCheckedIn;
use App\Events\CheckoutableCheckedOut;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\PredefinedKit;
use App\Models\Setting;
use App\Models\User;
use App\View\QrCheckoutLabel;
use Com\Tecnick\Barcode\Barcode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

        $supportsQuickTransaction = in_array($type, ['accessory', 'consumable'], true);
        $available = $supportsQuickTransaction ? (int) $item->numRemaining() : null;
        $currentUser = auth()->user();
        $heldByCurrentUser = $supportsQuickTransaction
            ? $this->heldQuantity($type, $item->id, $currentUser->id)
            : 0;

        return view('qr-checkout.show', [
            'item' => $item,
            'type' => $type,
            'checkoutUrl' => $this->checkoutUrl($type, $id),
            'labelUrl' => route('qr-checkout.label', ['type' => $type, 'id' => $id]),
            'supportsQuickTransaction' => $supportsQuickTransaction,
            'available' => $available,
            'currentUser' => $currentUser,
            'heldByCurrentUser' => $heldByCurrentUser,
        ]);
    }

    public function transact(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['accessory', 'consumable'], true), 404);

        $item = $this->findItem($type, $id);
        $action = $request->validate([
            'action' => ['required', 'in:checkout,checkin'],
            'quantity' => ['required', 'integer', 'min:1'],
            'assigned_to' => ['nullable', 'integer'],
        ])['action'];

        $permission = $action === 'checkout' ? 'checkout' : 'checkin';
        $this->authorize($permission, $item);

        $targetUserId = (int) ($request->input('assigned_to') ?: auth()->id());
        $targetUser = User::find($targetUserId);

        if (! $targetUser) {
            return back()->with('error', 'The selected user does not exist.');
        }

        $quantity = (int) $request->input('quantity');

        if ($action === 'checkout') {
            return $this->quickCheckout($type, $item, $targetUser, $quantity);
        }

        return $this->quickCheckin($type, $item, $targetUser, $quantity);
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

    public function labelsPrint(Request $request, string $type): Response
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

        $pdf = (new QrCheckoutLabel)
            ->with('settings', $settings)
            ->with('items', $items)
            ->with('type', $type)
            ->with('copies', (int) $validated['copies'])
            ->render();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="qr-checkout-'.str_slug($type).'-labels.pdf"',
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

    private function heldQuantity(string $type, int $itemId, int $userId): int
    {
        if ($type === 'accessory') {
            return AccessoryCheckout::where('accessory_id', $itemId)
                ->where('assigned_to', $userId)
                ->where('assigned_type', User::class)
                ->count();
        }

        return DB::table('consumables_users')
            ->where('consumable_id', $itemId)
            ->where('assigned_to', $userId)
            ->count();
    }

    private function quickCheckout(string $type, $item, User $targetUser, int $quantity): RedirectResponse
    {
        if (! $item->canCheckoutTo($targetUser)) {
            return back()->with('error', trans('general.error_checkout_company_mismatch', [
                'item' => ucfirst($type).' "'.$item->name.'"',
                'item_company' => $item->company->name ?? trans('general.unassigned'),
                'target' => trans('general.user').' "'.$targetUser->username.'"',
            ]));
        }

        $overAllocated = false;

        DB::transaction(function () use ($type, $item, $targetUser, $quantity, &$overAllocated): void {
            $locked = $item::whereKey($item->id)->lockForUpdate()->first();

            if (! $locked || $locked->numRemaining() < $quantity) {
                $overAllocated = true;
                return;
            }

            for ($i = 0; $i < $quantity; $i++) {
                if ($type === 'accessory') {
                    $checkout = new AccessoryCheckout([
                        'accessory_id' => $item->id,
                        'assigned_to' => $targetUser->id,
                        'assigned_type' => User::class,
                    ]);
                    $checkout->created_by = auth()->id();
                    $checkout->save();
                } else {
                    DB::table('consumables_users')->insert([
                        'consumable_id' => $item->id,
                        'assigned_to' => $targetUser->id,
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        if ($overAllocated) {
            return back()->with('error', 'There are not enough items available for that checkout quantity.');
        }

        event(new CheckoutableCheckedOut($item, $targetUser, auth()->user(), null, [], $quantity, false));

        return redirect()->route('qr-checkout.show', ['type' => $type, 'id' => $item->id])
            ->with('success', $quantity.' '.str_plural($type, $quantity).' checked out to '.$targetUser->display_name.'.');
    }

    private function quickCheckin(string $type, $item, User $targetUser, int $quantity): RedirectResponse
    {
        $held = $this->heldQuantity($type, $item->id, $targetUser->id);

        if ($held < $quantity) {
            return back()->with('error', $targetUser->display_name.' only has '.$held.' of this item checked out.');
        }

        DB::transaction(function () use ($type, $item, $targetUser, $quantity): void {
            if ($type === 'accessory') {
                $rows = AccessoryCheckout::where('accessory_id', $item->id)
                    ->where('assigned_to', $targetUser->id)
                    ->where('assigned_type', User::class)
                    ->orderByDesc('created_at')
                    ->limit($quantity)
                    ->get();

                foreach ($rows as $row) {
                    $row->delete();
                }
            } else {
                $ids = DB::table('consumables_users')
                    ->where('consumable_id', $item->id)
                    ->where('assigned_to', $targetUser->id)
                    ->orderByDesc('id')
                    ->limit($quantity)
                    ->pluck('id');

                DB::table('consumables_users')->whereIn('id', $ids)->delete();
            }
        });

        for ($i = 0; $i < $quantity; $i++) {
            event(new CheckoutableCheckedIn($item, $targetUser, auth()->user(), null));
        }

        return redirect()->route('qr-checkout.show', ['type' => $type, 'id' => $item->id])
            ->with('success', $quantity.' '.str_plural($type, $quantity).' checked in from '.$targetUser->display_name.'.');
    }

    private function checkoutUrl(string $type, int $id): string
    {
        return route($this->typeConfig($type)['checkout_route'], $id);
    }
}
