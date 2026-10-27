<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use ReflectionClass;
use ReflectionMethod;

/**
 * Resource semantics over a parsed call: the `$this->when*()` family, resource construction payloads, and which
 * classes count as resources. Every member needs to know what a JsonResource is, so it stays on the analyzer side.
 * Requires the host to also use `Ast\Concerns\InspectsAstNodes` (for `isThisMethodCall()`).
 */
trait InspectsResourceCalls
{
    /** @var list<string> */
    protected array $conditionalMethods = [
        'when', 'whenHas', 'whenNotNull', 'whenLoaded',
        'whenCounted', 'whenAggregated', 'whenPivotLoaded', 'whenPivotLoadedAs',
        'unless', 'whenAppended', 'whenExistsLoaded', 'transform', 'mergeUnless',
    ];

    /**
     * Check if a static call's payload argument is a conditional expression such as `$this->whenLoaded(...)`.
     *
     * @param  string  $className  the resolved receiver class, whose constructor names the payload parameter
     */
    protected function hasConditionalArgument(StaticCall $call, string $className): bool
    {
        $inner = $this->resourcePayloadArguments($call, $className)->at(0)?->value;

        return $inner !== null && $this->isConditionalMethodCall($inner);
    }

    /**
     * Check if a `new Resource(...)` call's payload argument is a conditional expression.
     */
    protected function hasConditionalNewArgument(New_ $expr, string $className): bool
    {
        $inner = $this->resourcePayloadArguments($expr, $className)->at(0)?->value;

        return $inner !== null && $this->isConditionalMethodCall($inner);
    }

    /**
     * A resource construction call's arguments mapped against the signature its payload lands in: `new X(...)`
     * and `X::make(...)` both bind through X's constructor (make() spreads into `new static`), `X::collection(...)`
     * through JsonResource::collection($resource).
     */
    protected function resourcePayloadArguments(StaticCall|New_ $call, string $className): CallArguments
    {
        if ($call instanceof StaticCall && $call->name instanceof Identifier && $call->name->toString() === 'collection') {
            return CallArguments::for($call, new ReflectionMethod(JsonResource::class, 'collection'));
        }

        $constructor = class_exists($className) ? new ReflectionClass($className)->getConstructor() : null;

        return CallArguments::for($call, $constructor ?? new ReflectionMethod(JsonResource::class, '__construct'));
    }

    /**
     * Whether an expression is one of the `$this->when*()` family, whose result can be a MissingValue.
     */
    protected function isConditionalMethodCall(Expr $expr): bool
    {
        foreach ($this->conditionalMethods as $method) {
            if ($this->isThisMethodCall($expr, $method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a class name refers to the package's own EnumResource wrapper.
     */
    protected function isEnumResourceClass(string $fqcn): bool
    {
        return $fqcn === EnumResource::class
            || $fqcn === 'EnumResource'
            || is_a($fqcn, EnumResource::class, true);
    }

    /**
     * Whether a class exists and is a JsonResource subclass.
     */
    protected function isResourceClass(string $fqcn): bool
    {
        return class_exists($fqcn) && is_a($fqcn, JsonResource::class, true);
    }

    /**
     * Whether Laravel declares a class, as JsonResource and ResourceCollection: a body it declares serves the runtime
     * class, and a resource it declares publishes no type, so neither is read as the class it names.
     */
    protected function isFrameworkClass(string $fqcn): bool
    {
        return str_starts_with(ltrim($fqcn, '\\'), 'Illuminate\\');
    }

    /**
     * Whether a class is an API resource this run will also emit a file for, as ResourceCollections decides it.
     *
     * @phpstan-assert-if-true class-string<JsonResource> $fqcn
     */
    protected function isPublishedResourceClass(string $fqcn): bool
    {
        return ResourceCollections::isPublishedResource($fqcn);
    }
}
