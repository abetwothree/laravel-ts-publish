<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;

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
     * Format an import name, applying "OriginalName as Alias" syntax when aliased.
     */
    protected function formatImportName(string $fqcn, string $typeName): string
    {
        $alias = $this->importAliases[$fqcn] ?? null;

        if ($alias !== null && $alias !== $typeName) {
            return $typeName.' as '.$alias;
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
                $map[$this->importAliases[$fqcn] ?? $typeName] = TsNaming::globalNamespace($fqcn).'.'.$typeName;
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
     * Apply a registry's resolved names to the alias maps, then rewrite type references when
     * anything was actually aliased.
     *
     * @param  array<string, string>  $resolved  FQCN => final local type name
     * @param  array<string, string>  $typeNames  FQCN => unaliased TypeScript type name
     * @param  array<string, string>  $constNames  FQCN => final local const name (empty when the caller has no const imports)
     */
    protected function applyResolvedImportNames(array $resolved, array $typeNames, array $constNames = []): void
    {
        foreach ($resolved as $fqcn => $localName) {
            $typeName = $typeNames[$fqcn] ?? null;

            if ($typeName === null || $localName === $typeName) {
                continue;
            }

            $this->importAliases[$fqcn] = $localName;

            if (isset($constNames[$fqcn]) && $constNames[$fqcn] !== $this->enumConstMap[$fqcn]) {
                $this->constImportAliases[$fqcn] = $constNames[$fqcn];
            }
        }

        // A const registered independently of the type registry (e.g. an enum reached only
        // through an inline EnumResource wrap, never a bare type import) has no key in
        // $typeNames, so the loop above never reaches it. Apply those leftovers here.
        foreach ($constNames as $fqcn => $localName) {
            if (! isset($typeNames[$fqcn]) && $localName !== $this->enumConstMap[$fqcn]) {
                $this->constImportAliases[$fqcn] = $localName;
            }
        }

        if ($this->importAliases !== []) {
            $this->rewriteTypeReferences();
        }
    }
}
