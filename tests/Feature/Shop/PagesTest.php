<?php

namespace Tests\Feature\Shop;

use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_footer_links_to_the_help_pages(): void
    {
        $this->get('/')->assertSee(route('about'), false)->assertSee(route('faq'), false);
    }

    public function test_the_about_page_renders(): void
    {
        $this->get('/nosotros')->assertOk()->assertSee('librería independiente de Lima')->assertSee('@hardcoverbookery');
    }

    public function test_the_faq_reads_the_real_shipping_rates(): void
    {
        config(['shop.free_shipping_from' => 200]);
        ShippingZone::factory()->create(['name' => 'Lima Metropolitana', 'min_cost' => 10, 'position' => 0]);
        ShippingZone::factory()->create(['name' => 'Lima Provincia', 'min_cost' => 15, 'position' => 1]);
        ShippingZone::factory()->create(['name' => 'Zona apagada', 'is_active' => false]);

        $this->get('/preguntas-frecuentes')
            ->assertOk()
            ->assertSeeInOrder(['Lima Metropolitana: desde', 'S/ 10.00', 'Lima Provincia: desde', 'S/ 15.00'])
            ->assertDontSee('Zona apagada')
            ->assertSee('S/ 200.00')
            ->assertSee('el stock se descuenta cuando se confirma tu pago')
            ->assertSee('te devolvemos todo lo que pagaste');
    }

    public function test_the_faq_says_where_the_shop_delivers_and_that_other_regions_are_coming_soon(): void
    {
        $lima = ShippingZone::factory()->create(['name' => 'Lima Metropolitana', 'position' => 0]);
        $provincia = ShippingZone::factory()->create(['name' => 'Lima Provincia', 'position' => 1]);
        ShippingDistrict::factory()->for($lima, 'zone')->create();
        ShippingDistrict::factory()->for($provincia, 'zone')->create();

        $this->get('/preguntas-frecuentes')->assertSee('Por ahora enviamos a Lima Metropolitana y Lima Provincia')->assertSee('otras regiones estarán disponibles próximamente');
    }

    public function test_a_zone_without_districts_is_not_announced_as_served(): void
    {
        $lima = ShippingZone::factory()->create(['name' => 'Lima Metropolitana', 'position' => 0]);
        ShippingZone::factory()->create(['name' => 'Lima Provincia', 'position' => 1]);
        ShippingDistrict::factory()->for($lima, 'zone')->create();

        $this->get('/preguntas-frecuentes')->assertSee('Por ahora enviamos a Lima Metropolitana.')->assertDontSee('Lima Metropolitana y Lima Provincia');
    }

    public function test_no_page_mentions_whatsapp_for_now(): void
    {
        foreach (['/', '/nosotros', '/preguntas-frecuentes', '/catalogo'] as $url) {
            $this->get($url)->assertDontSee('wa.me', false)->assertDontSee('WhatsApp');
        }
    }
}
