<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use Illuminate\Http\Resources\CollectsResources;
use Illuminate\Http\Resources\ConditionallyLoadsAttributes;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Gate;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

/**
 * The framework calls known only to read what they are handed: Laravel's resource helpers, which call at most the
 * callbacks they are given, its URL helpers, the Gate facade's checks, a resource's own constructor and Eloquent's
 * reading methods.
 *
 * @internal
 */
final class ReadOnlyCalls
{
    /** The framework's resource classes and traits, whose helpers only read the resource. */
    public const array READING_DECLARERS = [
        JsonResource::class, ResourceCollection::class, ConditionallyLoadsAttributes::class, CollectsResources::class,
    ];

    /** The JsonResource helpers that read what they are given, calling a closure given at most, lowercased. */
    public const array RESOURCE_HELPERS = [
        'when', 'unless', 'whenloaded', 'whennotnull', 'whennull', 'whenhas', 'whencounted', 'whenaggregated',
        'whenpivotloaded', 'whenpivotloadedas', 'whenexistsloaded', 'mergewhen', 'mergeunless', 'merge',
    ];

    /** The helpers that call their callback with a member of the resource, lowercased. */
    public const array HANDING_HELPERS = ['whenloaded', 'whenhas', 'whencounted', 'whenaggregated', 'whenexistsloaded'];

    /** The parameters of a JsonResource helper whose closure the helper calls. */
    public const array CALLBACK_PARAMETERS = ['value', 'default'];

    /** The Gate facade's checks, which run the app's gates and policies. */
    public const array GATE_CHECKS = ['allows', 'denies', 'check', 'any', 'none'];

    /** The helpers that only read a model to build a URL. */
    public const array URL_HELPERS = ['route', 'url'];

    /** The helpers that return the authentication guard. */
    public const array AUTH_HELPERS = ['auth'];

    /**
     * The static methods that build a resource, or a collection of it, through `new`, lowercased, each with the
     * JsonResource methods it runs.
     */
    public const array RESOURCE_BUILDERS = ['make' => ['make'], 'collection' => ['collection', 'newCollection']];

    /** The namespace Eloquent's model, its concerns and the SoftDeletes trait declare MODEL_READERS in. */
    public const string ELOQUENT = 'Illuminate\\Database\\Eloquent\\';

    /** Eloquent's methods that read a model and change nothing on it; `trashed()` is the SoftDeletes trait's. */
    public const array MODEL_READERS = [
        'getKey', 'getKeyName', 'getKeyType', 'getRouteKey', 'getRouteKeyName', 'getTable', 'getForeignKey',
        'getMorphClass', 'getQualifiedKeyName', 'qualifyColumn',
        'getAttribute', 'getAttributes', 'getRawOriginal', 'getOriginal', 'getDirty', 'getChanges', 'isDirty',
        'isClean', 'wasChanged', 'originalIsEquivalent', 'hasAppended',
        'relationLoaded', 'getRelation', 'getRelations', 'getRelationValue',
        'is', 'isNot', 'trashed', 'usesTimestamps', 'getConnectionName',
    ];

    /**
     * The helper RESOURCE_HELPERS lists that a call on `$this`, or through `$this::`, `self::`, `static::` or
     * `parent::`, runs, where the framework's resource classes declare it.
     */
    public static function resourceHelper(
        MethodCall|NullsafeMethodCall|StaticCall $call,
        AnalysisScope $scope,
    ): ?ReflectionMethod {
        if (! $call->name instanceof Identifier
            || ! in_array($call->name->toLowerString(), self::RESOURCE_HELPERS, true)
        ) {
            return null;
        }

        $name = $call->name->toString();
        $class = match (true) {
            $call instanceof StaticCall => $scope->staticCallClass($call),
            $call->var instanceof Variable && $call->var->name === 'this' => $scope->thisMemberClass($name, true),
            default => null,
        };

        return $class === null ? null : self::helperOn($class, $name);
    }

