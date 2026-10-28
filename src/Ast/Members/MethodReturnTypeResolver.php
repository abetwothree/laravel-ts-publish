<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BuildsInlineObjectTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use ReflectionMethod;

/**
 * A method's return type: its declaration, else, when that is too vague to publish, the shape its literal body spells
 * for the declaration's array arm beside its other arms, else the value its returns hold. Memoized per `class@method`,
 * known or assumed runtime class, and bindings; docs/components/receiver-types.md § Following a method's return type.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type PassedBinding from ParameterBinder
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type OwnReturn = array{Return_, bool}
 *
 * @internal
 */
final class MethodReturnTypeResolver
{
    use BuildsInlineObjectTypes;
    use InspectsAstNodes;

    /**
     * Reflect a method's return type, else analyze its body once per set of bindings its calls' arguments make;
     * declines one naming a model with no published file.
     *
     * @param  class-string  $class
     * @param  CallSite|null  $site  the call, read where it is written; null reads the body with its declarations only
     * @param  class-string<Model>|null  $backing  the model a caller's `instanceof` proof holds `$this->resource` to
     * @param  ReflectionClass<object>|null  $runtime  the caller's runtime class, which `static` names in the body and
     *                                                 whose model `$this` reads, as PHP forwards it through `self::`
     *                                                 and a private `$this->` call
     * @param  bool  $assumesRuntime  the body runs on an object only assumed to be that class, as a receiver's does
     * @return ValueExpressionResult|null
     */
    public function resolve(
        string $class,
        string $methodName,
        ?CallSite $site = null,
        ?string $backing = null,
        ?ReflectionClass $runtime = null,
        bool $assumesRuntime = false,
    ): ?array {
        if (! method_exists($class, $methodName)) {
            return null;
        }

        // Read before the memo opens the callee's frame, so what the arguments record belongs to the caller's.
        $passed = $site === null ? [] : $this->passedToBody($class, $methodName, $site);

        return resolve(AnalysisMemo::class)->remember(
            'method-return:'.$class.'@'.$methodName.CallSite::signature($passed).($backing === null ? '' : '@backing:'.$backing)
                .AnalysisMemo::runtimeKey($class, $runtime, $assumesRuntime),
            function () use ($class, $methodName, $passed, $backing, $runtime, $assumesRuntime): ?array {
                $result = $this->reflectOrReadBody($class, $methodName, $passed, $backing, $runtime, $assumesRuntime);

                return $result !== null && ValueResult::namesOnlyPublishedModels($result) ? $result : null;
            },
        );
    }

    /**
     * What a call passes a method whose body would be read; nothing where the declaration answers without the body.
     *
     * @param  class-string  $class
     * @return array<string, PassedBinding>
     */
    private function passedToBody(string $class, string $methodName, CallSite $site): array
    {
        if (! $site->passesArguments()) {
            return [];
        }

        $method = new ReflectionMethod($class, $methodName);

        if (! resolve(ParameterBinder::class)->takesPassedValues($method)) {
            return [];
        }

        $reflected = resolve(ReflectedTypeAcceptor::class)
            ->accept(LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($class), $methodName));

