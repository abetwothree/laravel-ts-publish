<?php

declare(strict_types=1);
use AbeTwoThree\LaravelTsPublish\Cache\Concerns\SignsCachePayloads;
use AbeTwoThree\LaravelTsPublish\Cache\FileCacheRepository;
use AbeTwoThree\LaravelTsPublish\Cache\StoreCacheRepository;
use AbeTwoThree\LaravelTsPublish\Runners\BaseRunner;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch()->preset()->php();

// The generation cache's file backend must (de)serialize its own on-disk
// payloads — the standard mechanism for a cache (Laravel's own file cache does
// the same). It is hardened: payloads can be HMAC-signed and `unserialize()` is
// called with `['allowed_classes' => false]`, so no objects are ever
// instantiated from cache files. These are trusted, locally-written files, not
// user input, so the security preset's blanket ban is scoped out for this class.
// BaseRunner::cachedGenerate() also uses serialize()/unserialize() to restore
// transformer snapshots from the manifest — these are our own trusted cache
// payloads; classes must be allowed here so transformer objects can be rebuilt
// (unlike the file backend's array payloads which use allowed_classes:false).
arch()->preset()->security()
    ->ignoring([FileCacheRepository::class, StoreCacheRepository::class, SignsCachePayloads::class, BaseRunner::class]);

arch()->preset()->laravel();
