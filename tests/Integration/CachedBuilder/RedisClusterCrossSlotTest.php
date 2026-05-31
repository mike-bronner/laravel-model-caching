<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CrossSlotFlushRepository;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

// Regression coverage for issue #594: saving a model against a Redis Cluster
// reached through a single (non-cluster) connection threw "CROSSSLOT Keys in
// request don't hash to the same slot" because Laravel's tagged flush runs a
// multi-key Lua script. invalidateTags() must recover via slot-safe deletes.
class RedisClusterCrossSlotTest extends IntegrationTestCase
{
    private const TAG = 'cross-slot-tag';

    public function testInvalidateTagsRecoversFromCrossSlotFailure(): void
    {
        $repository = app('cache')->store('model');
        $store = $repository->getStore();

        $repository->tags([self::TAG])->forever('entry-a', 'value-a');
        $repository->tags([self::TAG])->forever('entry-b', 'value-b');

        $this->assertSame('value-a', $repository->tags([self::TAG])->get('entry-a'));
        $this->assertNotEmpty($this->modelCacheKeys());

        $throwingRepository = new CrossSlotFlushRepository($store);
        $modelCacheRepository = new ModelCacheRepository($throwingRepository, false);

        // The CROSSSLOT exception must be swallowed in favor of the slot-safe path.
        $modelCacheRepository->invalidateTags([self::TAG]);

        // The cache is genuinely cleared, not merely namespaced away: every key
        // written under the tag is physically removed, so no orphans leak.
        $this->assertNull($repository->tags([self::TAG])->get('entry-a'));
        $this->assertNull($repository->tags([self::TAG])->get('entry-b'));
        $this->assertSame([], $this->modelCacheKeys());
    }

    public function testInvalidateTagsRethrowsNonCrossSlotFailures(): void
    {
        $store = app('cache')->store('model')->getStore();
        $throwingRepository = new class($store) extends CrossSlotFlushRepository
        {
            public function tags($names)
            {
                $tagged = parent::tags($names);

                return new class($tagged->getStore(), $tagged->getTags()) extends \Illuminate\Cache\RedisTaggedCache
                {
                    public function flush()
                    {
                        throw new \RedisException('READONLY You can\'t write against a read only replica.');
                    }
                };
            }
        };
        $modelCacheRepository = new ModelCacheRepository($throwingRepository, false);

        $this->expectException(\RedisException::class);

        $modelCacheRepository->invalidateTags([self::TAG]);
    }

    private function modelCacheKeys(): array
    {
        $token = (int) env('TEST_TOKEN', 1);
        $pattern = "lmc-test-{$token}:*";
        $client = app('redis')->connection('model-cache')->client();
        $cursor = null;
        $keys = [];

        do {
            $found = $client->scan($cursor, $pattern, 1000);

            if (is_array($found)) {
                $keys = array_merge($keys, $found);
            }
        } while ($cursor > 0);

        return $keys;
    }
}
