<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;

/**
 * @phpstan-type TsCastsUnpacked = array{
 *     overrides: array<string, string>,
 *     importPaths: array<string, string>,
 *     importMap: array<string, list<string>>,
 *     optionalOverrides: array<string, bool>,
 * }
 *
 * @internal
 */
class TsCastsReader
{
    /**
     * Unpack already-collected #[TsCasts] instances into every view a call site needs.
     *
     * Later attributes win over earlier ones on a shared key, so callers must pass instances
     * in their own precedence order (e.g. class-level before method-level).
     *
     * @param  list<TsCasts>  $attributes
     * @return TsCastsUnpacked
     */
    public function unpack(array $attributes): array
    {
        $merged = [];
        $optionalOverrides = [];

        // array_merge() would renumber a numeric key, which PHP stores as an int even when written '42'.
        foreach ($attributes as $attribute) {
            $merged = array_replace($merged, $attribute->types);

            // A later entry that says nothing about optional keeps an earlier attribute's flag.
            foreach ($attribute->types as $key => $value) {
                if (is_array($value) && isset($value['optional'])) {
                    $optionalOverrides[$key] = $value['optional'];
                }
            }
        }

        $overrides = [];
        $importPaths = [];
        $importMap = [];

        foreach ($merged as $key => $value) {
            if (is_array($value)) {
                /** @var array{type: string, import?: string, optional?: bool} $value */
                $overrides[$key] = $value['type'];

                if (isset($value['import'])) {
                    $importPaths[$key] = $value['import'];

                    foreach (TsTypeString::extractImportableTypes($value['type']) as $typeName) {
                        $importMap[$value['import']][] = $typeName;
                    }
                }
            } else {
                $overrides[$key] = $value;
            }
        }

        return [
            'overrides' => $overrides,
            'importPaths' => $importPaths,
            'importMap' => $importMap,
            'optionalOverrides' => $optionalOverrides,
        ];
    }

    /**
     * The published key each cast key of these instances retypes, as JsEmitter::castTargets() decides it for each
     * instance alone; a later instance's claim on a key then outranks an earlier one's, whatever either spelling.
     * The $castKeys no instance names, such as one a transformer subclass injects, decide last, as one more location.
     *
     * @param  list<TsCasts>  $attributes  in the precedence order unpack() takes
     * @param  array<array-key, int|string>  $keys  the keys the casts are laid over
     * @param  list<string>  $castKeys  every cast key the caller holds
     * @return array<string, string|null>
     */
    public function castTargets(array $attributes, array $keys, array $castKeys = []): array
    {
        $locations = array_map(fn (TsCasts $attribute): array => array_keys($attribute->types), $attributes);
        $locations[] = array_values(array_diff($castKeys, ...$locations));
        $targets = [];

        foreach ($locations as $location) {
            foreach (JsEmitter::castTargets($location, $keys) as $castKey => $target) {
                if ($target !== null) {
                    foreach (array_keys($targets, $target, true) as $earlier) {
                        $targets[$earlier] = null;
                    }
                }

                $targets[$castKey] = $target;
            }
        }

        return $targets;
    }
}
