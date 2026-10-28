<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommentWithObjectCastType extends Comment
{
    protected $table = 'comments';

    protected $casts = [
        'commentable_type' => CommentableTypeCast::class,
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
