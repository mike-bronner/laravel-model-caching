<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
