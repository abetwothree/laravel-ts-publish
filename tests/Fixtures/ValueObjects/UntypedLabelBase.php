<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload reading untyped members a subclass overrides through `self` and `static` spellings. */
class UntypedLabelBase
{
    public $tag = 1;

    /** A label no declaration types. */
    public function label()
    {
        return 1;
    }

    /** A label no subclass can override. */
    final public function fixedLabel()
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
            'tagSelf' => $me->tag,
            'fixedSelf' => $me->fixedLabel(),
        ];
    }
}
