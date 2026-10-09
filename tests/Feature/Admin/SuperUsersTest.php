<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_reaches_users_and_activity(): void
    {
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('super.users.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super.activity'))->assertForbidden();
        }

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('super.users.index'))->assertOk();
    }

    public function test_admin_cannot_change_roles_by_calling_the_route(): void
    {
        $customer = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->patch(route('super.users.role', $customer), ['role' => 'super_admin'])->assertForbidden();
        $this->assertSame(UserRole::CUSTOMER, $customer->fresh()->role);
    }

    public function test_super_admin_changes_a_role_and_it_is_recorded(): void
    {
        $super = User::factory()->superAdmin()->create();
        $customer = User::factory()->create();

        $this->actingAs($super)->patch(route('super.users.role', $customer), ['role' => 'admin'])->assertSessionHas('notice');

        $this->assertSame(UserRole::ADMIN, $customer->fresh()->role);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $super->id, 'action' => 'user.role_changed']);
    }

    public function test_super_admin_cannot_change_their_own_role(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->patch(route('super.users.role', $super), ['role' => 'customer'])->assertSessionHas('error');
        $this->assertSame(UserRole::SUPER_ADMIN, $super->fresh()->role);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch(route('super.users.role', User::factory()->create()), ['role' => 'dios'])->assertSessionHasErrors('role');
    }

    public function test_panel_actions_leave_a_trace_in_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.products.store'), ['type' => 'libro', 'sku' => 'X-1', 'name' => 'Libro X', 'price' => '10', 'stock' => 1]);

        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'product.created']);

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('super.activity'))->assertOk()->assertSee('Libro X');
        $this->assertSame(1, ActivityLog::count());
        $this->assertSame(1, Product::count());
    }
}
