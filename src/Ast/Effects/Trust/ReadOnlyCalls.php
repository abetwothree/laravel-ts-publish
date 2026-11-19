<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallablePair;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
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
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

/**
 * The framework calls known only to read what they are handed: Laravel's resource helpers, which call at most the
 * callbacks they are given, its URL helpers, the Gate facade's checks, a resource's own constructor and Eloquent's
 * reading methods.
 *
 * @phpstan-import-type UserJudge from FrameworkDispatch
 *
 * @internal
 */
final class ReadOnlyCalls
{
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

        return $class === null ? null : self::helperOn($class, $name, $scope->runtime->class);
    }

    /**
     * The helper RESOURCE_HELPERS lists that a method of a class names, where the framework's resource classes declare
     * it and FrameworkDispatch trusts it as an instance of the runtime class runs it, past any method that class
     * overrides, which $userMayWrite judges.
     *
     * @param  ReflectionClass<object>  $class
     * @param  ReflectionClass<object>|null  $runtime  the class `$this` is an instance of; $class where null
     * @param  UserJudge|null  $userMayWrite  whether running a method the app declares, on a class, may write
     */
    public static function helperOn(
        ReflectionClass $class,
        string $method,
        ?ReflectionClass $runtime = null,
        ?Closure $userMayWrite = null,
    ): ?ReflectionMethod {
        if (! in_array(strtolower($method), self::RESOURCE_HELPERS, true) || ! $class->hasMethod($method)) {
            return null;
        }

        $helper = $class->getMethod($method);

        return FrameworkDispatch::trusted(FrameworkFamily::ResourceHelpers, $helper, $runtime ?? $class, $userMayWrite)
            ? $helper
            : null;
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
        $pair = $value instanceof Array_ ? CallablePair::of($value) : null;
        $class = $pair instanceof CallablePair ? $pair->holder : null;

        return match (true) {
            $value instanceof CallLike && $value->isFirstClassCallable() => true,
            $value instanceof StaticCall => $value->class instanceof Name && $value->class->toLowerString() === 'closure'
                && $value->name instanceof Identifier && $value->name->toLowerString() === 'fromcallable',
            $value instanceof String_ => str_contains($value->value, '::') || function_exists($value->value),
            default => $pair instanceof CallablePair && $pair->method instanceof String_ && (self::isClassName($class)
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
     * source spells none, as in an open array whose keys may make a keyword item its class; null for any other value.
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

        $pair = $node instanceof Array_ ? CallablePair::of($node) : null;

        if ($pair instanceof CallablePair) {
            $keyword = self::relativeKeyword($pair->holder);
            $method = $pair->method instanceof String_ ? $pair->method->value : null;

            return $keyword === null ? null : [$keyword, $method];
        }

        $holder = $node instanceof Array_ && $pair === false
            ? array_find($node->items, static fn (ArrayItem $one): bool => self::relativeKeyword($one->value) !== null)
            : null;
        $keyword = $holder === null ? null : self::relativeKeyword($holder->value);

        return $keyword === null ? null : [$keyword, null];
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
     * matches one, in any case, and each class runs Eloquent's own, which FrameworkDispatch trusts there.
     *
     * @param  list<ReflectionClass<object>>  $receivers
     */
    public static function readsModel(string $method, array $receivers): bool
    {
        return $receivers !== []
            && array_any(self::MODEL_READERS, static fn (string $reader): bool => strcasecmp($reader, $method) === 0)
            && array_all($receivers, static fn (ReflectionClass $class): bool => $class->hasMethod($method)
                && FrameworkDispatch::trusted(FrameworkFamily::Eloquent, $class->getMethod($method), $class));
    }

    /**
     * Whether an expression is a `::class` name.
     */
    public static function isClassName(?Expr $expr): bool
    {
        return $expr instanceof ClassConstFetch && $expr->name instanceof Identifier && $expr->name->toLowerString() === 'class';
    }

    /**
     * The class keyword, lowercased, a string spells: `self`, `static` or `parent`; null for any other value.
     */
    private static function relativeKeyword(Expr $value): ?string
    {
        $keyword = $value instanceof String_ ? strtolower($value->value) : null;

        return in_array($keyword, ['self', 'static', 'parent'], true) ? $keyword : null;
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
