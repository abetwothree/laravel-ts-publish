<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use LogicException;

/**
 * Untyped methods whose own returns hold a literal null or may end without a value, beside ones that do neither.
 */
final class LiteralNullReturns
{
    /** A value that may be null. */
    private ?string $stored = null;

    /** Returns null for a NULL. */
    public function returnsNull($value)
    {
        if ($value === null) {
            return null;
        }

        return 'x';
    }

    /** Returns nothing for a NULL. */
    public function returnsNothing($value)
    {
        if ($value === null) {
            return;
        }

        return 'x';
    }

    /** Falls off its end for any value but a string. */
    public function fallsOff($value)
    {
        if (is_string($value)) {
            return $value;
        }
    }

    /** Returns null through a ternary arm. */
    public function ternaryArm($value)
    {
        return $value === '' ? null : 'x';
    }

    /** Returns null through a short ternary's arm. */
    public function shortTernaryArm($value)
    {
        return $value ?: null;
    }

    /** Returns null through a ternary nested in another's arm. */
    public function nestedTernaryArm($value)
    {
        return $value === '' ? 'a' : ($value === 'b' ? null : 'c');
    }

    /** Returns null through a match arm. */
    public function matchArm($value)
    {
        return match ($value) {
            'a' => 'x',
            default => null,
        };
    }

    /** Returns null through `??`. */
    public function coalesceArm($value)
    {
        return $value ?? null;
    }

    /** Returns a property that may hold null. */
    public function readsProperty()
    {
        return $this->stored;
    }

    /** Returns what a helper that returns null gives. */
    public function callsHelper()
    {
        return $this->helper();
    }

    /** Returns a list a closure returning null maps. */
    public function closureReturnsNull()
    {
        return array_map(function ($item) {
            return null;
        }, ['a']);
    }

    /** Replaces a NULL through `??` with a string. */
    public function coalesceDefault($value)
    {
        return $value ?? 'd';
    }

    /** Declares mixed, so it throws rather than falls off its end. */
    public function declaredMixed($value): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        throw new LogicException('Not a string.');
    }

    /** Returns a list an arrow function returning null maps. */
    public function arrowFunctionNull()
    {
        return array_map(fn ($item) => null, ['a']);
    }

    /** Defaults its parameter to null, and returns a string. */
    public function nullDefault($value = null)
    {
        return 'x';
    }

    /** Tests for null in a ternary's condition, returning a string on either arm. */
    public function nullCondition($value)
    {
        return $value === null ? 'a' : 'b';
    }

    /** Returns on every arm of an if/elseif/else. */
    public function ifElseAllReturn($value)
    {
        if (is_string($value)) {
            return 'a';
        } elseif (is_int($value)) {
            return 'b';
        } else {
            throw new LogicException('Neither.');
        }
    }

    /** Returns from every case of a switch with a default. */
    public function switchAllReturn($value)
    {
        switch ($value) {
            case 'a':
            case 'b':
                return 'x';
            default:
                return 'y';
        }
    }

    /** Returns from its try and its catch. */
    public function tryAllReturn($value)
    {
        try {
            return 'a'.$value;
        } catch (LogicException) {
            return 'z';
        }
    }

    /** Returns from its finally whatever its try does. */
    public function finallyReturns($value)
    {
        try {
            $value = (string) $value;
        } finally {
            return 'f';
        }
    }

    /** Returns from a switch with no default, so a value no case matches falls off. */
    public function switchWithoutDefault($value)
    {
        switch ($value) {
            case 'a':
                return 'x';
        }
    }

    /** Breaks out of a switch whose cases otherwise return, so it may fall off. */
    public function switchBreaks($value)
    {
        switch ($value) {
            case 'a':
                if ($value === 'a') {
                    break;
                }

                return 'x';
            default:
                return 'y';
        }
    }

    /** Returns from its try, but its catch ends without a value. */
    public function catchFallsOff($value)
    {
        try {
            return 'a'.$value;
        } catch (LogicException) {
            $value = null;
        }
    }

    /** Ends in abort(), which Laravel declares `@return never`. */
    public function abortsAtEnd($value)
    {
        if (is_string($value)) {
            return 'x';
        }

        abort(500);
    }

    /** Ends in abort_if(), which returns when its condition is false. */
    public function abortIfAtEnd($value)
    {
        if (is_string($value)) {
            return 'x';
        }

        abort_if($value === 0, 500);
    }

    /** Ends in a call to its own method declaring `never`. */
    public function endsInOwnNeverMethod($value)
    {
        if (is_string($value)) {
            return 'x';
        }

        $this->fail();
    }

    /** Ends in a static call to its own method documenting `@return never`. */
    public function endsInDocumentedNeverMethod($value)
    {
        if (is_string($value)) {
            return 'x';
        }

        self::failQuietly();
    }

    /** Ends in a match whose every arm throws. */
    public function endsInThrowingMatch($value)
    {
        if (is_string($value)) {
            return 'x';
        }

        match (true) {
            $value === null => throw new LogicException('A NULL.'),
            default => throw new LogicException('Not a string.'),
        };
    }

    /** Ends in a match one of whose arms returns a value. */
    public function endsInValueMatch($value)
    {
        if (is_string($value)) {
            return 'x';
        }

        match (true) {
            $value === null => throw new LogicException('A NULL.'),
            default => 'y',
        };
    }

    /** Loops until it returns. */
    public function loopsUntilItReturns($value)
    {
        while (true) {
            if (is_string($value)) {
                return 'x';
            }

            $value = (string) $value;
        }
    }

    /** Loops with no condition until it returns, a `continue` only starting the next turn. */
    public function loopsForeverUntilItReturns($value)
    {
        for (; ;) {
            if (! is_string($value)) {
                $value = (string) $value;

                continue;
            }

            return 'x';
        }
    }

    /** Loops until a `break` leaves the loop, so may fall off. */
    public function loopsUntilItBreaks($value)
    {
        while (true) {
            if (is_string($value)) {
                break;
            }

            $value = (string) $value;
        }
    }

    /** Loops until a `break 2` in a switch leaves the loop, so may fall off. */
    public function loopsUntilASwitchBreaksOut($value)
    {
        while (true) {
            switch ($value) {
                case 'a':
                    break 2;
                default:
                    $value = 'a';
            }
        }
    }

    /** Runs a do-while whose body always returns. */
    public function doWhileReturns($value)
    {
        do {
            return 'x';
        } while ($value);
    }

    /** Runs a do-while whose body may run to its end, so may fall off. */
    public function doWhileFinishes($value)
    {
        do {
            $value = (string) $value;
        } while ($value === '');
    }

    /**
     * Throws, as its `@return never` says.
     *
     * @return never
     */
    private static function failQuietly()
    {
        throw new LogicException('Failed.');
    }

    /** Throws, as its native `never` says. */
    private function fail(): never
    {
        throw new LogicException('Failed.');
    }

    /** A helper that returns null. */
    private function helper()
    {
        return null;
    }
}
