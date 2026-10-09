<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewayStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_sees_it(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('super.gateways'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('super.gateways'))->assertForbidden();
    }

    public function test_it_reports_missing_credentials_and_never_prints_secrets(): void
    {
        config([
            'shop.payment_gateway' => 'mercadopago',
            'services.mercadopago.access_token' => 'APP_USR-super-secreto-123',
            'services.mercadopago.public_key' => null,
            'services.mercadopago.webhook_secret' => null,
        ]);

        $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('super.gateways'));

        $response->assertOk()->assertSee('Incompleto')->assertSee(route('payments.webhook'));
        $response->assertDontSee('super-secreto-123');
    }

    public function test_fake_gateway_is_flagged_as_test_mode(): void
    {
        config(['shop.payment_gateway' => 'fake']);

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('super.gateways'))->assertSee('Prueba (fake)');
    }
}
