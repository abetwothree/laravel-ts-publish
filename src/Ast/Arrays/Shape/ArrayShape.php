<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;

/**
 * One array as the engine reads it, after PHPStan's constant array: its named keys and index signatures in
 * first-write order, the typed string keys it may hold beside them (`extras`), the parts that bring keys no name names,
 * a proof that it is never empty where its keys cannot give one, and what it holds beside its keys.
 *
 * A shape is sealed when it has no extras, no opaque part and no signature, and its wide data holds no unread or
 * unnamed key: a lifted analysis keeps those two flags where a builder would record an opaque part.
 *
 * @internal
 */
final readonly class ArrayShape
{
    /**
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
     * Whether the array holds a key: yes where an entry sends it on every path, no only where a sealed shape lacks it.
     */
    public function certainty(string $key): Certainty
    {
        foreach ($this->entries as $entry) {
            if ($entry instanceof ShapeKey && $entry->name === $key) {
                return $entry->presence->isSure() ? Certainty::Yes : Certainty::Maybe;
            }
        }

        return $this->isSealed() ? Certainty::No : Certainty::Maybe;
    }

    /**
     * Whether every key the array may hold is an entry it names.
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
