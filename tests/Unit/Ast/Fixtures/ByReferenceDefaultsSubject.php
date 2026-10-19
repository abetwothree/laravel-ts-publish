<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Support\Collection;

/** Passes most of its untyped defaults to by-reference parameters, so only the by-value ones keep their default. */
final class ByReferenceDefaultsSubject extends ByReferenceDefaultsParent
{
    protected $queue = ['a'];

    protected $matches = 'none';

    protected $filled = ['a'];

    protected $stashed = ['a'];

    protected $parentFilled = ['a'];

    protected $constructed = ['a'];

    protected $parsed = ['a'];

    protected $missing = ['a'];

    protected $cursor = ['a', 'b'];

    protected $counted = ['a'];

    protected $opaque = ['a'];

    /** Writes through PHP's own by-reference parameters and the class's own, one of them named, one variadic. */
    public function run(Collection $other): void
    {
        array_push($this->queue, 1);
        preg_match(matches: $this->matches, pattern: '/x/', subject: 'x');
        $this->fill($this->filled);
        self::stash($this->stashed);
        parent::append($this->parentFilled);
        new ByReferenceDefaultsParent($this->constructed);
        sscanf('1 2', '%d %d', $first, $this->parsed);
        self::absent($this->missing);
        end($this->cursor);
        count($this->counted);
        $other->push($this->opaque);
    }

    /**
     * Appends to the array it is passed.
     *
     * @param  list<mixed>  $into
     */
    private function fill(array &$into): void
    {
        $into[] = 1;
    }

    /**
     * Appends to the array it is passed.
     *
     * @param  list<mixed>  $into
     */
    private static function stash(array &$into): void
    {
        $into[] = 1;
    }
}
