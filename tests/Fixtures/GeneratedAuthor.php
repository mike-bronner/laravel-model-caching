<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeneratedAuthor extends Model
{
    use Cachable;
    use SoftDeletes;

    protected $table = 'authors';
    protected $fillable = [
        'name',
        'email',
        'is_famous',
    ];
}
