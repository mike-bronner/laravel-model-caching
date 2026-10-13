<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UncachedAuthorWithDynamicFillable extends Model
{
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
