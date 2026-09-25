<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Support\Collection;
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
     * A docblock generic, which is still an instance of its base class.
     *
     * @return Collection<int, Post>
     */
    public function docblockGeneric()
    {
        return null;
    }

    /**
     * A docblock array of a generic class, which is an array rather than the class.
     *
     * @return Collection<int, Post>[]
     */
    public function docblockGenericArray()
    {
        return null;
    }

    /**
     * The @return Post named in this sentence is prose, not the tag.
     *
     * @return UrlService
     */
    public function docblockProseMention()
    {
        return null;
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
