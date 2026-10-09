<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the staff activity record. Written, never edited. */
#[Fillable(['user_id', 'action', 'description', 'properties'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Record what the signed-in person just did. Never put secrets or card data in $properties. */
    public static function record(string $action, string $description, array $properties = [], ?User $by = null): self
    {
        return static::create([
            'user_id' => ($by ?? auth()->user())?->id,
            'action' => $action,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }
}
