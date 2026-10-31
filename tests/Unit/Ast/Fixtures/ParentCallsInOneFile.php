<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Declares the payload() each class below reads, or not, through `parent::payload()`. */
class ParentCallBase
{
    /**
     * The base payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ['a' => 1];
    }
}

/** Spreads its parent's payload into its own. */
class SpreadsParentPayload extends ParentCallBase
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [...parent::payload(), 'b' => 2];
    }
}

/** Merges its parent's payload with array_merge(). */
class MergesParentPayload extends ParentCallBase
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_merge(parent::payload(), ['b' => 2]);
    }
}

/** Returns its parent's payload whole on one branch. */
class ReturnsParentPayload extends ParentCallBase
{
    public bool $flag = true;

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->flag ? parent::payload() : [];
    }
}

/** Writes its parent's payload to a local, adds to it, and returns the local. */
class HoldsParentPayload extends ParentCallBase
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = ['b' => 2];
        $data = [...$data, 'c' => 3];
        $data += parent::payload();

        return $data;
    }
}

/** Nests its parent's payload under keys, whole, spread and in a closure, so none of its keys is its own. */
class NestsParentPayload extends ParentCallBase
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $kept = parent::payload();

        return ['whole' => parent::payload(), 'spread' => [...parent::payload()], 'lazy' => fn () => parent::payload(), 'kept' => $kept];
    }
}

/** Inherits SpreadsParentPayload's payload() whole. */
class InheritsSpreadPayload extends SpreadsParentPayload {}

/** Spreads the payload of a parent that itself spreads its own parent's. */
class SpreadsSpreadPayload extends InheritsSpreadPayload
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [...parent::payload(), 'd' => 4];
    }
}

/** Spreads the payload of a parent that never reads its own parent's. */
class SpreadsNestingPayload extends NestsParentPayload
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [...parent::payload(), 'e' => 5];
    }
}
