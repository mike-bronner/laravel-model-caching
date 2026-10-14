<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AuthorWithTtlRelations extends Author
{
    protected $table = "authors";

    public function booksWithoutTtl() : HasMany
    {
        return $this->hasMany(Book::class, "author_id");
    }

    public function booksWithTtl() : HasMany
    {
        return $this->hasMany(BookWithTtl::class, "author_id");
    }

    public function booksOptedOutOfTtl() : HasMany
    {
        return $this->hasMany(BookOptedOutOfTtl::class, "author_id");
    }

    public function booksWithPublisherWithTtl() : HasMany
    {
        return $this->hasMany(BookWithPublisherWithTtl::class, "author_id");
    }

    public function newestBookWithTtl() : HasOne
    {
        return $this->hasOne(BookWithTtl::class, "author_id")
            ->latestOfMany();
    }

    public function printersWithTtl() : HasManyThrough
    {
        return $this->hasManyThrough(PrinterWithTtl::class, Book::class, "author_id", "book_id");
    }
}
