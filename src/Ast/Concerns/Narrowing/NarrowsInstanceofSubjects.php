<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Narrowing;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\VariableWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;

/**
 * The one narrowing an `instanceof` ternary's proven arm resolves under, for TernaryHandler's value and
 * ReceiverClassResolver's receiver alike, and an analyzer's value inside an `if` block that proves `$this->resource`.
 * Hosts must also use InspectsAstNodes and ReadsInstanceofChains.
 *
 * @phpstan-import-type VarGuardBinding from AnalysisScope
 *
 * @internal
 */
trait NarrowsInstanceofSubjects
{
    /**
     * Resolve a value inside the span of an `if` proof on `$this->resource` as a ternary's proven arm resolves; as it
     * is where no proof holds, or one is already in force for an enclosing value.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $resolve
     * @return TResult
     */
    protected function resolveUnderProxyProof(Expr $value, AnalysisScope $scope, Closure $resolve): mixed
    {
        $proof = $scope->provenProxy === null && $scope->hasResourceProxy()
            ? $scope->proofAt(AnalysisScope::PROXY_GUARDS, $value->getStartFilePos())
            : null;

        if ($proof === null || $proof === $scope->appliedProxyProof) {
            return $resolve();
        }

        $narrowed = $this->resolveNarrowed(
            new PropertyFetch(new Variable('this'), 'resource'),
            $proof['classes'],
            $value,
            $scope,
            fn (): mixed => $this->resolveUnderAppliedProof($proof, $scope, $resolve),
        );

        return $narrowed ?? $resolve();
    }

    /**
     * Resolve an arm with the subject bound to what the test leaves of its own classes (a variable's varGuardBindings,
     * what the `$this->resource` proxy holds, or a `$this->prop` path's proof under its key), then restore the scope.
     * Null for any other subject, or for an arm that writes it.
     *
     * @template TResult
     *
     * @param  non-empty-list<class-string>  $tested
     * @param  Closure(): TResult  $resolve
     * @return TResult|null
     */
    protected function resolveNarrowed(Expr $subject, array $tested, Expr $arm, AnalysisScope $scope, Closure $resolve): mixed
    {
        $narrows = $this->instanceofSubject($subject, $scope);

        if ($narrows === null || in_array($narrows['variable'], VariableWrites::writtenNames([$arm], $scope), true)) {
            return null;
        }

        $receivers = resolve(ReceiverClassResolver::class);
        $classes = $receivers->narrowedSubject($subject, $tested, $scope)->classes;
        $key = $narrows['key'];

        if ($narrows['kind'] === 'variable') {
            // The proof holds only past the test, so a value assigned earlier and read in the arm reads the variable as
            // it was then.
            return $scope->within(function () use ($scope, $key, $classes, $subject): void {
                $scope->varGuardBindings[$key][] = ['classes' => $classes, 'after' => $subject->getEndFilePos(), 'before' => null];
            }, $resolve);
        }

        if ($narrows['kind'] === 'path') {
            // Every read of the chain narrows by the tested classes, wherever it sits, while the arm resolves.
            return $scope->within(function () use ($scope, $key, $tested): void {
                $scope->varGuardBindings[$key][] = AnalysisScope::armProof($tested);
            }, $resolve);
        }

        // A test that rules out no class `$resource` is redeclared as proves nothing, so the arm reads it unproven.
        if ($receivers->excludesNoNamedClass($classes, $scope)) {
            return $resolve();
        }

        // within() puts the backing back as it was, never re-derived: an instanceof guard in toArray() may have backed
        // the proxy the model did not.
        return $scope->within(function () use ($scope, $classes): void {
            $scope->provenProxy = $classes;

            // One proven model also answers the subject's model reads, such as an undeclared `$this->prop`. Not
            // rebase(): the collection facts stay the runtime class's, which an ancestor's body runs as.
            if (count($classes) === 1 && is_a($classes[0], Model::class, true)) {
                $scope->setBackingModel($classes[0]);
            }
        }, $resolve);
    }

    /**
     * Resolve under a proof on `$this->resource` that proved no class, marked as applied for the values nested in this
     * one, since provenProxy stays null and each would look the proof up again; any other proof is in force already.
     *
     * @template TResult
     *
     * @param  VarGuardBinding  $proof
     * @param  Closure(): TResult  $resolve
     * @return TResult
     */
    private function resolveUnderAppliedProof(array $proof, AnalysisScope $scope, Closure $resolve): mixed
    {
        if ($scope->provenProxy !== null) {
            return $resolve();
        }

        return $scope->within(function () use ($scope, $proof): void {
            $scope->appliedProxyProof = $proof;
        }, $resolve);
    }
}