    /**
     * The helper RESOURCE_HELPERS lists that a method of a class names, where the framework's resource classes
     * declare it and each method it calls on `$this`, rather than the class overriding one.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function helperOn(ReflectionClass $class, string $method): ?ReflectionMethod
    {
        if (! in_array(strtolower($method), self::RESOURCE_HELPERS, true) || ! $class->hasMethod($method)) {
            return null;
        }

        $helper = $class->getMethod($method);

        return self::runsFrameworkCode($class, $helper, []) ? $helper : null;
    }

    /**
     * The arguments of a resource helper's call that the helper may call, bound to its callback parameters.
     *
     * @return list<Expr>
     */
    public static function callbacks(CallArguments $arguments): array
    {
        return array_values(array_filter(
            array_map(static fn (string $name): ?Expr => $arguments->named($name)?->value, self::CALLBACK_PARAMETERS),
            static fn (?Expr $callback): bool => $callback !== null,
        ));
    }

    /**
     * Whether a value names a callable other than a closure written there: a first-class callable,
     * Closure::fromCallable(), a function's or a static method's name, or a class-and-method array.
     */
    public static function namesCallable(Expr $value): bool
    {
        $items = $value instanceof Array_ ? $value->items : [];
        $class = isset($items[0]) ? $items[0]->value : null;

        return match (true) {
            $value instanceof CallLike && $value->isFirstClassCallable() => true,
            $value instanceof StaticCall => $value->class instanceof Name && $value->class->toLowerString() === 'closure'
                && $value->name instanceof Identifier && $value->name->toLowerString() === 'fromcallable',
            $value instanceof String_ => str_contains($value->value, '::') || function_exists($value->value),
            default => count($items) === 2 && isset($items[1]) && $items[1]->value instanceof String_ && (self::isClassName($class)
                || ($class instanceof String_ && class_exists($class->value))),
        };
    }

    /**
     * Whether a function call runs Laravel's `route()` or `url()`.
     */
    public static function urlHelper(FuncCall $call): bool
    {
        return $call->name instanceof Name && self::runsLaravelHelper($call->name, self::URL_HELPERS);
    }

    /**
     * Whether a value spells a callable relative to the subject, which runs on `$this`: `'static::m'` or
     * `['parent', 'm']`.
     */
    public static function namesRelativeCallable(Node $node): bool
    {
        return self::relativeCallable($node) !== null;
    }

    /**
     * The class keyword, lowercased, and the method of a callable relative to the subject, the method null where the
     * source spells none; null for any other value.
     *
     * @return array{string, string|null}|null
     */
    public static function relativeCallable(Node $node): ?array
    {
        if ($node instanceof String_) {
            return preg_match('/^(self|static|parent)::(.*)$/is', $node->value, $parts) === 1
                ? [strtolower($parts[1]), $parts[2] === '' ? null : $parts[2]]
                : null;
        }

        $items = $node instanceof Array_ && count($node->items) === 2 ? $node->items : [];
        $class = isset($items[0]) ? $items[0]->value : null;
        $method = isset($items[1]) ? $items[1]->value : null;

        return $class instanceof String_ && in_array(strtolower($class->value), ['self', 'static', 'parent'], true)
            ? [strtolower($class->value), $method instanceof String_ ? $method->value : null]
            : null;
    }

    /**
     * Whether a function call runs Laravel's `auth()`.
     */
    public static function authHelper(FuncCall $call): bool
    {
        return $call->name instanceof Name && self::runsLaravelHelper($call->name, self::AUTH_HELPERS);
    }

    /**
     * Whether a static call runs one of the Gate facade's checks.
     */
    public static function gateCheck(StaticCall $call): bool
    {
        return $call->class instanceof Name && in_array($call->class->toString(), [Gate::class, 'Gate'], true)
            && $call->name instanceof Identifier && in_array($call->name->toLowerString(), self::GATE_CHECKS, true);
    }

    /**
     * Whether `new` of a class builds a resource with JsonResource's own constructor, which only holds what it is
     * handed: the resource's toArray() runs once the literal holding it is built.
     */
    public static function buildsResource(string $class): bool
    {
        return is_a($class, JsonResource::class, true)
            && new ReflectionMethod($class, '__construct')->getDeclaringClass()->getName() === JsonResource::class;
    }

