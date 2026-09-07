<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use Illuminate\Database\Eloquent\Model;

/**
 * Accept a reflected TypeScriptTypeInfo as a ValueExpressionResult, or null when any referenced
 * type can't be imported.
 *
 * Implements "a type token never outruns its import": a non-Model class token has no published
 * file to import, so its presence rejects the whole result. See the Import dispatch rules table
 * in docs/components/resource-ast-analyzer.md.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 *
 * @internal
 */
final class ReflectedTypeAcceptor
{
    /**
     * @param  TypeScriptTypeInfo  $tsInfo
     * @return ValueExpressionResult|null
     */
    public function accept(array $tsInfo): ?array
    {
        if (in_array($tsInfo['type'], ['unknown', 'unknown | null', 'void', 'never', ''], true)) {
            return null;
        }

        foreach ($tsInfo['classFqcns'] as $fqcn) {
            if (! is_a($fqcn, Model::class, true)) {
                return null;
            }
        }

        $result = [...ValueResult::unknown(), 'type' => $tsInfo['type'], 'optional' => false];

        if (count($tsInfo['enumFqcns']) === 1 && $tsInfo['classFqcns'] === []) {
            $result['directEnumFqcn'] = $tsInfo['enumFqcns'][0];
        } elseif ($tsInfo['enumFqcns'] !== []) {
            $result['embeddedEnumFqcns'] = $tsInfo['enumFqcns'];
        }

        if (count($tsInfo['classFqcns']) === 1 && $tsInfo['enumFqcns'] === []) {
            /** @var class-string<Model> $modelFqcn */
            $modelFqcn = $tsInfo['classFqcns'][0];
            $result['modelFqcn'] = $modelFqcn;
        } elseif ($tsInfo['classFqcns'] !== []) {
            $result['embeddedModelFqcns'] = $tsInfo['classFqcns'];
        }

        if ($tsInfo['customImports'] !== []) {
            $result['customImports'] = $tsInfo['customImports'];
        }

        return $result;
    }
}
