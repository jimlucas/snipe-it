<?php

namespace Tests\Feature\QrCheckout;

use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

class QrCheckoutTest extends TestCase
{
    private function enableEnhancedLabels(): void
    {
        Setting::getSettings()->update([
            'label2_enable' => true,
            'label2_template' => 'Tapes\\Dymo\\LabelWriter_11354',
        ]);
    }

    public function test_qr_checkout_requires_authentication()
    {
        $accessory = Accessory::factory()->create();

        $this->get(route('qr-checkout.show', ['type' => 'accessory', 'id' => $accessory->id]))
            ->assertRedirect(route('login'));
    }

    public function test_checkout_label_selector_requires_authentication()
    {
        $this->get(route('qr-checkout.labels.index', ['type' => 'accessory']))
            ->assertRedirect(route('login'));
    }

    public function test_qr_checkout_landing_page_contains_quick_transaction_controls()
    {
        $accessory = Accessory::factory()->create(['name' => 'QR Test Accessory', 'qty' => 10]);
        $actor = User::factory()->superuser()->create();

        $this->actingAs($actor)
            ->get(route('qr-checkout.show', ['type' => 'accessory', 'id' => $accessory->id]))
            ->assertOk()
            ->assertSee('QR Test Accessory')
            ->assertSee('Available')
            ->assertSee('Check Out')
            ->assertSee('Check In')
            ->assertSee(route('qr-checkout.transaction', ['type' => 'accessory', 'id' => $accessory->id]), false)
            ->assertSee(route('qr-checkout.label', ['type' => 'accessory', 'id' => $accessory->id]), false);
    }

    public function test_checkout_label_selector_lists_items_and_print_action()
    {
        $this->enableEnhancedLabels();

        $accessory = Accessory::factory()->create(['name' => 'Label Selector Accessory']);
        $actor = User::factory()->superuser()->create();

        $this->actingAs($actor)
            ->get(route('qr-checkout.labels.index', ['type' => 'accessory']))
            ->assertOk()
            ->assertSee('Label Selector Accessory')
            ->assertSee(route('qr-checkout.labels.print', ['type' => 'accessory']), false)
            ->assertSee('value="'.$accessory->id.'"', false);
    }

    public function test_batch_checkout_labels_return_pdf()
    {
        $this->enableEnhancedLabels();

        $accessories = Accessory::factory()->count(2)->create();
        $actor = User::factory()->superuser()->create();

        $response = $this->actingAs($actor)
            ->post(route('qr-checkout.labels.print', ['type' => 'accessory']), [
                'ids' => $accessories->pluck('id')->all(),
                'copies' => 1,
            ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_accessory_quick_checkout_and_checkin_round_trip()
    {
        $accessory = Accessory::factory()->create(['qty' => 5]);
        $actor = User::factory()->superuser()->create();

        $this->actingAs($actor)
            ->post(route('qr-checkout.transaction', ['type' => 'accessory', 'id' => $accessory->id]), [
                'action' => 'checkout',
                'quantity' => 2,
            ])
            ->assertRedirect(route('qr-checkout.show', ['type' => 'accessory', 'id' => $accessory->id]));

        $this->assertSame(2, AccessoryCheckout::where('accessory_id', $accessory->id)
            ->where('assigned_to', $actor->id)
            ->where('assigned_type', User::class)
            ->count());

        $this->actingAs($actor)
            ->post(route('qr-checkout.transaction', ['type' => 'accessory', 'id' => $accessory->id]), [
                'action' => 'checkin',
                'quantity' => 1,
            ])
            ->assertRedirect(route('qr-checkout.show', ['type' => 'accessory', 'id' => $accessory->id]));

        $this->assertSame(1, AccessoryCheckout::where('accessory_id', $accessory->id)
            ->where('assigned_to', $actor->id)
            ->where('assigned_type', User::class)
            ->count());
    }

    public function test_accessory_quick_checkout_can_override_target_user()
    {
        $accessory = Accessory::factory()->create(['qty' => 5]);
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('qr-checkout.transaction', ['type' => 'accessory', 'id' => $accessory->id]), [
                'action' => 'checkout',
                'quantity' => 1,
                'assigned_to' => $target->id,
            ])
            ->assertRedirect(route('qr-checkout.show', ['type' => 'accessory', 'id' => $accessory->id]));

        $this->assertDatabaseHas('accessories_checkout', [
            'accessory_id' => $accessory->id,
            'assigned_to' => $target->id,
            'assigned_type' => User::class,
            'created_by' => $actor->id,
        ]);
    }

    public function test_accessory_quick_checkout_rejects_quantity_above_available()
    {
        $accessory = Accessory::factory()->create(['qty' => 1]);
        $actor = User::factory()->superuser()->create();

        $this->actingAs($actor)
            ->from(route('qr-checkout.show', ['type' => 'accessory', 'id' => $accessory->id]))
            ->post(route('qr-checkout.transaction', ['type' => 'accessory', 'id' => $accessory->id]), [
                'action' => 'checkout',
                'quantity' => 2,
            ])
            ->assertRedirect(route('qr-checkout.show', ['type' => 'accessory', 'id' => $accessory->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('accessories_checkout', [
            'accessory_id' => $accessory->id,
            'assigned_to' => $actor->id,
        ]);
    }

    public function test_qr_checkout_label_page_contains_qr_image_route()
    {
        $accessory = Accessory::factory()->create(['name' => 'QR Test Accessory']);
        $actor = User::factory()->superuser()->create();

        $this->actingAs($actor)
            ->get(route('qr-checkout.label', ['type' => 'accessory', 'id' => $accessory->id]))
            ->assertOk()
            ->assertSee(route('qr-checkout.image', ['type' => 'accessory', 'id' => $accessory->id]), false);
    }

    public function test_unsupported_qr_checkout_type_returns_not_found()
    {
        $actor = User::factory()->superuser()->create();

        $this->actingAs($actor)
            ->get('/qr-checkout/notatype/1')
            ->assertNotFound();
    }
}
