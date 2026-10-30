<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWithAttributeDependentRelation extends Book
{
    protected $table = "books";

    public function dependent() : BelongsTo
    {
        return $this->getAttribute("use_tenant_author")
            ? $this->belongsTo(TenantAuthorWithTtl::class, "author_id")
            : $this->belongsTo(Author::class, "author_id");
    }
}
