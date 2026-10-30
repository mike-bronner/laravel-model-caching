<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\HasMany;

class BookWithCommentsOfAnyType extends Book
{
    protected $table = "books";

    public function commentsOfAnyType() : HasMany
    {
        return $this->hasMany(Comment::class, "commentable_id");
    }
}
