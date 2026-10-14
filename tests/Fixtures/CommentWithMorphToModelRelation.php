<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommentWithMorphToModelRelation extends Comment
{
    protected $table = "comments";

    public function model(): MorphTo
    {
        return $this->morphTo("model", "commentable_type", "commentable_id");
    }
}
