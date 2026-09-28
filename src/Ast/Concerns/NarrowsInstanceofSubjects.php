<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;

/**
 * The one narrowing an `instanceof` ternary's proven arm resolves under, for TernaryHandler's value and
 * ReceiverClassResolver's receiver alike. Hosts must also use InspectsAstNodes and CollectsLocalVarBindings.
 *
 * @internal
 */
trait NarrowsInstanceofSubjects
{
    /**
     * Resolve an arm with the subject bound to what the test leaves of its own classes (a variable's varClassBindings,
     * or `$this->resource`'s model when that is one), then restore the scope. Null for any other subject, or for an
     * arm that writes it, since a read after the write no longer holds what the test saw.
     *
     * @template TResult
     *
     * @param  non-empty-list<class-string>  $tested
     * @param  Closure(): TResult  $resolve
     * @return TResult|null
     */
    protected function resolveNarrowed(Expr $subject, array $tested, Expr $arm, AnalysisScope $scope, Closure $resolve): mixed
    {
        $variable = $subject instanceof Variable && is_string($subject->name) ? $subject->name : null;

        // `$this->resource` is written through the `this` variable.
        if (($variable === null && ! $this->isResourceFetch($subject))
            || in_array($variable ?? 'this', array_column($this->collectVariableWrites([$arm]), 0), true)
        ) {
            return null;
        }

        $classes = resolve(ReceiverClassResolver::class)->narrowedSubject($subject, $tested, $scope);

        if ($variable !== null) {
            $previousVarClassBindings = $scope->varClassBindings;

            try {
                $scope->varClassBindings[$variable] = $classes;

                return $resolve();
            } finally {
                $scope->varClassBindings = $previousVarClassBindings;
            }
        }

        $model = $classes[0];

        if (count($classes) !== 1 || ! is_a($model, Model::class, true)) {
            return null;
        }

        $previousModelClass = $scope->modelClass;
        $previousForwardsTo = $scope->forwardsUndeclaredMembersTo;

        try {
            $scope->modelClass = $model;

            // Derived from the subject, never from the previous value: a ternary-only guard leaves
            // instanceOfWrappedClass unseeded, so a proxying subject may not have been forwarding before.
            if ($scope->subjectReflection->isSubclassOf(JsonResource::class)) {
                $scope->forwardsUndeclaredMembersTo = $model;
            }

            return $resolve();
        } finally {
            $scope->modelClass = $previousModelClass;
            $scope->forwardsUndeclaredMembersTo = $previousForwardsTo;
        }
    }
}
