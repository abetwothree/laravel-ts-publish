<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;

/**
 * Shared import conflict resolution helpers for transformers.
 */
trait ResolvesImportConflicts
{
    /** @var array<string, string> FQCN => aliased TypeScript name (only for conflicting imports) */
    protected array $importAliases = [];

    /** @var array<string, string> FQCN => aliased TypeScript const name (only for conflicting imports) */
    protected array $constImportAliases = [];

    /**
     * The name a file gives an imported type: the alias the registries chose for it, else the type's own name.
     */
    protected function localImportName(string $fqcn, string $typeName): string
    {
        return $this->importAliases[$fqcn] ?? $typeName;
    }

    /**
     * Format an import name, applying "OriginalName as Alias" syntax when aliased.
     */
    protected function formatImportName(string $fqcn, string $typeName): string
    {
        $localName = $this->localImportName($fqcn, $typeName);

        if ($localName !== $typeName) {
            return $typeName.' as '.$localName;
        }

        return $typeName;
    }

    /**
     * Format a const import name, applying "OriginalName as Alias" syntax when aliased.
     */
    protected function formatConstImportName(string $fqcn): string
    {
        $constName = $this->enumConstMap[$fqcn];
        $alias = $this->constImportAliases[$fqcn] ?? null;

        if ($alias !== null && $alias !== $constName) {
            return $constName.' as '.$alias;
        }

        return $constName;
    }

    /**
     * Map each imported type, under the name this file gives it, to its globally-qualified name.
     *
     * A bare name is ambiguous once two namespaces publish it, so the globals file reads every name through this
     * map, as the class's own file reads it through its imports.
     *
     * @param  array<string, string>  ...$fqcnMaps  FQCN => unaliased TypeScript type name
     * @return array<string, string> typeName|alias => 'dot.separated.namespace.TypeName'
     */
    protected function qualifiedImportNames(array ...$fqcnMaps): array
    {
        $map = [];

        foreach ($fqcnMaps as $fqcnMap) {
            foreach ($fqcnMap as $fqcn => $typeName) {
                $map[$this->localImportName($fqcn, $typeName)] = TsNaming::globalNamespace($fqcn).'.'.$typeName;
            }
        }

        return $map;
    }

    /**
     * Rewrite property type references to use aliased names; each transformer implements this
     * against its own property shape.
     */
    abstract protected function rewriteTypeReferences(): void;

    /**
     * Resolve a type registry and its sibling const registry, then apply both, the types first.
     *
     * A type import and a const import are both local names in the file, so a const steps aside for every name a type
     * took: enum `Role`'s type and enum `RoleType`'s const would otherwise both be `RoleType`.
     *
     * @param  array<string, string>  $typeNames  FQCN => unaliased TypeScript type name
     */
    protected function applyImportNameRegistries(
        ImportNameRegistry $types,
        ImportNameRegistry $consts,
        array $typeNames,
    ): void {
        $resolved = $types->resolve();
        $consts->reserveMany(...array_values($resolved));

        $this->applyResolvedImportNames($resolved, $typeNames, $consts->resolve());
    }

    /**
     * Apply the registries' resolved names to the alias maps, then rewrite type references when a type was aliased.
     *
     * A const is aliased on its own account: its name can be taken while its enum's type name is free.
     *
     * @param  array<string, string>  $resolved  FQCN => final local type name
     * @param  array<string, string>  $typeNames  FQCN => unaliased TypeScript type name
     * @param  array<string, string>  $constNames  FQCN => final local const name (empty when the caller has no const imports)
     */
    protected function applyResolvedImportNames(array $resolved, array $typeNames, array $constNames = []): void
    {
        foreach ($resolved as $fqcn => $localName) {
            if (isset($typeNames[$fqcn]) && $localName !== $typeNames[$fqcn]) {
                $this->importAliases[$fqcn] = $localName;
            }
        }

        foreach ($constNames as $fqcn => $localName) {
            if ($localName !== $this->enumConstMap[$fqcn]) {
                $this->constImportAliases[$fqcn] = $localName;
            }
        }

        if ($this->importAliases !== []) {
            $this->rewriteTypeReferences();
        }
    }
}
