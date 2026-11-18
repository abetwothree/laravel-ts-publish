<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Dtos\TsResourceDto;

/**
 * The reserved row a resource's top level holds each group of its keys in, one arm per value of the key the others
 * follow, and how a printer splits those rows from the keys every arm shares.
 *
 * @phpstan-import-type PropertiesList from TsResourceDto
 *
 * @internal
 */
final class VariantRows
{
    /** Leads every variant row's name: no PHP key the analysis publishes starts with a NUL byte. */
    private const string PREFIX = "\0variants:";

    /**
     * The name of the row a group of keys that follow a discriminant is published under.
     */
    public static function key(string $discriminant): string
    {
        return self::PREFIX.$discriminant;
    }

    /**
     * Whether a row's name is a variant row's.
     */
    public static function isKey(string $name): bool
    {
        return str_starts_with($name, self::PREFIX);
    }

    /**
     * A resource's rows split into the keys every arm shares and each variant row's union, in row order.
     *
     * @param  PropertiesList  $properties
     * @return array{0: PropertiesList, 1: list<string>}
     */
    public static function split(array $properties): array
    {
        $shared = [];
        $variants = [];

        foreach ($properties as $name => $property) {
            if (self::isKey((string) $name)) {
                $variants[] = $property['type'];
            } else {
                $shared[$name] = $property;
            }
        }

        return [$shared, $variants];
    }

    /**
     * The top-level arms of a variant row's union, each one object a value of its discriminant sends.
     *
     * @return list<string>
     */
    public static function arms(string $union): array
    {
        return TsTypeShape::splitTopLevel($union, ['|']);
    }
}
