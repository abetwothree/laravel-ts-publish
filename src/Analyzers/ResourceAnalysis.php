<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;

/**
 * Holds the result of AST analysis of a resource's toArray() method.
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
 * @phpstan-type ClassMapType = array<string, class-string>
 * @phpstan-type ImportMapType = TypesImportMap
 */
class ResourceAnalysis
{
    /**
     * @param  ResourcePropertyInfoList  $properties
     * @param  ClassMapType  $enumResources  property name => enum FQCN (via EnumResource::make)
     * @param  ClassMapType  $nestedResources  property name => resource FQCN
     * @param  ImportMapType  $customImports  import path => list of type names
     * @param  ClassMapType  $directEnumFqcns  property name => enum FQCN (via direct $this->prop access)
     * @param  ClassMapType  $modelFqcns  property name => model FQCN (from bare whenLoaded)
     */
    public function __construct(
        public array $properties = [],
        public array $enumResources = [],
        public array $nestedResources = [],
        public array $customImports = [],
        public array $directEnumFqcns = [],
        public array $modelFqcns = [],
    ) {}
}
