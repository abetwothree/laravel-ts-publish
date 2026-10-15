<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
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
     * its varGuardBindings past the write that ended its parameter binding, or what the `$this->resource` proxy holds),
     * then restore the scope. Null for any other subject, or for an arm that writes it.
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
        if (($variable === null && ! $this->isProxyFetch($subject, $scope))
            || in_array($variable ?? 'this', $this->collectWrittenVariableNames([$arm], $scope), true)
        ) {
            return null;
        }

        $receivers = resolve(ReceiverClassResolver::class);
        $classes = $receivers->narrowedSubject($subject, $tested, $scope);

        if ($subject instanceof Variable && $variable !== null) {
            $previousVarClassBindings = $scope->varClassBindings;
            $previousVarGuardBindings = $scope->varGuardBindings;

            try {
                // Past the write that ended its parameter binding, the proof holds only past the test, so the value
                // that write assigned, read earlier, still reads the variable as the parameter it was.
                if ($scope->holdsBinding($subject)) {
                    $scope->varClassBindings[$variable] = $classes;
                } else {
                    $scope->varGuardBindings[$variable] = ['classes' => $classes, 'after' => $subject->getEndFilePos()];
                }

                return $resolve();
            } finally {
                $scope->varClassBindings = $previousVarClassBindings;
                $scope->varGuardBindings = $previousVarGuardBindings;
            }
        }

        // A test that rules out no class `$resource` is redeclared as proves nothing, so the arm reads it unproven.
        if ($receivers->excludesNoNamedClass($classes, $scope)) {
            return $resolve();
        }

        $previousProvenProxy = $scope->provenProxy;
        $previousModelClass = $scope->modelClass;
        $previousForwardsTo = $scope->forwardsUndeclaredMembersTo;

        try {
            $scope->provenProxy = $classes;

            // One proven model also answers the subject's model reads, such as an undeclared `$this->prop`.
            if (count($classes) === 1 && is_a($classes[0], Model::class, true)) {
                $scope->rebase($scope->subjectReflection, $classes[0]);
            }

            return $resolve();
        } finally {
            // Restored, never re-derived: an instanceof guard in toArray() may have backed the proxy the model did not.
            $scope->provenProxy = $previousProvenProxy;
            $scope->modelClass = $previousModelClass;
            $scope->forwardsUndeclaredMembersTo = $previousForwardsTo;
        }
    }
}
