<?php

declare(strict_types=1);
use AbeTwoThree\LaravelTsPublish\Cache\FileCacheRepository;

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
arch()->preset()->security()
    ->ignoring(FileCacheRepository::class);

arch()->preset()->laravel();
