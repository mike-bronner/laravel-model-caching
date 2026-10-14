<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class AuthorWithId
{
    public function __construct(protected int $id)
    {
    }

    public function __invoke(Model $author): bool
    {
        return $author->getKey() === $this->id;
    }
}
