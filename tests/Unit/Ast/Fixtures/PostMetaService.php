<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use ArrayIterator;
use Throwable;

/**
 * Vague signatures over literal bodies, each with a return the literal shape alone does not describe, so the body
 * fallback must keep the declaration's other arms or decline.
 */
final class PostMetaService
{
    public bool $flag = false;

    /** A nullable array that returns null early. */
    public function nullableMeta(): ?array
    {
        if ($this->flag) {
            return null;
        }

        return ['id' => 1];
    }

    /** A nullable array whose body never returns null; the declaration still admits it. */
    public function declaredNullableMeta(): ?array
    {
        return ['id' => 1];
    }

    /** An array or false. */
    public function metaOrFalse(): array|false
    {
        if ($this->flag) {
            return false;
        }

        return ['id' => 1];
    }

    /** An array or a string. */
    public function metaOrLabel(): array|string
    {
        if ($this->flag) {
            return 'none';
        }

        return ['id' => 1];
    }

    /** No declaration, and a string return the shape cannot describe. */
    public function untypedMeta()
    {
        if ($this->flag) {
            return 'none';
        }

        return ['id' => 1];
    }

    /** No declaration, and a bare return that yields null. */
    public function bareReturnMeta()
    {
        if ($this->flag) {
            return;
        }

        return ['id' => 1];
    }

    /** A mixed declaration, whose false return no arm but mixed names. */
    public function mixedMeta(): mixed
    {
        if ($this->flag) {
            return false;
        }

        return ['id' => 1];
    }

    /** An iterable that may be an iterator. */
    public function iterableMeta(): iterable
    {
        if ($this->flag) {
            return new ArrayIterator([]);
        }

        return ['id' => 1];
    }

    /** An array returned from another call on one path. */
    public function delegatingMeta(): array
    {
        if ($this->flag) {
            return $this->listing();
        }

        return ['id' => 1];
    }

    /** Two literals the branch sweep never reaches inside a try, so only the first would be read. */
    public function guardedMeta(): array
    {
        try {
            return ['id' => 1];
        } catch (Throwable) {
            return ['code' => 'failed'];
        }
    }

    /** A generator: the call returns a Generator, never the literal it returns. */
    public function generatedMeta(): iterable
    {
        yield 1;

        return ['id' => 1];
    }

    /**
     * A list, which no shape describes.
     *
     * @return list<int>
     */
    public function listing(): array
    {
        return [1, 2, 3];
    }
}
