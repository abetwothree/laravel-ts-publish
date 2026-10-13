<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\Concerns\SignsCachePayloads;
use AbeTwoThree\LaravelTsPublish\Cache\FileCacheRepository;
use AbeTwoThree\LaravelTsPublish\Cache\StoreCacheRepository;
use AbeTwoThree\LaravelTsPublish\Runners\BaseRunner;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ByReferenceReshapedResource;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch()->preset()->php();

// Pest's security preset bans serialize()/unserialize() outright; the cache backends need both to
// persist their own payloads. File payloads are HMAC-signed and read with allowed_classes:false;
// BaseRunner must allow classes so transformer snapshots can be rebuilt from the manifest.
arch()->preset()->security()
    ->ignoring([
        FileCacheRepository::class,
        StoreCacheRepository::class,
        SignsCachePayloads::class,
        BaseRunner::class,
        // A test fixture: it calls shuffle() only to pin what the call leaves in its by-reference argument.
        ByReferenceReshapedResource::class,
    ]);

arch()->preset()->laravel();
