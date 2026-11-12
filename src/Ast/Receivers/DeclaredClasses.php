<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockNames;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ScanDialect;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeScanner;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * The classes a declaration names: a method's native return type, else PHP's tentative one, else its `@return`; a
 * property's native type or docblock, any native type, and a docblock union's class arms. The receiver resolver reads
 * declarations through it, and model inspection reads a relation method's return here without the resolver.
 *
 * An intersection such as `A&B` names its primary member, the first that is no interface, among the classes, and its
 * other members under `intersections`: a reader that skips them reads less of the value, never a class it is not.
 *
 * @phpstan-type Intersections array<class-string, non-empty-list<class-string>>
 * @phpstan-type DeclaredType array{classes: non-empty-list<class-string>, intersections: Intersections}
 * @phpstan-type JoinedArms array{classes: list<class-string>, intersections: Intersections}
 * @phpstan-type DocblockArms array{
 *     classes: list<class-string>, named: bool, nullable: bool, intersections: Intersections,
 * }
 * @phpstan-type CollectionGeneric array{key: KeyKind, element: class-string<Model>|null}
 *
 * @phpstan-import-type KeyKind from ReceiverType
 *
 * @internal
 */
final class DeclaredClasses
{
    /** The keys a collection generic may spell, either kind written as a union too. */
    private const string COLLECTION_KEY = '/^(?:int\s*\|\s*string|string\s*\|\s*int|array-key|string|int)$/';

    /** The PHPDoc array types whose last generic argument types every element. */
    private const array LIST_TYPES = ['list', 'non-empty-list', 'array', 'non-empty-array', 'iterable'];

    /** Type keywords PHP never lets a class or an import alias take, so an arm naming one is no class in any file. */
    private const array RESERVED_TYPES = [
        'array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'object', 'string', 'true',
        'void',
    ];

    /**
     * The classes a method returns: its native or tentative class type, else its `@return`; null for a non-class arm.
     *
     * `static` and `$this` name the receiver class, or the caller's runtime class where it passes one; `self` names the
     * class that declares the method. Visibility is the caller's concern.
     *
     * @return non-empty-list<class-string>|null
     */
    public static function returnClasses(string $class, string $method, ?string $staticClass = null): ?array
    {
        return self::returnType($class, $method, $staticClass)['classes'] ?? null;
    }

