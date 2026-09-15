<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\Post;
use Workbench\App\Services\UrlService;

/** Return declarations `ReceiverClassResolver::returnClasses()` reads, one shape per method. */
final class ReceiverReturnsProbe
{
    /** A union of classes plus null. */
    public function classUnion(): UrlService|Post|null
    {
        return null;
    }

    /** A union with a builtin arm. */
    public function builtinUnion(): UrlService|string
    {
        return '';
    }

    /** A native static return. */
    public function fluent(): static
    {
        return $this;
    }

    /**
     * A docblock-only union, resolved against this file's imports.
     *
     * @return UrlService|Post|null
     */
    public function docblockUnion()
    {
        return null;
    }

    /**
     * A docblock-only `$this`.
     *
     * @return $this
     */
    public function docblockThis()
    {
        return $this;
    }

    /**
     * A docblock naming something that is not a class.
     *
     * @return UrlService|NotARealClass
     */
    public function docblockUnresolvable()
    {
        return null;
    }
}
