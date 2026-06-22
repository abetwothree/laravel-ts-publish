<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\StoreCacheRepository;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::store('array')->clear();
    $this->repo = new StoreCacheRepository(Cache::store('array'), 'ts-publish');
});

it('stores and retrieves array payloads with a prefix', function () {
    $this->repo->put('alpha', ['n' => 1]);

    expect($this->repo->get('alpha'))->toBe(['n' => 1])
        ->and(Cache::store('array')->has('ts-publish:alpha'))->toBeTrue();
});

it('returns null for missing keys', function () {
    expect($this->repo->get('nope'))->toBeNull();
});

it('forgets a single key and flushes only its own keys', function () {
    $this->repo->put('a', ['x' => 1]);
    $this->repo->put('b', ['x' => 2]);
    Cache::store('array')->put('unrelated', 'keep');

    $this->repo->forget('a');
    expect($this->repo->get('a'))->toBeNull()->and($this->repo->get('b'))->toBe(['x' => 2]);

    $this->repo->flush();
    expect($this->repo->get('b'))->toBeNull()
        ->and(Cache::store('array')->get('unrelated'))->toBe('keep');
});
