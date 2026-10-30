<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * A test-only payload naming itself as `self` and `static` in an inline `@var` and `self` as a callback's type, and
 * reading a `static` local in a helper a `self::` call reaches.
 */
class SelfSpellingBase
{
    /** A label wider than the one the subclass narrows it to. */
    public function label(): int|string
    {
        return 1;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        /** @var self $me */
        $me = $this;
        /** @var static $late */
        $late = $this;

        return [
            'varSelf' => $me->label(),
            'varStatic' => $late->label(),
            'mapSelf' => collect([$this])->map(fn (self $s) => $s->label())->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function lateLabels(): array
    {
        return ['viaStatic' => self::staticLabel(), 'viaThis' => self::thisLabel()];
    }

    /** The label of a local declared `static`, from a body whose return only it types. */
    protected function staticLabel()
    {
        /** @var static $me */
        $me = $this;

        return $me->label();
    }

    /** The label `$this` holds, from a body whose return only it types. */
    protected function thisLabel()
    {
        return $this->label();
    }
}
