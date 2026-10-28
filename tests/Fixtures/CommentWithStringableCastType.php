<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommentWithStringableCastType extends Comment
{
    protected $table = 'comments';

    protected $casts = [
        'commentable_type' => AsStringable::class,
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
