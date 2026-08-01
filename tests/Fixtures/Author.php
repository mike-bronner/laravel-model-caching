<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Tests\Database\Factories\AuthorFactory;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Author extends Model
{
    use Cachable;
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): AuthorFactory
    {
        return AuthorFactory::new();
    }

    protected $casts = [
        "finances" => "array",
    ];
    protected $fillable = [
        'name',
        'email',
        "finances",
    ];

    public function books() : HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function oldestBook() : HasOne
    {
        return $this->hasOne(Book::class)
            ->ofMany("id", "min");
    }

    public function firstBook() : HasOne
    {
        return $this->hasOne(Book::class)
            ->oldestOfMany();
    }

    public function newestBook() : HasOne
    {
        return $this->hasOne(Book::class)
            ->latestOfMany();
    }

    // Composite (multi-column) `ofMany()`: the highest-priced book, with the
    // most recent publication date breaking a price tie. Unlike the
    // single-column relations above, this builds a nested `beforeQuery`
    // callback on the one-of-many subquery.
    public function latestBookByPriceThenDate() : HasOne
    {
        return $this->hasOne(Book::class)
            ->ofMany([
                "price" => "max",
                "published_at" => "max",
            ]);
    }

    public function printers() : HasManyThrough
    {
        return $this->hasManyThrough(Printer::class, Book::class);
    }
    
    public function profile() : HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function getLatestBookAttribute()
    {
        return $this
            ->books()
            ->latest("id")
            ->first();
    }

    public function scopeStartsWithA(Builder $query) : Builder
    {
        return $query->where('name', 'LIKE', 'A%');
    }

    public function scopeNameStartsWith(Builder $query, string $startOfName) : Builder
    {
        return $query->where("name", "LIKE", "{$startOfName}%");
    }

    public function scopeBooksStartWith(Builder $query, string $startOfName) : Builder
    {
        return $query->where("name", "LIKE", "{$startOfName}%");
    }
}
