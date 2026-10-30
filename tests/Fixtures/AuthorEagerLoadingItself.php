<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AuthorEagerLoadingItself extends Author
{
    protected $table = "authors";
    protected $with = ["namesakes"];

    public function namesakes() : HasMany
    {
        return $this->hasMany(static::class, "name", "email");
    }
}
