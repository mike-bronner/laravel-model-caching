<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommentWithEnumCastType extends Comment
{
    protected $table = 'comments';

    protected $casts = [
        'commentable_type' => CommentableTypeEnum::class,
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
