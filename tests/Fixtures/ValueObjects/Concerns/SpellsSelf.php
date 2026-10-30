<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns;

/** A test-only trait naming its using class as `self` in an inline `@var` and as a callback's parameter type. */
trait SpellsSelf
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        /** @var self $me */
        $me = $this;

        return [
            'varSelf' => $me->label(),
            'mapSelf' => collect([$this])->map(fn (self $s) => $s->label())->all(),
        ];
    }
}
