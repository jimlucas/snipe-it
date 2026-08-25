<?php

namespace Tests\Feature\QrCheckout;

use App\Models\Accessory;
use App\Models\User;
use Tests\TestCase;

class QrCheckoutTest extends TestCase
{
    public function test_qr_checkout_requires_authentication()
    {
        $accessory = Accessory::factory()->create();

        $this->get(route('qr-checkout.show', ['type' => 'accessory', 'id' => $accessory->id]))
            ->assertRedirect(route('login'));
    }

    public function test_qr_checkout_landing_page_links_to_native_checkout_route()
    {
        $accessory = Accessory::factory()->create(['name' => 'QR Test Accessory']);
        $actor = User::factory()->superuser()->create();

        $this->actingAs($actor)
            ->get(route('qr-checkout.show', ['type' => 'accessory', 'id' => $accessory->id]))
            ->assertOk()
            ->assertSee('QR Test Accessory')
            ->assertSee(route('accessories.checkout.show', $accessory), false)
            ->assertSee(route('qr-checkout.label', ['type' => 'accessory', 'id' => $accessory->id]), false);
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