    /**
     * Whether a static call builds a resource as `new` of it does: a builder RESOURCE_BUILDERS lists, on a class with
     * JsonResource's own constructor that overrides none of the methods the builder runs.
     */
    public static function buildsResourceStatically(StaticCall $call): bool
    {
        $class = $call->class instanceof Name && ! $call->class->isSpecialClassName() ? $call->class->toString() : null;
        $runs = $call->name instanceof Identifier
            ? self::RESOURCE_BUILDERS[$call->name->toLowerString()] ?? null
            : null;

        return $class !== null && $runs !== null && self::buildsResource($class) && array_all(
            $runs,
            static fn (string $method): bool => new ReflectionMethod($class, $method)->getDeclaringClass()->getName()
                === JsonResource::class,
        );
    }

    /**
     * Whether a method only reads an object of each of the classes: MODEL_READERS lists it, its name matched as PHP
     * matches one, in any case, and each class runs Eloquent's own, which no class past Eloquent overrides.
     *
     * @param  list<ReflectionClass<object>>  $receivers
     */
    public static function readsModel(string $method, array $receivers): bool
    {
        return $receivers !== []
            && array_any(self::MODEL_READERS, static fn (string $reader): bool => strcasecmp($reader, $method) === 0)
            && array_all($receivers, static fn (ReflectionClass $class): bool => $class->hasMethod($method)
                && str_starts_with(DeclaringFile::ofMethod($class->getMethod($method))->getName(), self::ELOQUENT));
    }

    /**
     * Whether an expression is a `::class` name.
     */
    public static function isClassName(?Expr $expr): bool
    {
        return $expr instanceof ClassConstFetch && $expr->name instanceof Identifier && $expr->name->toLowerString() === 'class';
    }

    /**
     * Whether a method of a class runs only the framework's resource code: the framework's resource classes declare
     * it, and each method its body calls on `$this`, as `unless()` calls `when()`, which the class may override.
     *
     * @param  ReflectionClass<object>  $class
     * @param  list<string>  $seen  the methods, lowercased, the chain already holds
     */
    private static function runsFrameworkCode(ReflectionClass $class, ReflectionMethod $method, array $seen): bool
    {
        if (! in_array(DeclaringFile::ofMethod($method)->getName(), self::READING_DECLARERS, true)) {
            return false;
        }

        $seen[] = strtolower($method->getName());

        return resolve(AnalysisMemo::class)->rememberFact(
            'framework-helper:'.$class->getName().'::'.$method->getName(),
            static function () use ($class, $method, $seen): bool {
                $called = self::thisCalls($class, $method);

                return $called !== null && array_all($called, static fn (?string $name): bool => $name !== null
                    && (in_array(strtolower($name), $seen, true) || ($class->hasMethod($name)
                        && self::runsFrameworkCode($class, $class->getMethod($name), $seen))));
            },
        );
    }

    /**
     * The names of the methods a method's body calls on `$this`, each null where the call computes it; null where the
     * body cannot be read.
     *
     * @param  ReflectionClass<object>  $class  the class whose method it is
     * @return list<string|null>|null
     */
    private static function thisCalls(ReflectionClass $class, ReflectionMethod $method): ?array
    {
        $body = resolve(MethodLocator::class)->locate($class->getName(), $method->getName())?->method->stmts;

        if ($body === null) {
            return null;
        }

        $finder = new NodeFinder;
        $calls = [
            ...$finder->findInstanceOf($body, MethodCall::class),
            ...$finder->findInstanceOf($body, NullsafeMethodCall::class),
        ];

        return array_values(array_map(
            static fn (MethodCall|NullsafeMethodCall $call): ?string => $call->name instanceof Identifier
                ? $call->name->toString()
                : null,
            array_filter($calls, static fn (MethodCall|NullsafeMethodCall $call): bool => CallableTargets::isThis($call->var)),
        ));
    }

    /**
     * Whether a name runs one of Laravel's global helpers: no namespaced function of that name shadows it, and the
     * global one is the framework's.
     *
     * @param  list<string>  $helpers
     */
    private static function runsLaravelHelper(Name $name, array $helpers): bool
    {
        $namespaced = $name->getAttribute('namespacedName');

        if (! in_array($name->toLowerString(), $helpers, true)
            || ($namespaced instanceof Name && function_exists($namespaced->toString()))
            || ! function_exists($name->toString())
        ) {
            return false;
        }

        $file = str_replace('\\', '/', (string) new ReflectionFunction($name->toString())->getFileName());

        return str_ends_with($file, '/Illuminate/Foundation/helpers.php');
    }
}
