<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;

/**
 * What one class-and-method analysis hands a caller: the TypeScript properties, the `import type`
 * lines they need, and the value imports the `AsEnum<typeof X>` wrappers need.
 *
 * This and `AstEngine::analyze()` are the engine's whole public surface, so the property shape is
 * declared here rather than imported from the `@internal` DTO the engine builds it out of.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type ResourcePropertyInfo = array{
 *     name: string,
 *     type: string,
 *     optional: bool,
 *     description: string,
 * }
 * @phpstan-type ResourcePropertyInfoList = list<ResourcePropertyInfo>
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
