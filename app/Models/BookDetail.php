<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'isbn_10', 'isbn_13', 'author', 'publisher', 'published_year', 'pages', 'format', 'genres', 'author_bio'])]
class BookDetail extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    public $timestamps = false;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
