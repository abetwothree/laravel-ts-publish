<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRulesAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AuthUserResolver;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AppliesKnownMethodRules;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesAuthHelperCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use JsonSerializable;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
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

    /**
     * Reflections cached per bound Request subclass, so a form request's own typed helpers are
     * read too rather than only the base `Illuminate\Http\Request` surface.
     *
     * @var array<class-string<Request>, ReflectionClass<Request>>
     */
    private static array $requestReflections = [];

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

        $boundClass = $scope->requestVarNames[$expr->var->name];

        // validated() lives on FormRequest and is untyped; the rules are the only source of its shape.
        // Resolving them instantiates the form request and runs rules() — the same trade-off form
        // requests already make, at one more call site.
        if ($method === 'validated' && is_a($boundClass, FormRequest::class, true)) {
            return $this->validatedKeyRule($expr, $boundClass);
        }

        // Reflecting against the bound class — not just the base Request — means a FormRequest's
        // own typed helpers are read too. Declining on an unusable type matters: knownMethodRule()
        // runs next. A call-site default never widens a Request type; config()'s default IS the value.
        self::$requestReflections[$boundClass] ??= new ReflectionClass($boundClass);
        $reflection = self::$requestReflections[$boundClass];

        if (! $reflection->hasMethod($method)) {
            return null;
        }

        $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes($reflection, $method);

        // A prop is JSON, and a bare `@return array` carries no key evidence: the `unknown[]` it
        // derives claims a list for the string-keyed `all()`. Vagueness also covers a `| unknown` arm.
        if (LaravelTsPublish::isVagueTsType($tsInfo['type'])
            || ! $this->serializesAsReflected($reflection->getMethod($method))) {
            return null;
        }

        return resolve(ReflectedTypeAcceptor::class)->accept($tsInfo);
    }

    /**
     * Type `$request->validated('key')` from the bound FormRequest's rules() — the only source of
     * validated()'s shape, since the method itself is untyped. Declines a non-literal key, a key
     * the rules never mention, or one rules() marks prohibited.
     *
     * @param  class-string<FormRequest>  $formRequestClass
     * @return ValueExpressionResult|null
     */
    private function validatedKeyRule(MethodCall $expr, string $formRequestClass): ?array
    {
        $args = CallArguments::for($expr, new ReflectionMethod(FormRequest::class, 'validated'));
        $keyArg = $args->named('key');
        $defaultPosition = $args->positionOf('default');

        if ($keyArg === null
            || ! $keyArg->value instanceof String_
            || ($defaultPosition !== null && $args->passedCount() > $defaultPosition)) {
            return null;
        }

        $key = $keyArg->value->value;

        foreach (resolve(FormRequestRulesAnalyzer::class)->analyze($formRequestClass) as $field) {
            if ($field->fieldPath !== $key) {
                continue;
            }

            return $field->isProhibited ? null : [
                'type' => $field->tsType.($field->isNullable ? ' | null' : ''),
                'optional' => ! $field->isRequired,
            ];
        }

        return null;
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
            // A DNF arm is an intersection nested inside a union: flatten one level to reach its names.
            foreach ($arm instanceof ReflectionIntersectionType ? $arm->getTypes() : [$arm] as $named) {
                if ($named instanceof ReflectionNamedType && ! $named->isBuiltin()) {
                    $declared .= '|'.$named->getName();
                }
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
