<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Values\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * The classes a declaration names: a method's native return type or `@return` docblock, any native type, and a
 * docblock union's class arms. The receiver resolver reads declarations through it, and model inspection reads a
 * relation method's return here without the resolver.
 *
 * @phpstan-type DocblockArms array{classes: list<class-string>, named: bool, nullable: bool}
 *
 * @internal
 */
final class DeclaredClasses
{
    /** Type keywords PHP never lets a class or an import alias take, so an arm naming one is no class in any file. */
    private const array RESERVED_TYPES = [
        'array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'object', 'string', 'true',
        'void',
    ];

    /**
     * The classes a method returns: its native class type, else its `@return` docblock; null for any arm not a class.
     *
     * `static` and `$this` name the receiver class, or the caller's runtime class where it passes one; `self` names the
     * class that declares the method. Visibility is the caller's concern.
     *
     * @return non-empty-list<class-string>|null
     */
    public static function returnClasses(string $class, string $method, ?string $staticClass = null): ?array
    {
        DependencyRecorder::recordClass($class);

        if (! method_exists($class, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($class, $method);
        $declaring = $reflection->getDeclaringClass()->getName();

        if ($reflection->hasReturnType()) {
            return self::nativeClasses($reflection->getReturnType(), $staticClass ?? $class, $declaring);
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $reflection->getDocComment());

        return $docblock === null
            ? null
            : self::docblockClasses($docblock, LaravelTsPublish::methodDeclaringFileClass($reflection), $staticClass ?? $class, $declaring);
    }

    /**
     * Whether a method declares it returns `static` or `$this` and nothing else but null, once a run per class and
     * method.
     */
    public static function returnsStatic(string $class, string $method): bool
    {
        return resolve(AnalysisMemo::class)->remember(
            'returns-static:'.$class.'::'.$method,
            static fn (): bool => self::declaresStaticReturn($class, $method),
        );
    }

    /**
     * The classes a native type names, `parent` read as the parent of the class `self` names; null when it is absent,
     * an intersection, or has any builtin arm but `null`.
     *
     * @return non-empty-list<class-string>|null
     */
    public static function nativeClasses(?ReflectionType $type, string $staticClass, string $selfClass): ?array
    {
        $members = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        $classes = [];

        foreach ($members as $member) {
            if (! $member instanceof ReflectionNamedType) {
                return null;
            }

            $name = $member->getName();

            if ($name === 'null') {
                continue;
            }

            $class = match ($name) {
                'static' => $staticClass,
                'self' => $selfClass,
                'parent' => self::isClassLike($selfClass) ? (string) get_parent_class($selfClass) : '',
                default => $name,
            };

            if (($member->isBuiltin() && $name !== 'static') || ! self::isClassLike($class)) {
                return null;
            }

            $classes[] = $class;
        }

        return $classes === [] ? null : array_values(array_unique($classes));
    }

    /**
     * The classes a docblock union names, resolved against the declaring file's imports; null for any arm not a class.
     *
     * @param  ReflectionClass<object>  $context
     * @return non-empty-list<class-string>|null
     */
    public static function docblockClasses(string $type, ReflectionClass $context, string $staticClass, string $selfClass): ?array
    {
        $arms = self::docblockArms($type, $context, $staticClass, $selfClass);

        return $arms['named'] && $arms['classes'] !== [] ? $arms['classes'] : null;
    }

    /**
     * The class arms a docblock union names, resolved against the declaring file's imports, whether every arm but
     * `null` names one, and whether a `null` arm or a `?` prefix admits null.
     *
     * @param  ReflectionClass<object>  $context
     * @return DocblockArms
     */
    public static function docblockArms(string $type, ReflectionClass $context, string $staticClass, string $selfClass): array
    {
        $file = $context->getFileName();
        // Most docblocks name only scalars, so the import map is read for the first arm that needs it.
        $useMap = null;
        $classes = [];
        $named = true;
        $nullable = false;

        if ($file !== false) {
            DependencyRecorder::record($file);
        }

        foreach (LaravelTsPublish::splitPhpDocUnionType($type) as $part) {
            // Generic arguments never change the class, but text after the closing `>`, such as `[]`, does.
            if (! preg_match('/^\??([\\\\\w$]+)(?:<.*>)?$/s', $part, $match)) {
                $named = false;

                continue;
            }

            $name = $match[1];
            $nullable = $nullable || str_starts_with($part, '?');

            if (strtolower($name) === 'null') {
                $nullable = true;

                continue;
            }

            if (in_array(strtolower($name), self::RESERVED_TYPES, true)) {
                $named = false;

                continue;
            }

            $class = match ($name) {
                '$this', 'static' => $staticClass,
                'self' => $selfClass,
                default => LaravelTsPublish::resolveDocblockTypeName(
                    $name,
                    $useMap ??= LaravelTsPublish::parseFileUseStatements($context),
                    $context->getNamespaceName(),
                ),
            };

            if (self::isClassLike($class)) {
                $classes[] = $class;
            } else {
                $named = false;
            }
        }

        return ['classes' => array_values(array_unique($classes)), 'named' => $named, 'nullable' => $nullable];
    }

    /**
     * Whether a name is a loadable class, interface, or enum.
     *
     * @phpstan-assert-if-true class-string $name
     */
    public static function isClassLike(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || enum_exists($name);
    }

    /**
     * Whether a method's declaration, native or `@return`, names `static` or `$this` and nothing else but null.
     */
    private static function declaresStaticReturn(string $class, string $method): bool
    {
        $reflection = new ReflectionMethod($class, $method);
        $type = $reflection->getReturnType();

        if ($type !== null) {
            return $type instanceof ReflectionNamedType && $type->getName() === 'static';
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $reflection->getDocComment()) ?? '';
        $arms = array_map(fn (string $arm): string => ltrim($arm, '?'), LaravelTsPublish::splitPhpDocUnionType($docblock));
        $returned = array_diff($arms, ['null']);

        return $returned !== [] && array_diff($returned, ['static', '$this']) === [];
    }
}
