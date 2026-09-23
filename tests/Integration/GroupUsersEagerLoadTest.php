<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use GeneaLabs\LaravelModelCaching\Providers\Service as LaravelModelCachingService;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

// Regression test for an eager-loaded many-to-many relation going stale:
//
//     Group::with(['users'])->where('workspace_id', $id)->get();
//
// returned fewer users than the pivot table held, and users added to a group
// afterwards never showed up. The eager-loaded users are cached inside the
// Group query's entry, whose tags named the Group and User models but not the
// pivot table, so a pivot row written by a cachable model on that table never
// invalidated the entry.
//
// Each test compares the per-group user ids the cached query returns with the
// pivot table read directly, so any stale or dropped row fails the assertion
// with the exact group and ids involved.
//
// This test is self-contained: it runs on SQLite and the array cache store,
// which supports tags, so it does not need the Redis server the rest of the
// suite requires.
class GroupUsersEagerLoadTest extends TestCase
{
    // Group sizes in the reported workspace: 50 + 30 + 10 + 32 = 122.
    private const GROUP_SIZES = [50, 30, 10, 32];

    private const WORKSPACE_ID = 1;

    private const OTHER_WORKSPACE_ID = 2;

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
        // Same settings under a second name. The cross-connection test points
        // it at the testing connection's PDO, so both names see the same data
        // but key their cache under different prefixes.
        $app['config']->set('database.connections.tenant', [
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

        Schema::create('lmc_workspace_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('workspace_id');
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('lmc_workspace_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('lmc_group_user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('group_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
        });

        $this->seedWorkspace();
    }

    // Users overlap between groups, as they do in a real workspace: a user in
    // several groups comes back once per group from the eager-load query.
    private function seedWorkspace(): void
    {
        $users = collect(range(1, 90))
            ->map(fn (int $i) => WorkspaceUser::create(['name' => "User {$i}"]));

        $offset = 0;

        foreach (self::GROUP_SIZES as $index => $size) {
            $group = WorkspaceGroup::create([
                'workspace_id' => self::WORKSPACE_ID,
                'name' => "Group {$index}",
            ]);

            // Stride through the user list so groups share some users.
            $ids = $users->slice($offset % 60, $size)->pluck('id')->all();
            $group->users()->attach($ids);
            $offset += 17;
        }

        foreach (range(1, 3) as $index) {
            WorkspaceGroup::create([
                'workspace_id' => self::OTHER_WORKSPACE_ID,
                'name' => "Other {$index}",
            ])->users()->attach($users->random(12)->pluck('id')->all());
        }
    }

    private function runReportedQuery(int $workspaceId = self::WORKSPACE_ID)
    {
        return WorkspaceGroup::with(['users'])
            ->where('workspace_id', $workspaceId)
            ->get();
    }

    private function cachedMembership(int $workspaceId = self::WORKSPACE_ID): array
    {
        return $this->runReportedQuery($workspaceId)
            ->mapWithKeys(fn (WorkspaceGroup $group) => [
                $group->id => $group->users->pluck('id')->sort()->values()->all(),
            ])
            ->sortKeys()
            ->all();
    }

    private function databaseMembership(int $workspaceId = self::WORKSPACE_ID): array
    {
        $groupIds = DB::table('lmc_workspace_groups')
            ->where('workspace_id', $workspaceId)
            ->pluck('id');

        return $groupIds
            ->mapWithKeys(fn ($groupId) => [
                $groupId => DB::table('lmc_group_user')
                    ->where('group_id', $groupId)
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all(),
            ])
            ->sortKeys()
            ->all();
    }

    private function assertCachedQueryMatchesDatabase(int $workspaceId = self::WORKSPACE_ID): void
    {
        $expected = $this->databaseMembership($workspaceId);
        $actual = $this->cachedMembership($workspaceId);

        $this->assertSame(
            array_sum(array_map('count', $expected)),
            array_sum(array_map('count', $actual)),
            'Total users across groups differs from the database.',
        );
        $this->assertSame($expected, $actual);
    }

    private function lastGroup(): WorkspaceGroup
    {
        return WorkspaceGroup::where('workspace_id', self::WORKSPACE_ID)
            ->orderByDesc('id')
            ->first();
    }

    private function userNotInGroup(WorkspaceGroup $group): WorkspaceUser
    {
        $memberIds = DB::table('lmc_group_user')
            ->where('group_id', $group->id)
            ->pluck('user_id');

        return WorkspaceUser::whereNotIn('id', $memberIds)->orderBy('id')->first();
    }

    public function testSeedMatchesTheReportedShape(): void
    {
        $this->assertSame(
            122,
            DB::table('lmc_group_user')
                ->whereIn('group_id', DB::table('lmc_workspace_groups')
                    ->where('workspace_id', self::WORKSPACE_ID)
                    ->select('id'))
                ->count(),
        );
    }

    public function testColdAndWarmQueriesReturnEveryUser(): void
    {
        // First call fills the cache, second is served from it.
        $this->assertCachedQueryMatchesDatabase();
        $this->assertCachedQueryMatchesDatabase();

        $this->assertSame(
            122,
            $this->runReportedQuery()->sum(fn ($group) => $group->users->count()),
        );
    }

    public function testQueryIsActuallyServedFromCacheOnSecondCall(): void
    {
        $this->runReportedQuery();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->runReportedQuery();

        $this->assertSame([], DB::getQueryLog(), 'Second call should not hit the database.');
    }

    public function testOtherWorkspaceWarmedFirstDoesNotLeakIntoThisOne(): void
    {
        $this->assertCachedQueryMatchesDatabase(self::OTHER_WORKSPACE_ID);
        $this->assertCachedQueryMatchesDatabase(self::WORKSPACE_ID);
        $this->assertCachedQueryMatchesDatabase(self::OTHER_WORKSPACE_ID);
    }

    public function testStringWorkspaceIdFromARequestMatchesDatabase(): void
    {
        $this->assertCachedQueryMatchesDatabase();

        $fromRequest = WorkspaceGroup::with(['users'])
            ->where('workspace_id', (string) self::WORKSPACE_ID)
            ->get()
            ->sum(fn ($group) => $group->users->count());

        $this->assertSame(122, $fromRequest);
    }

    // Every way the downstream app might add a user to a group after the
    // query has been cached. Each one must make the next query return the
    // new member.
    public static function additionPaths(): array
    {
        return [
            'group->users()->attach(id)' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $group->users()->attach($user->id);
                },
            ],
            'group->users()->attach(model)' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $group->users()->attach($user);
                },
            ],
            'user->groups()->attach(id) (inverse side)' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $user->groups()->attach($group->id);
                },
            ],
            'group->users()->syncWithoutDetaching' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $group->users()->syncWithoutDetaching([$user->id]);
                },
            ],
            'group->users()->sync (full list)' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $ids = $group->users()->pluck('lmc_workspace_users.id')->push($user->id)->all();
                    $group->users()->sync($ids);
                },
            ],
            'group->users()->toggle' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $group->users()->toggle([$user->id]);
                },
            ],
            'group->users()->save(existing user)' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $group->users()->save($user);
                },
            ],
            'group->users()->create(new user)' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $group->users()->create(['name' => 'Brand new']);
                },
            ],
            'group->users()->createMany' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $group->users()->createMany([['name' => 'New A'], ['name' => 'New B']]);
                },
            ],
            'group->users()->saveMany(existing users)' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    $group->users()->saveMany([$user]);
                },
            ],
            'Cachable model on the pivot table ::create' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    CachableGroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);
                },
            ],
            'Cachable Pivot class ::create' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    CachableGroupUserPivot::create(['group_id' => $group->id, 'user_id' => $user->id]);
                },
            ],
        ];
    }

    // Writes to the pivot table that never pass through a cachable model or
    // relation, so the package has nothing to observe and cannot invalidate.
    // The app has to flush the related model's cache itself. Flushing the
    // parent is not enough: the eager-load query for the related models has
    // its own entry, tagged with the related model and the pivot table, and
    // the rebuilt parent entry would read the stale rows back from it.
    public static function unobservedAdditionPaths(): array
    {
        return [
            'plain Pivot class ::create' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    GroupUserPivot::create(['group_id' => $group->id, 'user_id' => $user->id]);
                },
            ],
            'plain Model on the pivot table ::create' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    UncachedGroupUser::create(['group_id' => $group->id, 'user_id' => $user->id]);
                },
            ],
            'DB::table(pivot)->insert' => [
                function (WorkspaceGroup $group, WorkspaceUser $user) {
                    DB::table('lmc_group_user')->insert(['group_id' => $group->id, 'user_id' => $user->id]);
                },
            ],
        ];
    }

    #[DataProvider('unobservedAdditionPaths')]
    public function testUnobservedPivotWritesShowUpAfterFlushingTheRelatedModelCache(callable $add): void
    {
        $this->assertCachedQueryMatchesDatabase();

        $group = $this->lastGroup();
        $add($group, $this->userNotInGroup($group));
        (new WorkspaceUser)->flushCache();

        $this->assertCachedQueryMatchesDatabase();
    }

    #[DataProvider('additionPaths')]
    public function testAddingAUserAfterTheQueryIsCachedShowsUp(callable $add): void
    {
        $this->assertCachedQueryMatchesDatabase();

        $group = $this->lastGroup();
        $add($group, $this->userNotInGroup($group));

        $this->assertCachedQueryMatchesDatabase();
    }

    // The eager-loaded users are cached inside the Group query's entry, so
    // that entry is only invalidated through its own tags. A write to the
    // pivot table can only reach it through a pivot-table tag, and only if
    // the tag's prefix is the one the writer flushes, so the whole tag list
    // is asserted, not just a suffix.
    public function testEagerLoadedBelongsToManyTagsIncludeThePivotTable(): void
    {
        $builder = WorkspaceGroup::with(['users'])->where('workspace_id', self::WORKSPACE_ID);
        $tags = (fn () => $this->makeCacheTags())->call($builder);

        $this->assertSame(
            [
                'genealabs:laravel-model-caching:testing::memory::genealabslaravelmodelcachingtestsintegrationworkspacegroup',
                'genealabs:laravel-model-caching:testing::memory::genealabslaravelmodelcachingtestsintegrationworkspaceuser',
                'genealabs:laravel-model-caching:testing::memory::lmc-group-user',
                'genealabs:laravel-model-caching:testing::memory::lmc-workspace-groups',
            ],
            $tags,
        );
    }

    // Compares the eager-loaded membership of any group model on the pivot
    // table with the pivot table itself.
    private function assertGroupsMatchDatabase(string $groupClass): void
    {
        $cached = $groupClass::with(['users'])
            ->where('workspace_id', self::WORKSPACE_ID)
            ->get()
            ->mapWithKeys(fn (Model $group) => [
                $group->id => $group->users->pluck('id')->sort()->values()->all(),
            ])
            ->sortKeys()
            ->all();

        $this->assertSame($this->databaseMembership(), $cached);
    }

    // The parent declares its own $cachePrefix, the cachable model on the
    // pivot table does not. The pivot tag must still be one that model's
    // write flushes.
    public function testPivotModelWriteReachesAParentWithItsOwnCachePrefix(): void
    {
        $this->assertGroupsMatchDatabase(PrefixedWorkspaceGroup::class);

        $group = $this->lastGroup();
        CachableGroupUser::create(['group_id' => $group->id, 'user_id' => $this->userNotInGroup($group)->id]);

        $this->assertGroupsMatchDatabase(PrefixedWorkspaceGroup::class);
    }

    // The related model declares its own $cachePrefix. The eager-load query
    // for it has its own entry, tagged with the pivot table through the join,
    // and the rebuilt parent entry reads from it, so that tag has to be
    // reachable by the pivot write too.
    public function testPivotModelWriteReachesRelatedModelsWithTheirOwnCachePrefix(): void
    {
        $this->assertGroupsMatchDatabase(GroupWithPrefixedUsers::class);

        $group = $this->lastGroup();
        CachableGroupUser::create(['group_id' => $group->id, 'user_id' => $this->userNotInGroup($group)->id]);

        $this->assertGroupsMatchDatabase(GroupWithPrefixedUsers::class);
    }

    // The parent lives on another connection. Laravel creates the relation's
    // pivots on the parent's connection, so a pivot deleted through the
    // relation flushes a tag under that connection, not the default one.
    public function testPivotDeletedThroughTheRelationReachesAParentOnAnotherConnection(): void
    {
        DB::connection('tenant')->setPdo(DB::connection('testing')->getPdo());

        $this->assertGroupsMatchDatabase(TenantWorkspaceGroup::class);

        TenantWorkspaceGroup::with(['users'])
            ->where('workspace_id', self::WORKSPACE_ID)
            ->orderByDesc('id')
            ->first()
            ->users
            ->first()
            ->pivot
            ->delete();

        $this->assertGroupsMatchDatabase(TenantWorkspaceGroup::class);
    }

    public function testRepeatedlyAddingUsersKeepsShowingUp(): void
    {
        $group = $this->lastGroup();

        foreach (range(1, 10) as $ignored) {
            $this->assertCachedQueryMatchesDatabase();
            $group->users()->attach($this->userNotInGroup($group)->id);
        }

        $this->assertCachedQueryMatchesDatabase();
    }
}

