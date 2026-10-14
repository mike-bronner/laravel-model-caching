<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

class CachableTaggable extends Model
{
    use Cachable;

    protected $fillable = [
        'tag_id',
        'taggable_id',
        'taggable_type',
    ];
    protected $table = 'taggables';
}
