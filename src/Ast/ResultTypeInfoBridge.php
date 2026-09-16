<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;

/**
 * Carry a ValueExpressionResult's type and FQCN channels into a TypeScriptTypeInfo.
 *
 * The documented inverse of `ReflectedTypeAcceptor::accept()`: that class turns a reflected
 * TypeScriptTypeInfo into a ValueExpressionResult, this turns an engine result back into one. It
 * reads the same five channels ReflectedTypeAcceptor writes — `directEnumFqcn`, `modelFqcn`,
 * `embeddedEnumFqcns`, `embeddedModelFqcns` and `customImports` — not the other channels the
 * contract declares; see the Import dispatch rules table in docs/components/resource-ast-analyzer.md.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 *
 * @internal
 */
final class ResultTypeInfoBridge
{
    /**
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
