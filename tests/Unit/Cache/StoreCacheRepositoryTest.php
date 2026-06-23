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

it('does not persist the key index on put (only on commit)', function () {
    $this->repo->put('a', ['x' => 1]);

    expect(Cache::store('array')->get('ts-publish:__index__'))->toBeNull();

    $this->repo->commit();

    expect(Cache::store('array')->get('ts-publish:__index__'))->toBe(['a']);
});

it('persists the index on commit so a fresh instance can flush it', function () {
    $this->repo->put('a', ['x' => 1]);
    $this->repo->put('b', ['x' => 2]);
    $this->repo->commit();

    // A brand-new instance (empty in-memory index) must still flush prior keys.
    $fresh = new StoreCacheRepository(Cache::store('array'), 'ts-publish');
    $fresh->flush();

    expect($this->repo->get('a'))->toBeNull()
        ->and($this->repo->get('b'))->toBeNull();
});

it('rejects an unsigned attacker-written store entry', function () {
    $repo = new StoreCacheRepository(Cache::store('array'), 'ts-publish', 'signing-secret');

    // The UNHARDENED repository stored/accepted a raw array. An attacker able to
    // write the store could plant a malicious manifest entry that way; with
    // signing, get() must refuse a payload that is not a valid signed string.
    Cache::store('array')->forever('ts-publish:evil', ['snapshot' => 'attacker-controlled']);

    expect($repo->get('evil'))->toBeNull();
});

it('round-trips a signed payload', function () {
    $repo = new StoreCacheRepository(Cache::store('array'), 'ts-publish', 'signing-secret');

    $repo->put('entry', ['snapshot' => 'legit']);

    expect($repo->get('entry'))->toBe(['snapshot' => 'legit']);
});

it('rejects a store entry whose signature does not match', function () {
    $repo = new StoreCacheRepository(Cache::store('array'), 'ts-publish', 'signing-secret');
    $repo->put('entry', ['snapshot' => 'legit']);

    Cache::store('array')->forever('ts-publish:entry', 'deadbeef:'.serialize(['snapshot' => 'evil']));

    expect($repo->get('entry'))->toBeNull();
});
