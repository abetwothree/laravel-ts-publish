<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * The PHP class(es) an expression holds, as ReceiverClassResolver answers it: a PHP class, never a TypeScript type.
 *
 * @phpstan-import-type CollectedElement from ResourceCollections
 * @phpstan-import-type Intersections from DeclaredClasses
 * @phpstan-import-type DeclaredType from DeclaredClasses
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
     * @param  bool  $runtimeInstance  an instance of the scope's runtime class, as a `$this->m()` declared `static`
     *                                 returns, so a body read on it knows that class where the scope does
     * @param  bool  $declaredOnly  spelled `self`, `static` or `parent`, so the object may be any subclass that
     *                              overrides a member: one is read by its declaration alone where a subclass can
     * @param  Intersections  $intersections  the other members of an intersection each class is, as `A&B` holds an A
     *                                        that is also a B: memberOwner() reads a member A lacks off B
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
        public bool $runtimeInstance = false,
        public bool $declaredOnly = false,
        public array $intersections = [],
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
     * A receiver holding what a declaration names, its intersections included; null for a declaration naming none.
     *
     * @param  DeclaredType|null  $declared
     */
    public static function declared(?array $declared): ?self
    {
        return $declared === null ? null : new self($declared['classes'], intersections: $declared['intersections']);
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
     * The intersections a union of receivers keeps: of each class, the other members every receiver holding it names,
     * so `A&B` beside a bare `A` holds an A and no more.
     *
     * @param  list<self>  $types
     * @return Intersections
     */
    public static function agreedIntersections(array $types): array
    {
        $shared = [];

        foreach ($types as $type) {
            foreach ($type->classes as $class) {
                $others = $type->intersections[$class] ?? [];
                $shared[$class] = isset($shared[$class]) ? array_values(array_intersect($shared[$class], $others)) : $others;
            }
        }

        $agreed = [];

        foreach ($shared as $class => $others) {
            if ($others !== []) {
                $agreed[$class] = $others;
            }
        }

        return $agreed;
    }

    /**
     * Whether a subclass can override a class's member, which a receiver $declaredOnly marks then reads by its
     * declaration alone: neither the class nor the member is final, and the member is not private.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function overridable(ReflectionClass $class, ReflectionMethod|ReflectionProperty $member): bool
    {
        return ! $class->isFinal() && ! $member->isFinal() && ! $member->isPrivate();
    }

    /**
     * The class whose public member a read on one of the receiver's classes runs: the class where it declares it, else
     * the first other member of its intersection that does, else the class, whose `__get()` or `__call()` runs.
     *
     * @param  class-string  $class
     * @return class-string
     */
    public function memberOwner(string $class, string $member, bool $isMethod): string
    {
        $others = $this->intersections[$class] ?? null;

        if ($others === null) {
            return $class;
        }

        // The answer follows each member's declarations, which reflection reads without parsing.
        foreach ($others as $other) {
            DependencyRecorder::recordClass($other);
        }

        return array_find(
            [$class, ...$others],
            fn (string $candidate): bool => self::declaresPublic($candidate, $member, $isMethod),
        ) ?? $class;
    }

    /**
     * The same receiver with each class a declared intersection admits also holding the members it is not: a subtype of
     * the primary member, or of any member where every one is an interface, which a subclass can add.
     *
     * @param  Intersections  $declared
     */
    public function withIntersections(array $declared): self
    {
        if ($declared === []) {
            return $this;
        }

        $intersections = $this->intersections;

        foreach ($this->classes as $class) {
            $others = $intersections[$class] ?? [];

            foreach ($declared as $primary => $members) {
                $arm = [$primary, ...$members];
                $admits = interface_exists($primary)
                    ? array_any($arm, fn (string $member): bool => is_a($class, $member, true))
                    : is_a($class, $primary, true);
                $others = $admits ? [...$others, ...$arm] : $others;
            }

            $others = array_values(array_unique(array_filter($others, fn (string $other): bool => ! is_a($class, $other, true))));

            if ($others !== []) {
                $intersections[$class] = $others;
            }
        }

        return $intersections === $this->intersections ? $this : $this->copy(intersections: $intersections);
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
     * The same receiver marked as an instance of the scope's runtime class.
     */
    public function asRuntimeInstance(): self
    {
        return $this->copy(runtimeInstance: true);
    }

    /**
     * The same receiver marked as spelled `self`, `static` or `parent`, which reads members by their declarations.
     */
    public function asDeclaredOnly(): self
    {
        return $this->copy(declaredOnly: true);
    }

    /**
     * The same value holding other classes, as an `instanceof` test narrows it: the proof names them, not a spelling.
     *
     * @param  non-empty-list<class-string>  $classes
     */
    public function withClasses(array $classes): self
    {
        return $this->copy(
            classes: $classes,
            runtimeInstance: false,
            declaredOnly: false,
            intersections: array_intersect_key($this->intersections, array_flip($classes)),
        );
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
     * Whether a class declares a public instance method or property by the name.
     *
     * @param  class-string  $class
     */
    private static function declaresPublic(string $class, string $member, bool $isMethod): bool
    {
        if ($isMethod) {
            return method_exists($class, $member) && new ReflectionMethod($class, $member)->isPublic();
        }

        if (! property_exists($class, $member)) {
            return false;
        }

        $property = new ReflectionProperty($class, $member);

        return $property->isPublic() && ! $property->isStatic();
    }

    /**
     * A copy with the given parts replaced and every other flag kept.
     *
     * @param  non-empty-list<class-string>|null  $classes
     * @param  list<string>|null  $filled
     * @param  Intersections|null  $intersections
     */
    private function copy(
        ?array $classes = null,
        ?bool $shortCircuits = null,
        ?bool $unsaved = null,
        ?bool $attributeless = null,
        ?array $filled = null,
        ?bool $runtimeInstance = null,
        ?bool $declaredOnly = null,
        ?array $intersections = null,
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
            runtimeInstance: $runtimeInstance ?? $this->runtimeInstance,
            declaredOnly: $declaredOnly ?? $this->declaredOnly,
            intersections: $intersections ?? $this->intersections,
        );
    }
}
