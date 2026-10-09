<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function enrolled(): array
    {
        $admin = User::factory()->admin()->create();
        $service = app(TwoFactor::class);
        $service->start($admin);
        $codes = $service->confirm($admin, (new Google2FA)->getCurrentOtp($admin->two_factor_secret));

        return [$admin->fresh(), $codes];
    }

    public function test_customers_cannot_use_the_security_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('security.show'))->assertForbidden();
    }

    public function test_staff_enroll_with_a_correct_code_and_get_recovery_codes(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('security.start'))->assertRedirect();
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertFalse($admin->hasTwoFactor());

        $this->post(route('security.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($admin->fresh()->hasTwoFactor());

        $this->post(route('security.confirm'), ['code' => (new Google2FA)->getCurrentOtp($admin->two_factor_secret)])
            ->assertSessionHas('recovery_codes');
        $this->assertTrue($admin->fresh()->hasTwoFactor());
        $this->assertCount(8, $admin->fresh()->two_factor_recovery_codes);
    }

    public function test_secret_is_stored_encrypted(): void
    {
        [$admin] = $this->enrolled();

        $raw = \DB::table('users')->where('id', $admin->id)->value('two_factor_secret');
        $this->assertNotSame($admin->two_factor_secret, $raw);
    }

    public function test_panel_requires_the_challenge_once_enrolled(): void
    {
        [$admin] = $this->enrolled();

        $this->actingAs($admin)->get(route('admin.index'))->assertRedirect(route('two-factor.challenge'));

        $this->withSession(['two_factor_user' => $admin->id])->actingAs($admin)->get(route('admin.index'))->assertOk();
    }

    public function test_wrong_code_is_refused_and_attempts_are_limited(): void
    {
        [$admin] = $this->enrolled();
        $this->actingAs($admin);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.verify'), ['code' => '111111'])->assertSessionHasErrors('code');
        }
        $good = (new Google2FA)->getCurrentOtp($admin->two_factor_secret);
        $this->post(route('two-factor.verify'), ['code' => $good])->assertSessionHasErrors('code');
        $this->get(route('admin.index'))->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        [$admin] = $this->enrolled();
        $service = app(TwoFactor::class);

        // The code used to confirm the enrollment is already spent.
        $this->assertFalse($service->verify($admin, (new Google2FA)->getCurrentOtp($admin->two_factor_secret)));
    }

    public function test_a_recovery_code_works_once(): void
    {
        [$admin, $codes] = $this->enrolled();
        $this->actingAs($admin);

        $this->post(route('two-factor.verify'), ['code' => $codes[0]])->assertRedirect();
        $this->get(route('admin.index'))->assertOk();
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);

        $this->assertFalse(app(TwoFactor::class)->verify($admin->fresh(), $codes[0]));
    }

    public function test_staff_without_it_are_sent_to_set_it_up_when_required(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.index'))->assertOk();

        config(['shop.require_two_factor' => true]);
        $this->get(route('admin.index'))->assertRedirect(route('security.show'));
        $this->get(route('security.show'))->assertOk();
    }

    public function test_disabling_needs_the_password(): void
    {
        [$admin] = $this->enrolled();
        $this->actingAs($admin);

        $this->delete(route('security.disable'), ['password' => 'incorrecta'])->assertSessionHasErrors('password');
        $this->assertTrue($admin->fresh()->hasTwoFactor());

        $this->delete(route('security.disable'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->hasTwoFactor());
    }

    public function test_super_admin_can_reset_someones_two_factor_but_an_admin_cannot(): void
    {
        [$admin] = $this->enrolled();

        $this->actingAs(User::factory()->admin()->create())->delete(route('super.users.2fa-reset', $admin))->assertForbidden();
        $this->assertTrue($admin->fresh()->hasTwoFactor());

        $this->actingAs(User::factory()->superAdmin()->create())->delete(route('super.users.2fa-reset', $admin))->assertSessionHas('notice');
        $this->assertFalse($admin->fresh()->hasTwoFactor());
        $this->assertDatabaseHas('activity_logs', ['action' => 'security.2fa_reset']);
    }
}