class WorkspaceGroup extends Model
{
    use Cachable;

    protected $table = 'lmc_workspace_groups';

    protected $fillable = ['workspace_id', 'name'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceUser::class, 'lmc_group_user', 'group_id', 'user_id')
            ->using(GroupUserPivot::class)
            ->withTimestamps();
    }
}

class WorkspaceUser extends Model
{
    use Cachable;

    protected $table = 'lmc_workspace_users';

    protected $fillable = ['name'];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceGroup::class, 'lmc_group_user', 'user_id', 'group_id')
            ->using(GroupUserPivot::class)
            ->withTimestamps();
    }
}

class GroupUserPivot extends Pivot
{
    protected $table = 'lmc_group_user';

    public $incrementing = true;

    protected $fillable = ['group_id', 'user_id'];
}

class CachableGroupUser extends Model
{
    use Cachable;

    protected $table = 'lmc_group_user';

    protected $fillable = ['group_id', 'user_id'];
}

class CachableGroupUserPivot extends Pivot
{
    use Cachable;

    protected $table = 'lmc_group_user';

    public $incrementing = true;

    protected $fillable = ['group_id', 'user_id'];
}

class UncachedGroupUser extends Model
{
    protected $table = 'lmc_group_user';

    protected $fillable = ['group_id', 'user_id'];
}

class PrefixedWorkspaceGroup extends Model
{
    use Cachable;

    protected $cachePrefix = 'groups';

    protected $table = 'lmc_workspace_groups';

    protected $fillable = ['workspace_id', 'name'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceUser::class, 'lmc_group_user', 'group_id', 'user_id');
    }
}

class PrefixedWorkspaceUser extends Model
{
    use Cachable;

    protected $cachePrefix = 'users';

    protected $table = 'lmc_workspace_users';

    protected $fillable = ['name'];
}

class GroupWithPrefixedUsers extends Model
{
    use Cachable;

    protected $table = 'lmc_workspace_groups';

    protected $fillable = ['workspace_id', 'name'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(PrefixedWorkspaceUser::class, 'lmc_group_user', 'group_id', 'user_id');
    }
}

class TenantWorkspaceGroup extends Model
{
    use Cachable;

    protected $connection = 'tenant';

    protected $table = 'lmc_workspace_groups';

    protected $fillable = ['workspace_id', 'name'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceUser::class, 'lmc_group_user', 'group_id', 'user_id')
            ->using(CachableGroupUserPivot::class);
    }
}
