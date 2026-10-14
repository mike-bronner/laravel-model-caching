<?php

test('preferring the other trait base builder loads', function (): void {
    expect(loadTraitCollisionForm("AuthorPreferringOtherBaseBuilder"))->toBe("loaded");
});

test('an alias of the cachable base builder fails to load', function (): void {
    $message = "An alias was defined for "
        . "GeneaLabs\\LaravelModelCaching\\Traits\\Cachable::newBaseQueryBuilder "
        . "but this method does not exist";

    expect(loadTraitCollisionForm("AuthorAliasingCachableBaseBuilder"))->toContain($message);
});

test('preferring the cachable base builder fails to load', function (): void {
    $message = "A precedence rule was defined for "
        . "GeneaLabs\\LaravelModelCaching\\Traits\\Cachable::newBaseQueryBuilder "
        . "but this method does not exist";

    expect(loadTraitCollisionForm("AuthorPreferringCachableBaseBuilder"))->toContain($message);
});