        return $reflected !== null && ! TsTypeString::isVagueTsType($reflected['type'])
            ? []
            : $site->passedTo($method, valueRead: true);
    }

    /**
     * The precise reflected return type, else the body's shape beside the declaration's other arms, else its value,
     * unless the declaration spells as a string-keyed `Record` the vague list that value reads as.
     *
     * @param  class-string  $class
     * @param  array<string, PassedBinding>  $passed
     * @param  class-string<Model>|null  $backing
     * @param  ReflectionClass<object>|null  $runtime
     * @return ValueExpressionResult|null
     */
    private function reflectOrReadBody(
        string $class,
        string $methodName,
        array $passed,
        ?string $backing,
        ?ReflectionClass $runtime,
        bool $assumesRuntime,
    ): ?array {
        $reflected = resolve(ReflectedTypeAcceptor::class)
            ->accept(LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($class), $methodName));

        if ($reflected !== null && ! TsTypeString::isVagueTsType($reflected['type'])) {
            return $reflected;
        }

        return $this->bodyType($class, $methodName, $reflected, $passed, $backing, $runtime, $assumesRuntime)
            ?? $this->declaredOverVagueList($this->bodyValue($class, $methodName, $passed, $backing, $runtime, $assumesRuntime), $reflected)
            ?? $reflected;
    }

    /**
     * A body value, or the declaration where it spells that value's vague list as a `Record`, as
     * LaravelTsPublish::methodOrDocblockReturnTypes() prefers it: a string-keyed array encodes as an object.
     *
     * @param  ValueExpressionResult|null  $value
     * @param  ValueExpressionResult|null  $reflected
     * @return ValueExpressionResult|null
     */
    private function declaredOverVagueList(?array $value, ?array $reflected): ?array
    {
        return $value !== null && $reflected !== null && TsTypeString::spellsListAsRecord($value['type'], $reflected['type'])
            ? $reflected
            : $value;
    }

    /**
     * The inline object a method's literal return body spells, beside every non-array arm its declaration names, open
     * where it holds keys the analysis never read; null when a return the shape does not describe is left over, or
     * the shape spells nothing importable.
     *
     * The cycle guard stays per method, whatever the call passes, so a recursion passing a new value each time still
     * stops at its first repeat.
     *
     * @param  class-string  $class
     * @param  ValueExpressionResult|null  $reflected
     * @param  array<string, PassedBinding>  $passed
     * @param  class-string<Model>|null  $backing
     * @param  ReflectionClass<object>|null  $runtime
     * @return ValueExpressionResult|null
     */
    private function bodyType(
        string $class,
        string $methodName,
        ?array $reflected,
        array $passed,
        ?string $backing,
        ?ReflectionClass $runtime,
        bool $assumesRuntime,
    ): ?array {
        $key = 'method-body:'.$class.'@'.$methodName;
        $otherArms = $this->nonArrayArms($reflected);
        $context = resolve(MethodLocator::class)->locate($class, $methodName);
        $body = $context?->method->stmts;
        $memo = resolve(AnalysisMemo::class);

        // Defence in depth: analyzeMethod() cuts the same cycle, and this stops one before it reaches the engine. It
        // comes last, so a body declined for its own shape never counts as a cycle cut.
        if ($otherArms === null
            || $body === null
            || ! $this->shapeReadsEveryReturn($body, $otherArms)
            || ! $memo->enter($key)
        ) {
            return null;
        }

        $runs = $runtime?->getName() ?? $class;

        try {
            $analysis = resolve(AstEngine::class)->analyzeMethod(
                $class,
                $methodName,
                $backing ?? (is_a($runs, Model::class, true) ? $runs : null),
                carriesImports: false,
                passed: $passed,
                readsCall: true,
                runtime: $runtime,
                assumesRuntime: $assumesRuntime,
            );
        } finally {
            $memo->leave($key);
        }

        if ($analysis->properties === []) {
            return null;
        }

        // The inline type carries custom imports and no FQCN channel, so a member keeps its type only where no channel
        // names a class in it and the imports bring in every name it spells; any other is `unknown`, its siblings stay.
        foreach ($analysis->properties as $index => $property) {
            if ($analysis->hasFqcnChannel($property['name'])
                || ! TsTypeString::importsCover($property['type'], $analysis->customImports)) {
                $analysis->properties[$index]['type'] = 'unknown';
            }
        }

        // Keys the walk never read reach the return, as a caller's do in a parameter it starts with no seed.
        if ($analysis->holdsUnreadKeys) {
            $signature = array_search('[key: string]', array_column($analysis->properties, 'name'), true);

            if ($signature === false) {
                $analysis->properties[] = ['name' => '[key: string]', 'type' => 'unknown', 'optional' => false, 'description' => ''];
            } else {
                $analysis->properties[$signature]['type'] = 'unknown';
            }
        }

        $type = $this->buildInlineObjectType($analysis);

        if (! TsTypeString::importsCover($type, $analysis->customImports)) {
            return null;
        }

        $imports = $this->spelledImports($analysis->customImports, $type);

        return [
            ...ValueResult::unknown(),
            'type' => TsTypeString::hoistNull([$type, ...$otherArms]),
            ...($imports === [] ? [] : ['customImports' => $imports]),
        ];
    }

    /**
     * The custom imports a shape spells a name of, each name once, so a member left `unknown` brings none.
     *
     * @param  TypesImportMap  $imports
     * @return TypesImportMap
     */
    private function spelledImports(array $imports, string $type): array
    {
        $spelled = [];

        foreach ($imports as $path => $names) {
            $kept = array_filter(array_unique($names), fn (string $name): bool => TsTypeString::typeNameOccursIn($name, $type));

            if ($kept !== []) {
                $spelled[$path] = array_values($kept);
            }
        }

        return $spelled;
    }

    /**
     * The value an application method's own returns hold; null for a dependency's method or one already under read.
     *
     * @param  class-string  $class
     * @param  array<string, PassedBinding>  $passed
     * @param  class-string<Model>|null  $backing
     * @param  ReflectionClass<object>|null  $runtime
     * @return ValueExpressionResult|null
     */
    private function bodyValue(
        string $class,
        string $methodName,
        array $passed,
        ?string $backing,
        ?ReflectionClass $runtime,
        bool $assumesRuntime,
    ): ?array {
        $file = new ReflectionMethod($class, $methodName)->getFileName();
        $key = 'method-body:'.$class.'@'.$methodName;
        $memo = resolve(AnalysisMemo::class);

        // bodyType()'s guard, so a recursion through either reader stops at its first repeat.
        if (DependencyFiles::ofApplication()->contains($file) || ! $memo->enter($key)) {
            return null;
        }

        try {
            return resolve(AstEngine::class)->analyzeMethodValue($class, $methodName, $passed, $backing, $runtime, $assumesRuntime);
        } finally {
            $memo->leave($key);
        }
    }

    /**
     * The arms of a vague declared return other than its array ones, which the body's shape stands in for; null when
     * one admits any value or names a type the shape could not carry the import of.
     *
     * @param  ValueExpressionResult|null  $reflected
     * @return list<string>|null
     */
    private function nonArrayArms(?array $reflected): ?array
    {
        $arms = [];

        foreach ($reflected === null ? [] : TsTypeString::splitTopLevelUnion($reflected['type']) as $arm) {
            if (TsTypeShape::elementType($arm) !== null || str_starts_with($arm, 'Record<')) {
                continue;
            }

            if ($arm === 'unknown' || TsTypeString::shapeValueHasUnimportableToken($arm)) {
                return null;
            }

            $arms[] = $arm;
        }

        return $arms;
    }

    /**
     * Whether the analysis of this body reads every value it returns, or a declared non-array arm covers what it skips.
     *
     * The analysis reads each literal, and each variable it reads completely, that only `if` and loop blocks enclose
     * when one has a key; with none, each variable leniently if every return is such a literal or variable, else the
     * first return. This counts a variable only as a first return beside no non-empty literal: a sound subset.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  list<string>  $otherArms
     */
    private function shapeReadsEveryReturn(array $stmts, array $otherArms): bool
    {
        $returns = $this->ownReturns($stmts);

        if ($returns === null) {
            return false;
        }

        $sweeps = array_any(
            $returns,
            fn (array $own): bool => $own[1] && $own[0]->expr instanceof Array_ && $own[0]->expr->items !== [],
        );
        $first = new NodeFinder()->findFirstInstanceOf($stmts, Return_::class);
        $readsAny = false;

        foreach ($returns as [$return, $direct]) {
            $read = $sweeps ? $direct && $return->expr instanceof Array_ : $return === $first;
            $readsAny = $readsAny || $read;

            if ($return->expr === null || (! $read && ! $this->armCovers($return->expr, $otherArms))) {
                return false;
            }
        }

        return $readsAny;
    }

    /**
     * Each of a body's own returns, flagged when only `if` and loop blocks enclose it; null when the body yields.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return list<OwnReturn>|null
     */
    private function ownReturns(array $stmts): ?array
    {
        if ($this->yields($stmts)) {
            return null;
        }

        $visitor = new /** @internal */ class extends NodeVisitorAbstract
        {
            /** @var list<array{Return_, bool}> */
            public array $returns = [];

            private int $enclosingBlocks = 0;

            /** Record a return with whether a block the branch sweep skips encloses it. */
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof FunctionLike || $node instanceof ClassLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                $this->enclosingBlocks += $this->hidesReturns($node) ? 1 : 0;

                if ($node instanceof Return_) {
                    $this->returns[] = [$node, $this->enclosingBlocks === 0];
                }

                return null;
            }

            /** Leave a block the branch sweep skips. */
            public function leaveNode(Node $node): ?int
            {
                $this->enclosingBlocks -= $this->hidesReturns($node) ? 1 : 0;

                return null;
            }

            /** Whether the branch sweep leaves the returns inside this block unread. */
            private function hidesReturns(Node $node): bool
            {
                return $node instanceof Switch_
                    || $node instanceof TryCatch
                    || $node instanceof Block
                    || $node instanceof Declare_;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->returns;
    }

    /**
     * Whether a declared non-array arm holds the value a returned `null`, boolean, string or number literal is.
     *
     * @param  list<string>  $otherArms
     */
    private function armCovers(Expr $returned, array $otherArms): bool
    {
        $constant = $returned instanceof ConstFetch ? $returned->name->toLowerString() : null;

        $literal = match (true) {
            in_array($constant, ['null', 'true', 'false'], true) => $constant,
            $returned instanceof String_ => 'string',
            $returned instanceof Int_, $returned instanceof Float_ => 'number',
            default => null,
        };

        return match ($literal) {
            'null', 'string', 'number' => in_array($literal, $otherArms, true),
            'true', 'false' => in_array($literal, $otherArms, true) || in_array('boolean', $otherArms, true),
            default => false,
        };
    }
}
