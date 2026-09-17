<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use Illuminate\Database\Eloquent\Model;

/**
 * The PHP class(es) an expression holds, as ReceiverClassResolver answers it: a PHP class, never a TypeScript type.
 *
 * @internal
 */
final readonly class ReceiverType
{
    /**
     * @param  non-empty-list<class-string>  $classes  every class the value may be an instance of
     * @param  bool  $shortCircuits  a `?->` earlier in the chain can end the whole expression as null
     * @param  class-string<Model>|null  $elementModel  set when the receiver is an Eloquent collection of that model
     * @param  class-string<Model>|null  $relatedModel  set when the receiver is a relation instance, e.g. `$post->comments()`
     */
    public function __construct(
        public array $classes,
        public bool $shortCircuits = false,
        public ?string $elementModel = null,
        public ?string $relatedModel = null,
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
     * The same receiver with its short-circuit flag replaced.
     */
    public function withShortCircuit(bool $shortCircuits): self
    {
        return new self($this->classes, $shortCircuits, $this->elementModel, $this->relatedModel);
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
}
