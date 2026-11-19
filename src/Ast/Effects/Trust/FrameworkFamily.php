<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust;

use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use Illuminate\Http\Resources\CollectsResources;
use Illuminate\Http\Resources\ConditionallyLoadsAttributes;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use ReflectionMethod;

/**
 * A body of framework code the write checks trust to only read, as FrameworkDispatch walks it: the classes declaring
 * it, whether it may call a method the source does not name, and how a method of the app it reaches is judged.
 *
 * @internal
 */
enum FrameworkFamily
{
    /** Laravel's resource helpers, which call at most the callbacks they are given. */
    case ResourceHelpers;

    /** Eloquent's reading methods, whose computed calls run accessors and relations (see docs/known-gaps.md). */
    case Eloquent;

    /** The framework's resource classes and traits, whose helpers only read the resource. */
    public const array RESOURCE_DECLARERS = [
        JsonResource::class, ResourceCollection::class, ConditionallyLoadsAttributes::class, CollectsResources::class,
    ];

    /** The namespace Eloquent's model, its concerns and the SoftDeletes trait are declared in. */
    public const string ELOQUENT_NAMESPACE = 'Illuminate\\Database\\Eloquent\\';

    /**
     * Whether the family's classes declare a method's body.
     */
    public function declares(ReflectionMethod $method): bool
    {
        $declarer = DeclaringFile::ofMethod($method)->getName();

        return match ($this) {
            self::ResourceHelpers => in_array($declarer, self::RESOURCE_DECLARERS, true),
            self::Eloquent => str_starts_with($declarer, self::ELOQUENT_NAMESPACE),
        };
    }

    /**
     * Whether the family's code is trusted where it calls a method the source does not name.
     */
    public function trustsComputedCalls(): bool
    {
        return $this === self::Eloquent;
    }

    /**
     * Whether a method the family's code runs on `$this`, which the app declares, may write, judged with nothing more
     * than its own body: a model's only where it does more than return a literal, a resource's always.
     *
     * @param  ReflectionClass<object>  $class  the class the method runs on
     */
    public function userMethodMayWrite(ReflectionClass $class, string $method): bool
    {
        return match ($this) {
            self::ResourceHelpers => true,
            self::Eloquent => ! LiteralGetter::returnsLiteral($class->getMethod($method)),
        };
    }
}
