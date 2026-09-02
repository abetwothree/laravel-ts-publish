<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AuthUserResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AppliesKnownMethodRules;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesAuthHelperCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Http\Request;
use JsonSerializable;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * The dispatch floor: Laravel-convention method-name rules for method calls no earlier handler
 * claimed — e.g. `$request->user()->can(…)`, whose receiver is itself a MethodCall. Registered last.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 */
final class KnownMethodRuleHandler implements ExpressionHandler
{
    use AppliesKnownMethodRules;
    use InspectsAstNodes;
    use InspectsResourceSubject;
    use ResolvesAuthHelperCalls;
    use ResolvesModelRelationTypes;

    /** @var ReflectionClass<Request>|null Immutable subject, so one reflection serves every expression. */
    private static ?ReflectionClass $requestReflection = null;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // Only MethodCall reaches here — every NullsafeMethodCall already returned via MethodChainHandler.
        if ($expr instanceof MethodCall) {
            $request = $this->requestMethodRule($expr, $scope);

            if ($request !== null) {
                return $request;
            }

            $known = $this->knownMethodRule($expr, $scope);

            if ($known !== null) {
                return $known;
            }
        }

        return null;
    }

    /**
     * Type a method call on an `Illuminate\Http\Request` receiver from the method's own signature.
     *
     * Gated on the receiver being a variable the scope knows holds a Request: these names
     * (`string`, `boolean`, `user`, …) are far too common to type on the name alone.
     *
     * @return ValueExpressionResult|null
     */
    private function requestMethodRule(MethodCall $expr, AnalysisScope $scope): ?array
    {
        if (! $expr->name instanceof Identifier
            || ! $expr->var instanceof Variable
            || ! is_string($expr->var->name)
            || ! isset($scope->requestVarNames[$expr->var->name])) {
            return null;
        }

        $method = $expr->name->toString();

        // Reflection reports `@return mixed` here; the configured auth model is the useful answer.
        if ($method === 'user') {
            return $this->authMethodResult($method, resolve(AuthUserResolver::class)->model());
        }

        // The scope knows the variable is *a* Request, not which subclass, so the base class is the
        // honest floor. Declining on an unusable type is required: knownMethodRule() runs next.
        self::$requestReflection ??= new ReflectionClass(Request::class);

        if (! self::$requestReflection->hasMethod($method)) {
            return null;
        }

        $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(self::$requestReflection, $method);

        // A prop is JSON, and a bare `@return array` carries no key evidence: the `unknown[]` it
        // derives claims a list for the string-keyed `all()`. Vagueness also covers a `| unknown` arm.
        if (LaravelTsPublish::isVagueTsType($tsInfo['type'])
            || ! $this->serializesAsReflected(self::$requestReflection->getMethod($method))) {
            return null;
        }

        return resolve(ReflectedTypeAcceptor::class)->accept($tsInfo);
    }

    /**
     * Whether every class the declared return names reaches a page prop as the type reflection derived.
     *
     * `toTsType()` reads `__toString` as `string`, but `json_encode` ignores it and emits an object:
     * `allFiles()`'s UploadedFile and `interval()`'s CarbonInterval are not the strings it promises.
     */
    private function serializesAsReflected(ReflectionMethod $method): bool
    {
        $returnType = $method->getReturnType();
        $docComment = $method->getDocComment();

        $declared = $docComment === false ? '' : (string) LaravelTsPublish::extractReturnTypeFromDocblock($docComment);

        $arms = match (true) {
            $returnType instanceof ReflectionNamedType => [$returnType],
            $returnType instanceof ReflectionUnionType,
            $returnType instanceof ReflectionIntersectionType => $returnType->getTypes(),
            default => [],
        };

        foreach ($arms as $arm) {
            if ($arm instanceof ReflectionNamedType && ! $arm->isBuiltin()) {
                $declared .= '|'.$arm->getName();
            }
        }

        // `class_exists` mirrors step 5b's own gate, so an interface — which it never launders — is skipped.
        // Request writes every class in its declarations fully qualified, so no use map is needed here.
        foreach (preg_split('/[^\w\\\\]+/', $declared) ?: [] as $token) {
            if (str_contains($token, '\\') && class_exists($token) && ! is_a($token, JsonSerializable::class, true)) {
                return false;
            }
        }

        return true;
    }
}
