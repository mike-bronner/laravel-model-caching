<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\HasMany;

class EagerLoadCountingAuthor extends Author
{
    protected $table = "authors";

    public function books() : HasMany
    {
        RelationCallLog::record($this, "books");

        return $this->hasMany(EagerLoadCountingBook::class, "author_id");
    }
}
