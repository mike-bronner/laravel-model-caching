<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AuthorWithCustomBaseBuilderAndBooks extends AuthorWithCustomBaseBuilder
{
    public function books() : HasMany
    {
        return $this->hasMany(Book::class, "author_id");
    }
}
