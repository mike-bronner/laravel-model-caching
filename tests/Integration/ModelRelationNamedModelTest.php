<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use GeneaLabs\LaravelModelCaching\Providers\Service as LaravelModelCachingService;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use RuntimeException;

// Regression test for a cachable model that defines a relationship named
// model(). The flush after every write resolved the model it caches for by
// reading $this->model, which on a model goes through Eloquent's magic
// accessors and lazy-loads that relationship. A relation that cannot be
// lazy-loaded (a HasOneDeep ending in a MorphTo produced invalid SQL in the
// downstream report) made every create, update and delete fail.
//
// Self-contained on SQLite and the array cache store, like
// GroupUsersEagerLoadTest, so it runs without Redis.
class ModelRelationNamedModelTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelModelCachingService::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('laravel-model-caching.store', 'array');
        $app['config']->set('laravel-model-caching.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('lmc_model_owners', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('lmc_model_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('owner_id')->nullable();
            $table->string('value');
            $table->timestamps();
        });

        FieldWithModelRelation::$modelRelationCalls = 0;
    }

    public function testWritesDoNotLoadARelationNamedModel(): void
    {
        $owner = ModelOwner::create(['name' => 'Owner']);

        $field = FieldWithModelRelation::create(['owner_id' => $owner->id, 'value' => 'a']);
        $field->update(['value' => 'b']);
        $field->delete();

        $this->assertSame(
            0,
            FieldWithModelRelation::$modelRelationCalls,
            'Flushing the cache after a write must not resolve the model() relationship',
        );
        $this->assertFalse($field->relationLoaded('model'));
    }

    public function testWritesSucceedWhenTheModelRelationCannotBeLoaded(): void
    {
        $field = FieldWithUnloadableModelRelation::create(['value' => 'a']);
        $field->update(['value' => 'b']);
        $field->delete();

        $this->assertFalse(FieldWithUnloadableModelRelation::query()->whereKey($field->id)->exists());
    }

    public function testTheModelRelationStillWorksWhenAskedFor(): void
    {
        $owner = ModelOwner::create(['name' => 'Owner']);
        $field = FieldWithModelRelation::create(['owner_id' => $owner->id, 'value' => 'a']);

        $this->assertTrue($field->fresh()->model->is($owner));
    }
}

class ModelOwner extends Model
{
    use Cachable;

    protected $table = 'lmc_model_owners';

    protected $fillable = ['name'];
}

class FieldWithModelRelation extends Model
{
    use Cachable;

    public static int $modelRelationCalls = 0;

    protected $table = 'lmc_model_fields';

    protected $fillable = ['owner_id', 'value'];

    public function model(): BelongsTo
    {
        static::$modelRelationCalls++;

        return $this->belongsTo(ModelOwner::class, 'owner_id');
    }
}

class FieldWithUnloadableModelRelation extends Model
{
    use Cachable;

    protected $table = 'lmc_model_fields';

    protected $fillable = ['owner_id', 'value'];

    public function model(): BelongsTo
    {
        throw new RuntimeException('The model() relationship was resolved during a write.');
    }
}
