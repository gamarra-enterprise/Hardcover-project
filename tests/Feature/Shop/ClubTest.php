<?php

namespace Tests\Feature\Shop;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Notifications\NewsletterWelcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClubTest extends TestCase
{
    use RefreshDatabase;

    public function test_joining_saves_the_address_once_and_sends_a_welcome(): void
    {
        Notification::fake();

        $this->post(route('club.subscribe'), ['email' => ' Lector@Correo.COM '])->assertSessionHas('club_notice');
        $this->post(route('club.subscribe'), ['email' => 'lector@correo.com'])->assertSessionHas('club_notice');

        $this->assertSame(1, NewsletterSubscriber::count());
        $this->assertSame('lector@correo.com', NewsletterSubscriber::first()->email);
        Notification::assertSentOnDemandTimes(NewsletterWelcome::class, 1);
    }

    public function test_an_invalid_address_is_refused(): void
    {
        $this->post(route('club.subscribe'), ['email' => 'no-es-un-correo'])->assertSessionHasErrors('email');
        $this->assertSame(0, NewsletterSubscriber::count());
    }

    public function test_leaving_needs_the_signed_link_and_a_return_is_welcome(): void
    {
        Notification::fake();
        $subscriber = NewsletterSubscriber::create(['email' => 'a@b.com']);

        $this->get(route('club.unsubscribe', $subscriber))->assertForbidden();
        $this->assertTrue($subscriber->fresh()->isActive());

        $this->get($subscriber->unsubscribeUrl())->assertRedirect(route('home'));
        $this->assertFalse($subscriber->fresh()->isActive());

        $this->post(route('club.subscribe'), ['email' => 'a@b.com']);
        $this->assertTrue($subscriber->fresh()->isActive());
    }

    public function test_only_staff_see_and_export_the_list(): void
    {
        NewsletterSubscriber::create(['email' => 'activo@b.com']);
        NewsletterSubscriber::create(['email' => 'baja@b.com', 'unsubscribed_at' => now()]);

        $this->actingAs(User::factory()->create())->get(route('admin.club.index'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.club.index'))->assertOk()->assertSee('activo@b.com');
        $csv = $this->actingAs($admin)->get(route('admin.club.export'))->streamedContent();
        $this->assertStringContainsString('activo@b.com', $csv);
        $this->assertStringNotContainsString('baja@b.com', $csv);
    }
}
