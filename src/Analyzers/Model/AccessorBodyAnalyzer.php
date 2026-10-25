<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Model;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ResultTypeInfoBridge;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Concerns\NamesAccessorMethods;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
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
 * Its cycle guard and its memo for the run live in AnalysisMemo, keyed per model@attribute and import mode.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 *
 * @internal
 */
final class AccessorBodyAnalyzer
{
    use InspectsAstNodes;
    use NamesAccessorMethods;

    /**
     * The TypeScript type a model accessor's getter body resolves to.
     *
     * @param  class-string<Model>  $modelFqcn
     * @param  bool  $carriesImports  false when the reader carries no import, so the getter's filters name no token
     * @return TypeScriptTypeInfo|null null when there is no readable body, a cycle, nothing better than unknown, or a
     *                                 class or enum the model file cannot name
     */
    public function analyze(string $modelFqcn, string $attributeName, bool $carriesImports = true): ?array
    {
        // Keyed per import mode, so an analysis that keeps imports never stands in for one that carries none. Its guard
        // can still cut short the check with imports that an import-less read makes of a vague spelling.
        $key = 'accessor-body:'.$modelFqcn.'@'.$attributeName.($carriesImports ? '' : '@importless');

        return resolve(AnalysisMemo::class)->remember(
            $key,
            fn (): ?array => $this->analyzeOnce($modelFqcn, $attributeName, $carriesImports, $key),
        );
    }

    /**
     * Analyze the getter body once, guarded so two accessors reading each other terminate.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return TypeScriptTypeInfo|null
     */
    private function analyzeOnce(string $modelFqcn, string $attributeName, bool $carriesImports, string $key): ?array
    {
        $memo = resolve(AnalysisMemo::class);

        if (! $memo->enter($key)) {
            return null;
        }

        $dropped = DroppedUnionArms::dropped();

        try {
            // A body declined here gives way to what the model declares, so its placeholder is never published.
            return DroppedUnionArms::replaceable(
                fn (): ?array => $this->publishedBody($modelFqcn, $attributeName, $carriesImports, $dropped),
                fn (?array $info): bool => $info !== null,
            );
        } finally {
            $memo->leave($key);
        }
    }

    /**
     * The type a getter body publishes; null where it does not stand or spells a class the model file cannot name.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return TypeScriptTypeInfo|null
     */
    private function publishedBody(string $modelFqcn, string $attributeName, bool $carriesImports, int $dropped): ?array
    {
        $result = $this->resolveBody($modelFqcn, $attributeName, $carriesImports);

        if ($result === null || ! $this->bodyStands($result, $dropped)) {
            return null;
        }

        $info = resolve(ResultTypeInfoBridge::class)->toTypeInfo($result);

        return $this->namesEveryClass($result, $info) ? $info : null;
    }

    /**
     * Whether a body's type stands over a `@property` tag or a cast: not `unknown`, not the `never[]` of an empty `[]`,
     * which says nothing of the elements, and not a bare `null` a ternary or `??` left when it dropped an untyped arm.
     *
     * @param  ValueExpressionResult|null  $result
     */
    private function bodyStands(?array $result, int $dropped): bool
    {
        return $result !== null
            && ! in_array($result['type'], ['unknown', 'never[]'], true)
            && ! DroppedUnionArms::leftNullSince($result['type'], $dropped);
    }

    /**
     * Whether the model file can name every class and enum the body type spells.
     *
     * The aliasing pass gives each same-named class or enum its own alias one occurrence at a time, so the type must
     * name that token once per FQCN; and the bridge carries no resource channel, so a resource token has no import.
     *
     * @param  ValueExpressionResult  $result
     * @param  TypeScriptTypeInfo  $info
     */
    private function namesEveryClass(array $result, array $info): bool
    {
        // A backed enum's `…Kind` token is one of its occurrences too.
        /** @var array<string, non-empty-list<string>> $spellings enum type name => the names its tokens spell */
        $spellings = [];

        foreach ($info['enumFqcns'] as $i => $fqcn) {
            $name = $info['enumTypes'][$i];
            $spellings[$name] = [...($spellings[$name] ?? [$name]), ...ValueResult::enumSiblingNames($fqcn)];
        }

        foreach ([[$info['enumTypes'], $spellings], [$info['classes'], []]] as [$names, $spelledAs]) {
            foreach (array_count_values($names) as $name => $count) {
                $pattern = TsTypeString::queuedTokenPattern(array_values(array_unique($spelledAs[$name] ?? [(string) $name])));

                if ($count > 1 && preg_match_all($pattern, $info['type']) < $count) {
                    return false;
                }
            }
        }

        foreach (array_filter([$result['resourceFqcn'] ?? null, ...$result['embeddedResourceFqcns'] ?? []]) as $fqcn) {
            if (TsTypeString::typeNameOccursIn(TsNaming::resourceTypeName($fqcn), $info['type'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The getter closure of a new-style accessor, or an old-style accessor's body wrapped as a closure.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ValueExpressionResult|null
     */
    private function resolveBody(string $modelFqcn, string $attributeName, bool $carriesImports): ?array
    {
        $locator = resolve(MethodLocator::class);
        $engine = resolve(AstEngine::class);
        ['newStyle' => $newStyleName, 'oldStyle' => $oldStyleName] = $this->accessorMethodNames($attributeName);

        $newStyle = $locator->locate($modelFqcn, $newStyleName);
        $getter = $newStyle === null ? null : $this->getterClosure($newStyle->method);

        if ($newStyle !== null && $getter !== null) {
            return $engine->analyzeModelClosure($modelFqcn, $getter, $newStyle, $carriesImports);
        }

        // A new-style method whose getter is not a closure — or a same-named non-accessor, e.g. a relation —
        // falls through. Eloquent would prefer the new-style getter, but an unreadable one types nothing, and
        // the fallthrough is what lets `get{Name}Attribute()` still answer for a camel-named collision.
        $oldStyle = $locator->locate($modelFqcn, $oldStyleName);

        if ($oldStyle === null || $oldStyle->method->stmts === null) {
            return null;
        }

        return $engine->analyzeModelClosure($modelFqcn, new ClosureExpr(['stmts' => $oldStyle->method->stmts]), $oldStyle, $carriesImports);
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
