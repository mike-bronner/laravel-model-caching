<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

class PostWithUncachedTags extends Post
{
    protected $table = "posts";

    public function getMorphClass(): string
    {
        return Post::class;
    }

    public function uncachedTags(): MorphToMany
    {
        return $this->morphToMany(UncachedTag::class, "taggable", relatedPivotKey: "tag_id");
    }
}
