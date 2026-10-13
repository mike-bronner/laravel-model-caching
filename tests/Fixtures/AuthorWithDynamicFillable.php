<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthorWithDynamicFillable extends Model
{
    use Cachable;
    use SoftDeletes;

    protected $table = 'authors';

    protected $fillable = [
        'name',
        'email',
    ];

    public function __construct(array $attributes = [])
    {
        $this->fillable[] = 'is_famous';

        parent::__construct($attributes);
    }
}
