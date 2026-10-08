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

/**
 * Whether a resource built around a payload serializes as null. Only JsonResource::filter() turns a nested resource
 * whose payload is null into null, so only a JsonResource subject's own output carries that null.
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
     * Whether `new X(…)` or `X::make(…)` in a JsonResource subject wraps a payload that can be null. A
     * ResourceCollection never does: it throws on a null payload. Neither does a payload that is `new` or a resource's
     * `make()`, since PHP builds an object and only that inner resource serializes as null, nor a read a guard proves.
     */
    protected function wrapsNullablePayload(StaticCall|New_ $call, string $className, AnalysisScope $scope, ExpressionEngine $engine): bool
    {
        if (! $scope->subjectReflection->isSubclassOf(JsonResource::class) || is_a($className, ResourceCollection::class, true)) {
            return false;
        }

        $payload = $this->resourcePayloadArguments($call, $className)->at(0)?->value;

        if ($payload === null) {
            return false;
        }

        // Resolved even for `new`: resolving a payload also records the warnings of the expressions it nests.
        $resolved = $engine->resolve($payload);

        if ($payload instanceof New_
            || ($payload instanceof StaticCall
                && $payload->name instanceof Identifier
                && $payload->name->toString() === 'make'
                && isset($resolved['resourceFqcn']))
        ) {
            return false;
        }

        return ValueResult::hasNullArm($resolved['type']) && ! $this->isProvenNonNull($payload, $scope);
    }
}
