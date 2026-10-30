<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class EagerLoadCountingComment extends Comment
{
    protected $table = "comments";

    public function commentable() : MorphTo
    {
        RelationCallLog::record($this, "commentable");

        return $this->morphTo("commentable");
    }
}
