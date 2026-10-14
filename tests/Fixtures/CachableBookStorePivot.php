<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CachableBookStorePivot extends Pivot
{
    use Cachable;

    protected $fillable = [
        'book_id',
        'store_id',
    ];
    protected $table = 'book_store';
}
