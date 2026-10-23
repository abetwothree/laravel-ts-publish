<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * The PHP class(es) an expression holds, as ReceiverClassResolver answers it: a PHP class, never a TypeScript type.
 *
 * @phpstan-import-type CollectedElement from ResourceCollections
 *
 * @internal
 */
final readonly class ReceiverType
{
    /**
     * @param  non-empty-list<class-string>  $classes  every class the value may be an instance of
     * @param  bool  $shortCircuits  a `?->` earlier in the chain can end the whole expression as null
     * @param  class-string<Model>|null  $elementModel  set when the receiver is a collection of that model
     * @param  class-string<Model>|null  $relatedModel  the model a relation, query or factory builds
     * @param  bool  $unsaved  the model may be one no row backs yet: `new`, a `replicate()` copy, or a `make()` result
     * @param  bool  $collectedItems  a resource collection's `$this->resource`: its items in a Support\Collection,
     *                                or the paginator it was given, so a method an arm cannot run throws, not answers
     * @param  CollectedElement|null  $collectedElement  set with $collectedItems: what each item publishes as
     * @param  bool  $keyed  a collection whose keys a step such as `keyBy()`, `sortBy()` or `filter()` moved off 0..n-1
     * @param  bool  $attributeless  a `new` model with no attributes yet, whose columns read null but for a default
     * @param  list<string>  $filled  the columns a write gave such a model, which read as written
     */
    public function __construct(
        public array $classes,
        public bool $shortCircuits = false,
        public ?string $elementModel = null,
        public ?string $relatedModel = null,
        public bool $unsaved = false,
        public bool $collectedItems = false,
        public ?array $collectedElement = null,
        public bool $keyed = false,
        public bool $attributeless = false,
        public array $filled = [],
    ) {}

    /**
     * A receiver holding exactly one class.
     *
     * @param  class-string  $class
     */
    public static function of(string $class, bool $shortCircuits = false): self
    {
        return new self([$class], $shortCircuits);
    }

    /**
     * Whether a class is a model a row can be read into: a subclass of Model that is not abstract.
     *
     * @phpstan-assert-if-true class-string<Model> $class
     */
    public static function isConcreteModel(string $class): bool
    {
        return is_subclass_of($class, Model::class) && ! new ReflectionClass($class)->isAbstract();
    }

    /**
     * The same receiver with its short-circuit flag replaced.
     */
    public function withShortCircuit(bool $shortCircuits): self
    {
        return $this->copy(shortCircuits: $shortCircuits);
    }

    /**
     * The same receiver marked as a model no row backs yet.
     */
    public function asUnsaved(): self
    {
        return $this->copy(unsaved: true);
    }

    /**
     * The same receiver marked as a new model that holds no attributes yet, but the columns a write filled, which no
     * row backs either.
     *
     * @param  list<string>  $filled
     */
    public function asAttributeless(array $filled = []): self
    {
        return $this->copy(unsaved: true, attributeless: true, filled: $filled);
    }

    /**
     * The same value holding other classes, as an `instanceof` test narrows it.
     *
     * @param  non-empty-list<class-string>  $classes
     */
    public function withClasses(array $classes): self
    {
        return $this->copy(classes: $classes);
    }

    /**
     * Whether every class the receiver holds is one of the given classes, or a subtype of one.
     *
     * @param  non-empty-list<class-string>  $classes
     */
    public function within(array $classes): bool
    {
        return array_all(
            $this->classes,
            fn (string $class): bool => array_any($classes, fn (string $type): bool => is_a($class, $type, true)),
        );
    }

    /**
     * The receiver's classes that are Eloquent models.
     *
     * @return list<class-string<Model>>
     */
    public function models(): array
    {
        /** @var list<class-string<Model>> $models */
        $models = array_values(array_filter($this->classes, fn (string $c): bool => is_a($c, Model::class, true)));

        return $models;
    }

    /**
     * A copy with the given parts replaced and every other flag kept.
     *
     * @param  non-empty-list<class-string>|null  $classes
     * @param  list<string>|null  $filled
     */
    private function copy(
        ?array $classes = null,
        ?bool $shortCircuits = null,
        ?bool $unsaved = null,
        ?bool $attributeless = null,
        ?array $filled = null,
    ): self {
        return new self(
            classes: $classes ?? $this->classes,
            shortCircuits: $shortCircuits ?? $this->shortCircuits,
            elementModel: $this->elementModel,
            relatedModel: $this->relatedModel,
            unsaved: $unsaved ?? $this->unsaved,
            collectedItems: $this->collectedItems,
            collectedElement: $this->collectedElement,
            keyed: $this->keyed,
            attributeless: $attributeless ?? $this->attributeless,
            filled: $filled ?? $this->filled,
        );
    }
}
