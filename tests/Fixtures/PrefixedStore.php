<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A Store declaring its own cache prefix, reached through Book::prefixedStores().
 *
 * It exists so a relation's resolved model is observable. Book declares no
 * prefix and Store declares no prefix, so a relation resolving to its parent,
 * or to nothing at all, produces the same default prefix as one resolving
 * correctly. Only a related model with a prefix of its own tells those apart.
 */
class PrefixedStore extends Model
{
    use Cachable;

    protected $cachePrefix = "store-prefix";
    protected $fillable = [
        'address',
        'name',
    ];
    protected $table = "stores";

    public function books() : BelongsToMany
    {
        return $this->belongsToMany(Book::class, "book_store", "store_id", "book_id");
    }
}
