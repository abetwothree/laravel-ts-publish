<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;

/**
 * Carry a ValueExpressionResult's type and FQCN channels into a TypeScriptTypeInfo: the inverse of
 * `ReflectedTypeAcceptor::accept()`, reading only the five channels it writes, so no resource channel. See the Import
 * dispatch rules table in docs/components/resource-ast-analyzer.md.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 *
 * @internal
 */
final class ResultTypeInfoBridge
{
    /**
     * Convert one engine result into the model engine's type info, with the imports its class and enum channels name.
     *
     * @param  ValueExpressionResult  $result
     * @return TypeScriptTypeInfo
     */
    public function toTypeInfo(array $result): array
    {
        $fqcns = array_values(array_unique(array_filter([
            $result['directEnumFqcn'] ?? null,
            $result['modelFqcn'] ?? null,
            ...($result['embeddedEnumFqcns'] ?? []),
            ...($result['embeddedModelFqcns'] ?? []),
        ])));

        $info = $fqcns === []
            ? LaravelTsPublish::emptyTypeScriptInfo()
            : LaravelTsPublish::mergeTypeScriptInfos(array_map(
                fn (string $fqcn): array => LaravelTsPublish::toTsType($fqcn),
                $fqcns,
            ));

        $info['type'] = $result['type'];

        // Union, not replace: the merged copy carries a #[TsType] channel's own import, the engine result
        // carries the ones its expression named, and dropping either emits a token with no import.
        foreach ($result['customImports'] ?? [] as $path => $names) {
            $info['customImports'][$path] = array_values(array_unique([...$info['customImports'][$path] ?? [], ...$names]));
        }

        return $info;
    }
}
