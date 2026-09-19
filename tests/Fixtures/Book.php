<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Tests\Database\Factories\BookFactory;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Book extends Model
{
    use Cachable;
    use HasFactory;

    protected static function newFactory(): BookFactory
    {
        return BookFactory::new();
    }

    protected $casts = [
        'price' => 'float',
        'published_at' => 'datetime',
    ];

    protected $fillable = [
        "author_id",
        'description',
        'published_at',
        'title',
        "publisher_id",
        'price',
    ];

    public function author() : BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function comments() : MorphMany
    {
        return $this->morphMany(Comment::class, "commentable");
    }

    public function image() : MorphOne
    {
        return $this->morphOne(Image::class, "imagable");
    }

    public function publisher() : BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function stores() : BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    public function prefixedStores() : BelongsToMany
    {
        return $this->belongsToMany(PrefixedStore::class, "book_store", "book_id", "store_id");
    }

    public function scopeStartsWith(Builder $query, string $startOfName) : Builder
    {
        return $query->where("title", "LIKE", "{$startOfName}%");
    }
}
