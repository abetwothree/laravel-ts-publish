<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
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
     * Resolve an arm with the tested subject bound to the classes the test proves, then restore the scope: a variable
     * through varClassBindings, and `$this->resource` proved one model through the scope's model, which every
     * `$this->prop` read resolves against. Null when the subject is neither, or when the arm writes it, since a read
     * after that write no longer holds what the test saw.
     *
     * @template TResult
     *
     * @param  non-empty-list<class-string>  $classes
     * @param  Closure(): TResult  $resolve
     * @return TResult|null
     */
    protected function resolveNarrowed(Expr $subject, array $classes, Expr $arm, AnalysisScope $scope, Closure $resolve): mixed
    {
        $written = array_column($this->collectVariableWrites([$arm]), 0);

        if ($subject instanceof Variable && is_string($subject->name)) {
            if (in_array($subject->name, $written, true)) {
                return null;
            }

            $previousVarClassBindings = $scope->varClassBindings;

            try {
                $scope->varClassBindings[$subject->name] = $classes;

                return $resolve();
            } finally {
                $scope->varClassBindings = $previousVarClassBindings;
            }
        }

        $model = $classes[0];

        if (! $this->isResourceFetch($subject)
            || count($classes) !== 1
            || ! is_a($model, Model::class, true)
            || in_array('this', $written, true)
        ) {
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
