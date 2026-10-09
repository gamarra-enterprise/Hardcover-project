<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterWelcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/** The "Club de lectores" form: join the list and leave it. */
class ClubController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']], [], ['email' => 'correo']);
        $email = mb_strtolower(trim($data['email']));

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $email]);
        $wasActive = $subscriber->exists && $subscriber->isActive();

        if (! $wasActive) {
            $subscriber->fill(['subscribed_at' => now(), 'unsubscribed_at' => null])->save();

            try {
                Notification::route('mail', $email)->notify(new NewsletterWelcome($subscriber));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // The same answer whether or not the address was already on the list, so the form cannot be used to find out who is.
        return back()->with('club_notice', '¡Listo! Te escribiremos pronto.');
    }

    public function unsubscribe(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $subscriber->forceFill(['unsubscribed_at' => $subscriber->unsubscribed_at ?? now()])->save();

        return redirect()->route('home')->with('club_notice', 'Saliste de la lista. No recibirás más correos del Club.');
    }
}
