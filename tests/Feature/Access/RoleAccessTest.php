<?php

namespace Tests\Feature\Access;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): ?User
    {
        return match ($role) {
            'guest' => null,
            'customer' => User::factory()->create(),
            'admin' => User::factory()->admin()->create(),
            'super_admin' => User::factory()->superAdmin()->create(),
        };
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function panelAccess(): array
    {
        return [
            'guest to admin' => ['guest', '/admin', 302],
            'customer to admin' => ['customer', '/admin', 403],
            'admin to admin' => ['admin', '/admin', 200],
            'super admin to admin' => ['super_admin', '/admin', 200],
            'guest to super' => ['guest', '/super', 302],
            'customer to super' => ['customer', '/super', 403],
            'admin to super' => ['admin', '/super', 403],
            'super admin to super' => ['super_admin', '/super', 200],
        ];
    }

    #[DataProvider('panelAccess')]
    public function test_panels_are_limited_by_role(string $role, string $url, int $status): void
    {
        $user = $this->actor($role);

        $response = $user ? $this->actingAs($user)->get($url) : $this->get($url);

        $response->assertStatus($status);
    }

    public function test_dashboard_sends_each_role_to_its_place(): void
    {
        $this->actingAs($this->actor('customer'))->get('/dashboard')->assertRedirect(route('account.orders'));
        $this->actingAs($this->actor('admin'))->get('/dashboard')->assertRedirect('/admin');
        $this->actingAs($this->actor('super_admin'))->get('/dashboard')->assertRedirect('/super');
    }

    public function test_only_staff_manage_the_catalog(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();

        $customer = $this->actor('customer');
        $admin = $this->actor('admin');

        foreach ([$product, $category] as $model) {
            $this->assertFalse($customer->can('update', $model));
            $this->assertFalse($customer->can('delete', $model));
            $this->assertTrue($admin->can('update', $model));
            $this->assertTrue($admin->can('delete', $model));
        }
        $this->assertFalse($customer->can('create', Product::class));
        $this->assertTrue($admin->can('create', Product::class));
    }

    public function test_everyone_can_browse_active_products_but_not_inactive_ones(): void
    {
        $active = Product::factory()->create();
        $hidden = Product::factory()->hidden()->create();

        $this->assertTrue(Gate::forUser(null)->allows('view', $active));
        $this->assertFalse(Gate::forUser(null)->allows('view', $hidden));
        $this->assertFalse($this->actor('customer')->can('view', $hidden));
        $this->assertTrue($this->actor('admin')->can('view', $hidden));
    }

    public function test_customers_only_see_their_own_orders_addresses_and_cart(): void
    {
        $owner = $this->actor('customer');
        $other = $this->actor('customer');
        $order = Order::factory()->for($owner)->create();
        $address = Address::factory()->for($owner)->create();

        $this->assertTrue($owner->can('view', $order));
        $this->assertFalse($other->can('view', $order));
        $this->assertFalse($other->can('update', $order));
        $this->assertTrue($owner->can('update', $address));
        $this->assertFalse($other->can('update', $address));
        $this->assertTrue($this->actor('admin')->can('view', $order));
        $this->assertTrue($this->actor('admin')->can('update', $order));
    }

    public function test_admins_cannot_manage_users_or_roles_but_super_admins_can(): void
    {
        $admin = $this->actor('admin');
        $customer = $this->actor('customer');
        $super = $this->actor('super_admin');

        $this->assertFalse($admin->can('viewAny', User::class));
        $this->assertFalse($admin->can('update', $customer));
        $this->assertFalse($admin->can('changeRole', $customer));
        $this->assertTrue($admin->can('update', $admin));
        $this->assertFalse($admin->can('changeRole', $admin));

        $this->assertTrue($super->can('viewAny', User::class));
        $this->assertTrue($super->can('update', $customer));
        $this->assertTrue($super->can('changeRole', $customer));
        $this->assertTrue($super->can('delete', Order::factory()->create()));
    }

    public function test_the_administrator_manages_shipping_zones_and_districts_but_customers_do_not(): void
    {
        $zone = ShippingZone::factory()->create();
        $district = ShippingDistrict::factory()->for($zone, 'zone')->create();

        foreach ([$zone, $district] as $model) {
            $this->assertTrue($this->actor('admin')->can('update', $model));
            $this->assertTrue($this->actor('admin')->can('delete', $model));
            $this->assertTrue($this->actor('super_admin')->can('update', $model));
            $this->assertFalse($this->actor('customer')->can('update', $model));
            $this->assertFalse($this->actor('customer')->can('viewAny', $model::class));
        }

        $this->assertTrue($this->actor('admin')->can('create', ShippingDistrict::class));
        $this->assertFalse($this->actor('customer')->can('create', ShippingDistrict::class));
    }
}
