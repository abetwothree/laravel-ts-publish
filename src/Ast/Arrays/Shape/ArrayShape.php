<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;

/**
 * One array as the engine reads it, after PHPStan's constant array: named keys and index signatures in first-write
 * order, the typed string keys beside them (`extras`), the parts that bring keys no name names, a proof that it is
 * never empty where its keys give none, and what it holds beside its keys.
 *
 * @internal
 */
final readonly class ArrayShape
{
    /**
     * Hold an array's entries, extras, opaque parts, never-empty proof and wide data.
     *
     * @param  list<ShapeKey|ShapeSignature>  $entries  in first-write order
     * @param  list<OpaquePart>  $opaque
     * @param  bool  $nonEmptyProof  whether a guard or a join proves a key is held where no entry is sure
     */
    public function __construct(
        public array $entries = [],
        public ?KeyValue $extras = null,
        public array $opaque = [],
        public bool $nonEmptyProof = false,
        public ShapeWide $wide = new ShapeWide,
    ) {}

    /**
     * Whether the array holds a key: yes where the last entry of its name, the one MethodAnalysis::sliceOf() reads,
     * sends it on every path; no only where a sealed shape lacks it.
     */
    public function certainty(string $key): Certainty
    {
        for ($index = count($this->entries) - 1; $index >= 0; $index--) {
            $entry = $this->entries[$index];

            if ($entry instanceof ShapeKey && $entry->name === $key) {
                return $entry->presence->isSure() ? Certainty::Yes : Certainty::Maybe;
            }
        }

        return $this->isSealed() ? Certainty::No : Certainty::Maybe;
    }

    /**
     * Whether every key the array may hold is an entry it names: no extras, opaque part or signature, and no unread or
     * unnamed key, the two flags a lifted analysis keeps where a builder would record an opaque part.
     */
    public function isSealed(): bool
    {
        $signature = static fn (ShapeKey|ShapeSignature $entry): bool => $entry instanceof ShapeSignature;

        return $this->extras === null && $this->opaque === []
            && ! $this->wide->holdsUnreadKeys && ! $this->wide->holdsUnnamedKeys
            && ! array_any($this->entries, $signature);
    }

    /**
     * Whether the array holds a key: yes where an entry is sure or a proof holds, no where it can hold none.
     */
    public function nonEmpty(): Certainty
    {
        $sureKey = static fn (ShapeKey|ShapeSignature $entry): bool => $entry instanceof ShapeKey
            && $entry->presence->isSure();

        if ($this->nonEmptyProof || array_any($this->entries, $sureKey)) {
            return Certainty::Yes;
        }

        return $this->entries === [] && $this->isSealed() ? Certainty::No : Certainty::Maybe;
    }

    /**
     * Whether PHP sends the array as a JSON list: no where a key PHP stores as a string is sent on every path.
     */
    public function isList(): Certainty
    {
        $stringKey = static fn (ShapeKey|ShapeSignature $entry): bool => $entry instanceof ShapeKey
            && $entry->presence->isSure() && PhpArrayKey::storedInt($entry->name) === null;

        return array_any($this->entries, $stringKey) ? Certainty::No : Certainty::Maybe;
    }
}
