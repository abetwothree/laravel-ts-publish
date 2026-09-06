<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;

/**
 * What one class-and-method analysis hands a caller: the TypeScript properties, the `import type`
 * lines they need, and the value imports the `AsEnum<typeof X>` wrappers need.
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type ResourcePropertyInfoList from MethodAnalysis
 */
final readonly class AnalysisResult
{
    /**
     * @param  ResourcePropertyInfoList  $properties
     * @param  TypesImportMap  $typeImports
     * @param  TypesImportMap  $valueImports
     */
    public function __construct(
        public array $properties,
        public array $typeImports,
        public array $valueImports,
    ) {}
}
