<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

/**
 * The one narrowing an `instanceof` ternary's proven arm resolves under, for TernaryHandler's value and
 * ReceiverClassResolver's receiver alike. Hosts must also use InspectsAstNodes and CollectsLocalVarBindings.
 *
 * @internal
 */
trait NarrowsInstanceofSubjects
{
    /**
     * Resolve an arm with the subject bound to what the test leaves of its own classes (a variable's varGuardBindings,
     * what the `$this->resource` proxy holds, or a `$this->prop` path's narrowedReads), then restore the scope. Null
     * for any other subject, or for an arm that writes it.
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
        $isProxy = $variable === null && $this->isProxyFetch($subject, $scope);
        $path = $variable === null && ! $isProxy ? $this->thisReadPath($subject) : null;

        // `$this->resource` and a `$this->prop` path are written through the `this` variable.
        if (($variable === null && ! $isProxy && $path === null)
            || in_array($variable ?? 'this', $this->collectWrittenVariableNames([$arm], $scope), true)
        ) {
            return null;
        }

        $receivers = resolve(ReceiverClassResolver::class);
        $classes = $receivers->narrowedSubject($subject, $tested, $scope);

        if ($subject instanceof Variable && $variable !== null) {
            $previousVarGuardBindings = $scope->varGuardBindings;

            try {
                // The proof holds only past the test, so a value assigned earlier and read in the arm reads the
                // variable as it was then.
                $scope->varGuardBindings[$variable][] = ['classes' => $classes, 'after' => $subject->getEndFilePos(), 'before' => null];

                return $resolve();
            } finally {
                $scope->varGuardBindings = $previousVarGuardBindings;
            }
        }

        if ($path !== null) {
            $previousNarrowedReads = $scope->narrowedReads;

            try {
                $scope->narrowedReads[] = [$path, $classes];

                return $resolve();
            } finally {
                $scope->narrowedReads = $previousNarrowedReads;
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

            // One proven model also answers the subject's model reads, such as an undeclared `$this->prop`. Not
            // rebase(): the collection facts stay the runtime class's, which an ancestor's body runs as.
            if (count($classes) === 1 && is_a($classes[0], Model::class, true)) {
                $scope->setBackingModel($classes[0]);
            }

            return $resolve();
        } finally {
            // Restored, never re-derived: an instanceof guard in toArray() may have backed the proxy the model did not.
            $scope->provenProxy = $previousProvenProxy;
            $scope->modelClass = $previousModelClass;
            $scope->forwardsUndeclaredMembersTo = $previousForwardsTo;
        }
    }

    /**
     * The property names a chain of named property reads on `$this` spells, outermost first, such as `owner`, `team`
     * for `$this->owner?->team`; null for any other expression.
     *
     * @return list<string>|null
     */
    private function thisReadPath(Expr $expr): ?array
    {
        $path = [];

        while (($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch) && $expr->name instanceof Identifier) {
            array_unshift($path, $expr->name->toString());
            $expr = $expr->var;
        }

        return $path !== [] && $expr instanceof Variable && $expr->name === 'this' ? $path : null;
    }
}
