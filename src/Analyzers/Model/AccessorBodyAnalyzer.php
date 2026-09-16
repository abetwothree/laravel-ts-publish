<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Model;

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\ResultTypeInfoBridge;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod;

/**
 * Types an accessor from what its getter body actually returns, once the signature and the
 * `Attribute<>` docblock have both proven vague.
 *
 * Registered as a singleton so the cycle guard spans every call site, not one instance.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 */
final class AccessorBodyAnalyzer
{
    use InspectsAstNodes;

    /** @var array<string, true> model@attribute bodies on the stack, so two accessors reading each other terminate */
    private array $analyzing = [];

    /**
     * The TypeScript type a model accessor's getter body resolves to.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return TypeScriptTypeInfo|null null when there is no readable body, a cycle, or nothing better than unknown
     */
    public function analyze(string $modelFqcn, string $attributeName): ?array
    {
        $key = $modelFqcn.'@'.$attributeName;

        if (isset($this->analyzing[$key])) {
            return null;
        }

        $this->analyzing[$key] = true;

        try {
            $result = $this->resolveBody($modelFqcn, $attributeName);
        } finally {
            unset($this->analyzing[$key]);
        }

        // `never[]` is what an empty `[]` literal resolves to — no element information at all, so a
        // @property tag or a cast still knows the elements better than the body does.
        if ($result === null || $result['type'] === 'unknown' || $result['type'] === 'never[]') {
            return null;
        }

        return resolve(ResultTypeInfoBridge::class)->toTypeInfo($result);
    }

    /**
     * The getter closure of a new-style accessor, or an old-style accessor's body wrapped as a closure.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ValueExpressionResult|null
     */
    private function resolveBody(string $modelFqcn, string $attributeName): ?array
    {
        $locator = resolve(MethodLocator::class);
        $engine = resolve(AstEngine::class);

        $newStyle = $locator->locate($modelFqcn, Str::camel($attributeName));
        $getter = $newStyle === null ? null : $this->getterClosure($newStyle->method);

        if ($newStyle !== null && $getter !== null) {
            return $engine->analyzeClosure($modelFqcn, $getter, $newStyle);
        }

        // A new-style method whose getter is not a closure — or a same-named non-accessor, e.g. a relation —
        // falls through. Eloquent would prefer the new-style getter, but an unreadable one types nothing, and
        // the fallthrough is what lets `get{Name}Attribute()` still answer for a camel-named collision.
        $oldStyle = $locator->locate($modelFqcn, 'get'.Str::studly($attributeName).'Attribute');

        if ($oldStyle === null || $oldStyle->method->stmts === null) {
            return null;
        }

        return $engine->analyzeClosure($modelFqcn, new ClosureExpr(['stmts' => $oldStyle->method->stmts]), $oldStyle);
    }

    /**
     * The `get` closure an accessor returns through Attribute::make(), Attribute::get(), or new Attribute().
     *
     * The first getter found wins; branches are not merged, and no fixture returns a different one per branch.
     */
    private function getterClosure(ClassMethod $method): ClosureExpr|ArrowFunction|null
    {
        foreach ($this->collectReturnExpressions($method->stmts ?? []) as $returned) {
            $getter = $this->attributeCallArguments($returned)?->named('get')?->value;

            if ($getter instanceof ClosureExpr || $getter instanceof ArrowFunction) {
                return $getter;
            }
        }

        return null;
    }

    /** An `Attribute::make()`/`Attribute::get()` call or a `new Attribute()`, read against its own signature. */
    private function attributeCallArguments(Expr $returned): ?CallArguments
    {
        if ($returned instanceof StaticCall
            && $returned->class instanceof Name
            && is_a($returned->class->toString(), Attribute::class, true)
            && $returned->name instanceof Identifier
            && in_array($returned->name->toString(), ['make', 'get'], true)) {
            return CallArguments::for($returned, new ReflectionMethod(Attribute::class, $returned->name->toString()));
        }

        if ($returned instanceof New_
            && $returned->class instanceof Name
            && is_a($returned->class->toString(), Attribute::class, true)) {
            return CallArguments::for($returned, new ReflectionMethod(Attribute::class, '__construct'));
        }

        return null;
    }
}
