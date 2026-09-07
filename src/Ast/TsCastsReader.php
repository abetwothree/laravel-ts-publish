<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
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

        foreach ($attributes as $attribute) {
            $merged = array_merge($merged, $attribute->types);
        }

        $overrides = [];
        $importPaths = [];
        $importMap = [];
        $optionalOverrides = [];

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

                if (isset($value['optional'])) {
                    $optionalOverrides[$key] = $value['optional'];
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
}