    /**
     * The classes returnClasses() reads, with each intersection's other members.
     *
     * @return DeclaredType|null
     */
    public static function returnType(string $class, string $method, ?string $staticClass = null): ?array
    {
        DependencyRecorder::recordClass($class);

        if (! method_exists($class, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($class, $method);
        $declaring = $reflection->getDeclaringClass()->getName();

        $native = self::reflectedReturnType($reflection);

        if ($native !== null) {
            return self::nativeType($native, $staticClass ?? $class, $declaring);
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $reflection->getDocComment());

        return $docblock === null
            ? null
            : self::docblockType($docblock, LaravelTsPublish::methodDeclaringFileClass($reflection), $staticClass ?? $class, $declaring);
    }

    /**
     * Whether a method declares it returns `static` or `$this` and nothing else but null.
     */
    public static function returnsStatic(string $class, string $method): bool
    {
        return self::declaresOwnReturn($class, $method, false);
    }

    /**
     * Whether a method's native, tentative or `@return` declaration names the class it is called on, `static` or
     * `$this`, generic arguments aside, and else only null: what it returns is as exact as its receiver.
     */
    public static function returnsOwnClass(string $class, string $method): bool
    {
        return self::declaresOwnReturn($class, $method, true);
    }

    /**
     * A function's native return type, else the tentative one PHP declares on its own classes' methods, which only an
     * internal method carries, so an override that drops it reads as untyped.
     */
    public static function reflectedReturnType(ReflectionFunctionAbstract $function): ?ReflectionType
    {
        return $function->getReturnType() ?? $function->getTentativeReturnType();
    }

    /**
     * A property's classes: its native class type, else its full docblock type, as PropertyDocblockTypeReader::tag()
     * finds it on the class the property was read on.
     *
     * @param  ReflectionClass<object>  $subject
     * @return non-empty-list<class-string>|null
     */
    public static function propertyClasses(ReflectionProperty $property, ReflectionClass $subject, string $receiverClass): ?array
    {
        return self::propertyType($property, $subject, $receiverClass)['classes'] ?? null;
    }

    /**
     * The classes propertyClasses() reads, with each intersection's other members.
     *
     * @param  ReflectionClass<object>  $subject
     * @return DeclaredType|null
     */
    public static function propertyType(ReflectionProperty $property, ReflectionClass $subject, string $receiverClass): ?array
    {
        $declaring = $property->getDeclaringClass();
        $native = self::nativeType($property->getType(), $receiverClass, $declaring->getName());

        if ($native !== null) {
            return $native;
        }

        $tag = resolve(PropertyDocblockTypeReader::class)->tag($property, $subject);

        return $tag === null
            ? null
            : self::docblockType($tag['type'], $tag['context'], $receiverClass, $declaring->getName());
    }

    /**
     * The classes each element of a property's array is declared as, by the docblock propertyType() reads, as
     * `@var list<X>` spells them; null where that docblock lists no class.
     *
     * @param  ReflectionClass<object>  $subject
     * @return DeclaredType|null
     */
    public static function propertyElementType(ReflectionProperty $property, ReflectionClass $subject, string $receiverClass): ?array
    {
        $tag = resolve(PropertyDocblockTypeReader::class)->tag($property, $subject);
        $element = $tag === null ? null : self::listedElement($tag['type']);

        return $element === null
            ? null
            : self::docblockType($element, $tag['context'], $receiverClass, $property->getDeclaringClass()->getName());
    }

    /**
     * The classes a native type names, `parent` read as the parent of the class `self` names, and an intersection, DNF
     * arm or not, as its primary member; null when it is absent or has any builtin arm but `null`.
     *
     * @return non-empty-list<class-string>|null
     */
    public static function nativeClasses(?ReflectionType $type, string $staticClass, string $selfClass): ?array
    {
        return self::nativeType($type, $staticClass, $selfClass)['classes'] ?? null;
    }

    /**
     * The classes nativeClasses() reads, with each intersection's other members: `(A&B)|null` holds an A that is a B.
     *
     * @return DeclaredType|null
     */
    public static function nativeType(?ReflectionType $type, string $staticClass, string $selfClass): ?array
    {
        $arms = [];

        foreach ($type instanceof ReflectionUnionType ? $type->getTypes() : [$type] as $arm) {
            $members = [];

            foreach ($arm instanceof ReflectionIntersectionType ? $arm->getTypes() : [$arm] as $member) {
                if (! $member instanceof ReflectionNamedType) {
                    return null;
                }

                if ($member->getName() === 'null') {
                    continue 2;
                }

                $class = self::nativeClass($member, $staticClass, $selfClass);

                if ($class === null) {
                    return null;
                }

                $members[] = $class;
            }

            $arms[] = $members;
        }

        $joined = self::joined($arms);

        return $joined['classes'] === [] ? null : $joined;
    }

    /**
     * The classes a docblock union names, resolved against the declaring file's imports; null for any arm not a class.
     *
     * @param  ReflectionClass<object>  $context
     * @return non-empty-list<class-string>|null
     */
    public static function docblockClasses(string $type, ReflectionClass $context, string $staticClass, string $selfClass): ?array
    {
        return self::docblockType($type, $context, $staticClass, $selfClass)['classes'] ?? null;
    }

    /**
     * The classes docblockClasses() reads, with each intersection's other members.
     *
     * @param  ReflectionClass<object>  $context
     * @return DeclaredType|null
     */
    public static function docblockType(string $type, ReflectionClass $context, string $staticClass, string $selfClass): ?array
    {
        $arms = self::docblockArms($type, $context, $staticClass, $selfClass);

        return $arms['named'] && $arms['classes'] !== []
            ? ['classes' => $arms['classes'], 'intersections' => $arms['intersections']]
            : null;
    }

    /**
     * The class arms a docblock union names, resolved against the declaring file's imports, whether every arm but
     * `null` names one, whether a `null` arm or a `?` prefix admits null, and each intersection arm's other members.
     *
     * @param  ReflectionClass<object>  $context
     * @return DocblockArms
     */
    public static function docblockArms(string $type, ReflectionClass $context, string $staticClass, string $selfClass): array
    {
        $file = $context->getFileName();
        $arms = [];
        $named = true;
        $nullable = false;

        if ($file !== false) {
            DependencyRecorder::record($file);
        }

        foreach (LaravelTsPublish::splitPhpDocUnionType($type) as $part) {
            $members = self::intersectionMembers($part);

            if ($members === null) {
                // Generic arguments never change the class, but text after the closing `>`, such as `[]`, does.
                if (preg_match('/^\??([\\\\\w$]+)(?:<.*>)?$/s', $part, $match) !== 1) {
                    $named = false;

                    continue;
                }

                $isNull = strtolower($match[1]) === 'null';
                $nullable = $nullable || $isNull || str_starts_with($part, '?');
                $members = $isNull ? [] : [$match[1]];
            }

            $classes = self::memberClasses($members, $context, $staticClass, $selfClass);
            $named = $named && $classes !== null;
            $arms[] = $classes ?? [];
        }

        $joined = self::joined($arms);

        return ['classes' => $joined['classes'], 'named' => $named, 'nullable' => $nullable, 'intersections' => $joined['intersections']];
    }

    /**
     * The one class a native type names, `static` read as the class the value was read through and `self` as the class
     * that declares the type; null for a builtin, a union or an intersection.
     *
     * @return class-string|null
     */
    public static function singleClass(?ReflectionType $type, string $staticClass, string $selfClass): ?string
    {
        if (! $type instanceof ReflectionNamedType || ($type->isBuiltin() && $type->getName() !== 'static')) {
            return null;
        }

        $class = match ($type->getName()) {
            'static' => $staticClass,
            'self' => $selfClass,
            default => $type->getName(),
        };

        return self::isValueClass($class) ? $class : null;
    }

    /**
     * The arguments a generic type such as `JsonResource<Post>` or `Attribute<string, never>` spells, trimmed, split at
     * its top-level commas; null when the type has none.
     *
     * @return list<string>|null
     */
    public static function genericArguments(string $type): ?array
    {
        return preg_match('/^[\\\\\w]+\s*<(.+)>$/s', trim($type), $match) === 1
            ? LaravelTsPublish::splitAtTopLevelCommas($match[1])
            : null;
    }

    /**
     * The key and element a docblock spells for its one collection, as `Collection<string, Comment>` or
     * `?Collection<Comment>` does, a `null` arm aside: the key is null where the generic names only the element, and
     * the element is the concrete model it names, else null; null for any other spelling.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports resolve the element
     * @return CollectionGeneric|null
     */
    public static function collectionGeneric(string $phpDocType, ReflectionClass $context): ?array
    {
        $arms = array_values(array_filter(
            LaravelTsPublish::splitPhpDocUnionType($phpDocType),
            fn (string $arm): bool => strtolower($arm) !== 'null',
        ));
        $arguments = count($arms) === 1
            ? self::genericArguments(str_starts_with($arms[0], '?') ? substr($arms[0], 1) : $arms[0])
            : null;
        [$key, $element] = match (count($arguments ?? [])) {
            1 => ['', $arguments[0]],
            2 => [$arguments[0], $arguments[1]],
            default => ['', null],
        };

        if ($element === null
            || ($key !== '' && preg_match(self::COLLECTION_KEY, $key) !== 1)
            || preg_match('/^[\\\\\w]+$/', $element) !== 1
        ) {
            return null;
        }

        $model = DocblockNames::resolve($element, $context);

        return ['key' => ReceiverType::keyKind($key), 'element' => ReceiverType::isConcreteModel($model) ? $model : null];
    }

    /**
     * The element type a docblock spells for its one array, as `list<X>`, `array<string, X>`, `iterable<X>` or `X[]`
     * does, a `null` arm or a `?` aside; null for any other spelling.
     */
    public static function listedElement(string $phpDocType): ?string
    {
        $arms = array_values(array_filter(
            LaravelTsPublish::splitPhpDocUnionType($phpDocType),
            fn (string $arm): bool => strtolower($arm) !== 'null',
        ));
        $arm = count($arms) === 1 ? ltrim($arms[0], '?') : '';

        if (str_ends_with($arm, '[]')) {
            $element = trim(substr($arm, 0, -2));

            return match (true) {
                $element === '' => null,
                TsTypeShape::isWrapped($element, '(', ')') => trim(substr($element, 1, -1)),
                default => $element,
            };
        }

        $listed = preg_match('/^([a-z-]+)\s*<(.+)>$/is', $arm, $match) === 1 && in_array(strtolower($match[1]), self::LIST_TYPES, true);
        $arguments = $listed ? LaravelTsPublish::splitAtTopLevelCommas($match[2]) : [];

        return in_array(count($arguments), [1, 2], true) ? $arguments[count($arguments) - 1] : null;
    }

    /**
     * Whether a name is a loadable class, interface or enum: a type a value can have, so never a trait.
     *
     * @phpstan-assert-if-true class-string $name
     */
    public static function isValueClass(string $name): bool
    {
        return DocblockNames::isValueClass($name);
    }

    /**
     * Whether a name is a loadable class, interface, trait or enum: anything a docblock may name.
     *
     * @phpstan-assert-if-true class-string $name
     */
    public static function isClassLike(string $name): bool
    {
        return DocblockNames::isClassLike($name);
    }

    /**
     * The classes a union's arms name, each arm's primary member, and the other members every arm naming that primary
     * shares, as `(A&B)|(A&C)` holds an A and no more; an empty arm, a `null` one, names nothing.
     *
     * @param  list<list<class-string>>  $arms  each arm's members, one for an arm that is no intersection
     * @return JoinedArms
     */
    private static function joined(array $arms): array
    {
        $shared = [];

        foreach ($arms as $members) {
            if ($members === []) {
                continue;
            }

            $primary = array_find($members, fn (string $member): bool => ! interface_exists($member)) ?? $members[0];
            $others = array_values(array_filter($members, fn (string $member): bool => ! is_a($primary, $member, true)));
            $shared[$primary] = isset($shared[$primary]) ? array_values(array_intersect($shared[$primary], $others)) : $others;
        }

        $intersections = [];

        foreach ($shared as $primary => $others) {
            if ($others !== []) {
                $intersections[$primary] = $others;
            }
        }

        return ['classes' => array_keys($shared), 'intersections' => $intersections];
    }

    /**
     * The members of an intersection arm, as `A&B` or the DNF group `(A&B)` spells them; null for any other arm.
     *
     * @return list<string>|null
     */
    private static function intersectionMembers(string $part): ?array
    {
        $members = TypeScanner::split($part, ['&'], ScanDialect::PhpDoc);

        if (count($members) === 1 && str_starts_with($part, '(') && str_ends_with($part, ')')) {
            $members = TypeScanner::split(substr($part, 1, -1), ['&'], ScanDialect::PhpDoc);
        }

        return count($members) > 1 ? $members : null;
    }

    /**
     * The class each member of an arm names, its generic arguments aside; null when one names no loadable class.
     *
     * @param  list<string>  $members
     * @param  ReflectionClass<object>  $context
     * @return list<class-string>|null
     */
    private static function memberClasses(array $members, ReflectionClass $context, string $staticClass, string $selfClass): ?array
    {
        $classes = [];

        foreach ($members as $member) {
            $class = preg_match('/^([\\\\\w$]+)(?:<.*>)?$/s', $member, $match) === 1
                ? self::namedClass($match[1], $context, $staticClass, $selfClass)
                : null;

            if ($class === null) {
                return null;
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * The loadable class a bare docblock name spells, resolved against the declaring file's imports; null for a type
     * keyword or a name no class loads.
     *
     * @param  ReflectionClass<object>  $context
     * @return class-string|null
     */
    private static function namedClass(string $name, ReflectionClass $context, string $staticClass, string $selfClass): ?string
    {
        if (in_array(strtolower($name), self::RESERVED_TYPES, true)) {
            return null;
        }

        $class = match ($name) {
            '$this', 'static' => $staticClass,
            'self' => $selfClass,
            default => DocblockNames::resolve($name, $context),
        };

        return self::isValueClass($class) ? $class : null;
    }

    /**
     * The class one named native type names, `parent` read as the parent of the class `self` names; null for a builtin.
     *
     * @return class-string|null
     */
    private static function nativeClass(ReflectionNamedType $type, string $staticClass, string $selfClass): ?string
    {
        $name = $type->getName();
        $class = match ($name) {
            'static' => $staticClass,
            'self' => $selfClass,
            'parent' => self::isValueClass($selfClass) ? (string) get_parent_class($selfClass) : '',
            default => $name,
        };

        return ($type->isBuiltin() && $name !== 'static') || ! self::isValueClass($class) ? null : $class;
    }

    /**
     * Whether a method's native, tentative or `@return` declaration names `static` or `$this`, with generic arguments
     * where $generics allows them, and else only null; read once a run.
     */
    private static function declaresOwnReturn(string $class, string $method, bool $generics): bool
    {
        return resolve(AnalysisMemo::class)->remember(
            ($generics ? 'returns-own-class:' : 'returns-static:').$class.'::'.$method,
            static function () use ($class, $method, $generics): bool {
                $reflection = new ReflectionMethod($class, $method);
                $type = self::reflectedReturnType($reflection);

                if ($type !== null) {
                    return $type instanceof ReflectionNamedType && $type->getName() === 'static';
                }

                $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $reflection->getDocComment()) ?? '';
                $arms = array_map(fn (string $arm): string => ltrim($arm, '?'), LaravelTsPublish::splitPhpDocUnionType($docblock));
                $returned = array_values(array_diff($arms, ['null']));
                $pattern = $generics ? '/^(?:static|\$this)(?:<.*>)?$/s' : '/^(?:static|\$this)$/';

                return $returned !== [] && array_all($returned, fn (string $arm): bool => preg_match($pattern, $arm) === 1);
            },
        );
    }
}
