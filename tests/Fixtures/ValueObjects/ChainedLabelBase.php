<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload reading untyped members a subclass overrides through what a `self` spelling's calls return. */
class ChainedLabelBase
{
    public $tag = 1;

    /** A label no declaration types. */
    public function label()
    {
        return 1;
    }

    /** A label its declaration types. */
    public function typedLabel(): int|string
    {
        return 1;
    }

    /** This object, declared `static`. */
    public function peer(): static
    {
        return $this;
    }

    /** This object, declared `self`. */
    public function peerSelf(): self
    {
        return $this;
    }

    /**
     * This object, documented `static`.
     *
     * @return static
     */
    public function peerDoc()
    {
        return $this;
    }

    /**
     * This object, documented `$this`.
     *
     * @return $this
     */
    public function peerThis()
    {
        return $this;
    }

    /** Whether to take the first arm. */
    public function flag(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        /** @var self $me */
        $me = $this;

        return [
            'chainStatic' => $me->peer()->label(),
            'chainSelf' => $me->peerSelf()->label(),
            'chainDoc' => $me->peerDoc()->label(),
            'chainThis' => $me->peerThis()->label(),
            'chainTag' => $me->peer()->tag,
            'mapChain' => collect([$this])->map(fn (self $s) => $s->peer()->label())->all(),
            'thisChain' => $this->peer()->label(),
        ];
    }

    /** @return array<string, mixed> */
    public function relayed(): array
    {
        /** @var self $me */
        $me = $this;

        return [
            'passed' => $this->labelOf($me),
            'either' => ($this->flag() ? $me : $me)->label(),
            'typedChain' => $me->peer()->typedLabel(),
        ];
    }

    /** The label of whatever is passed, from a parameter no declaration types. */
    private function labelOf($held)
    {
        return $held->label();
    }
}
