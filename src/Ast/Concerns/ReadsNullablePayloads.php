<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * The type of a resource built around a payload that can serialize as null. Only JsonResource::filter() turns a
 * nested resource whose payload is null into null, so only a JsonResource subject's own output carries that null.
 *
 * @internal
 */
trait ReadsNullablePayloads
{
    use ReadsNonNullGuards;

    /**
     * A resource construction call's arguments mapped against the signature its payload lands in.
     */
    abstract protected function resourcePayloadArguments(StaticCall|New_ $call, string $className): CallArguments;

    /**
     * Whether a class exists and is a JsonResource subclass.
     */
    abstract protected function isResourceClass(string $fqcn): bool;

    /**
     * The type `new X(…)` or `X::make(…)` publishes: `null` for a payload that is only ever null in a JsonResource
     * subject, the resource or null for one that can be null, else the resource.
     */
    protected function resourceWrapType(StaticCall|New_ $call, string $className, string $resourceName, AnalysisScope $scope, ExpressionEngine $engine): string
    {
        return match ($this->nullablePayloadType($call, $className, $scope, $engine)) {
            null => $resourceName,
            'null' => 'null',
            default => ValueResult::withNullArm($resourceName),
        };
    }

    /**
     * The resolved payload type of `new X(…)` or `X::make(…)` in a JsonResource subject when the payload can be null,
     * else null. A ResourceCollection never wraps null: it throws. Neither does a payload that is `new` or a resource's
     * `make()`, since PHP builds an object and only that inner resource serializes as null, nor a read a guard proves.
     */
    private function nullablePayloadType(StaticCall|New_ $call, string $className, AnalysisScope $scope, ExpressionEngine $engine): ?string
    {
        if (! $scope->subjectReflection->isSubclassOf(JsonResource::class) || is_a($className, ResourceCollection::class, true)) {
            return null;
        }

        $payload = $this->resourcePayloadArguments($call, $className)->at(0)?->value;

        if ($payload === null) {
            return null;
        }

        // Resolved even for `new`: resolving a payload also records the warnings of the expressions it nests.
        $resolved = $engine->resolve($payload);

        if ($payload instanceof New_
            || ($payload instanceof StaticCall
                && $payload->name instanceof Identifier
                && $payload->name->toString() === 'make'
                && (isset($resolved['resourceFqcn'])
                    || ($resolved['type'] === 'null' && $this->callsResourceClass($payload, $scope))))
        ) {
            return null;
        }

        if (! ValueResult::hasNullArm($resolved['type']) || $this->isProvenNonNull($payload, $scope)) {
            return null;
        }

        return $resolved['type'];
    }

    /**
     * Whether a static call's class is a resource, `self` and `static` reading as the subject.
     */
    private function callsResourceClass(StaticCall $call, AnalysisScope $scope): bool
    {
        if (! $call->class instanceof Name) {
            return false;
        }

        $className = $call->class->toString();

        if ($className === 'self' || $className === 'static') {
            $className = $scope->subjectReflection->getName();
        }

        return $this->isResourceClass($className);
    }
}
