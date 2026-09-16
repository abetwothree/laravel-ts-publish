<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use ReflectionClass;
use ReflectionMethod;

/**
 * Resource semantics over a parsed call: the `$this->when*()` family, resource construction payloads,
 * and which classes count as resources.
 *
 * Every member here needs to know what a JsonResource is, which is why it stays on the analyzer side
 * rather than moving with the node-shape half. Requires the host to also use
 * `Ast\Concerns\InspectsAstNodes` (for `isThisMethodCall()`).
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

    protected function isEnumResourceClass(string $fqcn): bool
    {
        return $fqcn === EnumResource::class
            || $fqcn === 'EnumResource'
            || is_a($fqcn, EnumResource::class, true);
    }

    protected function isResourceClass(string $fqcn): bool
    {
        return class_exists($fqcn) && is_a($fqcn, JsonResource::class, true);
    }

    /**
     * Whether a class is a resource this run will also emit a file for.
     *
     * A convention-guessed candidate must be one, or the import it produces points at no module.
     *
     * @phpstan-assert-if-true class-string<JsonResource> $fqcn
     */
    protected function isPublishedResourceClass(string $fqcn): bool
    {
        return $this->isResourceClass($fqcn) && PublishedResourceRegistry::isPublished($fqcn);
    }

    /**
     * Resolve the resource class a ResourceCollection collects, from the #[Collects] attribute, the
     * $collects property default, or the FooCollection → FooResource naming convention.
     *
     * Shared by both analyzers so their resolution order cannot drift apart.
     *
     * @param  class-string  $collectionFqcn
     * @return class-string<JsonResource>|null
     */
    protected function resolveCollectedResourceClass(string $collectionFqcn): ?string
    {
        $reflection = new ReflectionClass($collectionFqcn);

        $collectsAttribute = 'Illuminate\Http\Resources\Attributes\Collects';
        if (class_exists($collectsAttribute)) {
            // Priority 1: #[Collects] attribute (Laravel 13.0+)
            $collectsAttrs = $reflection->getAttributes($collectsAttribute);

            if ($collectsAttrs !== []) {
                $collectsClass = $collectsAttrs[0]->newInstance()->class;

                if (class_exists($collectsClass) && is_a($collectsClass, JsonResource::class, true)) {
                    return $collectsClass;
                }
            }
        }

        // Priority 2: explicit $collects property default value
        /** @var array<string, mixed> $defaults */
        $defaults = $reflection->getDefaultProperties();
        $collects = $defaults['collects'] ?? null;

        if (is_string($collects) && class_exists($collects) && is_a($collects, JsonResource::class, true)) {
            return $collects;
        }

        // Priority 3: naming convention — FooCollection → FooResource, gated on the published set
        $className = $reflection->getShortName();
        $namespace = $reflection->getNamespaceName();

        if (str_ends_with($className, 'Collection')) {
            $base = substr($className, 0, -10);

            $candidate = $namespace.'\\'.$base.'Resource';

            if ($this->isPublishedResourceClass($candidate)) {
                return $candidate;
            }

            $candidate = $namespace.'\\'.$base;

            if ($this->isPublishedResourceClass($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
