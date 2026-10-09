<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/** Someone who asked, from the "Club de lectores" form, to get news from the shop. */
#[Fillable(['email', 'subscribed_at', 'unsubscribed_at'])]
class NewsletterSubscriber extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['subscribed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->unsubscribed_at === null;
    }

    /** Link in every message to leave the list. Signed, no login needed. */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('club.unsubscribe', ['subscriber' => $this->id]);
    }
}
