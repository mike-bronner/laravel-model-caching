<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(config_path('laravel-model-caching.php'));
});

test('the config option publishes the config file', function () {
    File::delete(config_path('laravel-model-caching.php'));

    $this->artisan('modelCache:publish', ['--config' => true])
        ->assertExitCode(0);

    expect(File::get(config_path('laravel-model-caching.php')))
        ->toBe(File::get(__DIR__ . '/../../../../config/laravel-model-caching.php'));
});

test('the views and migrations options report that there is nothing to publish', function () {
    $this->artisan('modelCache:publish', ['--views' => true, '--migrations' => true])
        ->expectsOutputToContain('No publishable resources for tag [views]')
        ->expectsOutputToContain('No publishable resources for tag [migrations]')
        ->assertExitCode(0);

    expect(File::exists(config_path('laravel-model-caching.php')))->toBeFalse();
});

test('no option publishes nothing', function () {
    $this->artisan('modelCache:publish')
        ->assertExitCode(0);

    expect(File::exists(config_path('laravel-model-caching.php')))->toBeFalse();
});

test('the assets option publishes without error', function () {
    $this->artisan('modelCache:publish', ['--assets' => true])
        ->expectsOutputToContain('No publishable resources for tag [assets]')
        ->assertExitCode(0);
});
