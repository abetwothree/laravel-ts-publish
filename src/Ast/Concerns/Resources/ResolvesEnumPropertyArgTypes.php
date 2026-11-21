<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;

/**
 * Types a model attribute read through a resource, with every FQCN channel it carries and its `#[TsType]` imports.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type AttributeTypeInfo = array{
 *      type: string,
 *      enumFqcn: class-string|null,
 *      enumFqcns: list<class-string>,
 *      classFqcns: list<class-string>,
 *      classTokenFqcns?: list<class-string|null>,
 *      customImports: TypesImportMap,
 *      keepsKeys?: bool,
 *      typedByClass?: true
 * }
 *
 * @internal
 */
trait ResolvesEnumPropertyArgTypes
{
    /**
     * Resolve the TypeScript type, optional enum FQCN, class FQCNs and `#[TsType]` imports for a model attribute.
     *
     * Bypasses ResolvesModelTypes's cached-property gate, which this per-call handler never
     * populates — calls the ModelAttributeResolver singleton directly instead; it caches per FQCN.
     *
     * @param  bool  $casesAsLiterals  the scope's own flag where the attribute is read as a value; an `EnumResource`
     *                                 payload or a `->value` base needs the enum, so it reads cases as their enum
     * @return AttributeTypeInfo
     */
    protected function resolveModelAttributeTypeInfo(
        string $attributeName,
        AnalysisScope $scope,
        bool $casesAsLiterals = false,
    ): array {
        if ($scope->modelClass === null) {
            return ['type' => 'unknown', 'enumFqcn' => null, 'enumFqcns' => [], 'classFqcns' => [], 'customImports' => []];
        }

        $tsInfo = resolve(ModelAttributeResolver::class)
            ->resolveAttributeValue($scope->modelClass, $attributeName, $scope->carriesImports, $casesAsLiterals);

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        return [
            'type' => $tsInfo['type'],
            'enumFqcn' => $enumFqcn,
            'enumFqcns' => $tsInfo['enumFqcns'],
            'classFqcns' => $tsInfo['classFqcns'],
            // Present only where the type spells one name for two classes or an override spells a class's name; see
            // TypeScriptTypeInfo.
            ...(isset($tsInfo['classTokenFqcns']) ? ['classTokenFqcns' => $tsInfo['classTokenFqcns']] : []),
            'customImports' => $tsInfo['customImports'],
            ...(($tsInfo['keepsKeys'] ?? false) ? ['keepsKeys' => true] : []),
            ...(($tsInfo['typedByClass'] ?? false) ? ['typedByClass' => true] : []),
        ];
    }
}
