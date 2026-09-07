<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisResult;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The `@internal` boundary, machine-checked. Tagging one class buys nothing if a public signature
 * hands the same type out anyway, or a public subclass extends it — those are the shapes that made
 * two user-facing docs disagree about what the engine's supported surface even is.
 */

/**
 * Every class, interface, trait and enum this package declares, keyed by FQCN.
 *
 * @return array<string, ReflectionClass<object>>
 */
function packageReflections(): array
{
    static $reflections = null;

    if ($reflections !== null) {
        return $reflections;
    }

    $source = dirname(__DIR__, 2).'/src';
    $reflections = [];

    foreach (new Finder()->files()->in($source)->name('*.php') as $file) {
        /** @var SplFileInfo $file */
        $relative = substr($file->getRelativePathname(), 0, -4);
        $fqcn = 'AbeTwoThree\\LaravelTsPublish\\'.str_replace('/', '\\', $relative);

        if (class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn)) {
            $reflections[$fqcn] = new ReflectionClass($fqcn);
        }
    }

    return $reflections;
}

/**
 * Whether a docblock carries `@internal` as its own tag line, not merely as prose naming the tag.
 */
function isTaggedInternal(string|false|null $docComment): bool
{
    return preg_match('/^\s*\*\s*@internal\b/m', $docComment === false || $docComment === null ? '' : $docComment) === 1;
}

it('tags every class under src/Ast except the two the engine exposes', function () {
    $public = [AstEngine::class, AnalysisResult::class];
    $untagged = [];

    foreach (packageReflections() as $fqcn => $reflection) {
        if (! str_starts_with($fqcn, 'AbeTwoThree\\LaravelTsPublish\\Ast\\') || in_array($fqcn, $public, true)) {
            continue;
        }

        if (! isTaggedInternal($reflection->getDocComment())) {
            $untagged[] = $fqcn;
        }
    }

    expect($untagged)->toBe([]);

    // The two exceptions are deliberate, so a blanket re-tag has to be an explicit decision here too.
    foreach ($public as $fqcn) {
        expect(isTaggedInternal(new ReflectionClass($fqcn)->getDocComment()))->toBeFalse();
    }
});

it('leaves no public class extending an internal one', function () {
    $violations = [];

    foreach (packageReflections() as $fqcn => $reflection) {
        $parent = $reflection->getParentClass();

        if ($parent === false || isTaggedInternal($reflection->getDocComment())) {
            continue;
        }

        if (isTaggedInternal($parent->getDocComment())) {
            $violations[] = $fqcn.' extends '.$parent->getName();
        }
    }

    expect($violations)->toBe([]);
});

it('never names an internal type in a public signature', function () {
    $violations = [];

    foreach (packageReflections() as $fqcn => $reflection) {
        if (isTaggedInternal($reflection->getDocComment())) {
            continue;
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $fqcn || isTaggedInternal($method->getDocComment())) {
                continue;
            }

            $types = [$method->getReturnType(), ...array_map(
                fn (ReflectionParameter $parameter): ?ReflectionType => $parameter->getType(),
                $method->getParameters(),
            )];

            foreach ($types as $type) {
                foreach (signatureClassNames($type) as $name) {
                    $named = packageReflections()[$name] ?? null;

                    if ($named !== null && isTaggedInternal($named->getDocComment())) {
                        $violations[] = $fqcn.'::'.$method->getName().'() names '.$name;
                    }
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

/**
 * The class names one reflected signature type mentions, flattening unions and intersections.
 *
 * @return list<string>
 */
function signatureClassNames(?ReflectionType $type): array
{
    if ($type instanceof ReflectionNamedType) {
        return $type->isBuiltin() ? [] : [$type->getName()];
    }

    if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
        return array_merge(...array_map(signatureClassNames(...), $type->getTypes()));
    }

    return [];
}
