<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;

/**
 * Shared import conflict resolution helpers for transformers.
 *
 * @phpstan-import-type SiblingImportNames from ImportNameRegistry
 *
 * @phpstan-type TypedEntry array{type: string}
 */
trait ResolvesImportConflicts
{
    /** @var array<string, string> FQCN => aliased TypeScript name (only for conflicting imports) */
    protected array $importAliases = [];

    /** @var array<string, string> FQCN => aliased TypeScript const name (only for conflicting imports) */
    protected array $constImportAliases = [];

    /** @var SiblingImportNames each sibling name an FQCN is imported under, such as an enum's `…Kind` */
    protected array $siblingImportNames = [];

    /**
     * Rewrite property type references to use aliased names; each transformer implements this
     * against its own property shape.
     */
    abstract protected function rewriteTypeReferences(): void;

    /**
     * The name a file gives an imported type: the alias the registries chose for it, else the type's own name.
     */
    protected function localImportName(string $fqcn, string $typeName): string
    {
        return $this->importAliases[$fqcn] ?? $typeName;
    }

    /**
     * Every name a file gives an imported class: its type name's, then each sibling name's, such as an enum's `…Kind`.
     *
     * @return list<string>
     */
    protected function localImportNames(string $fqcn, string $typeName): array
    {
        return [$this->localImportName($fqcn, $typeName), ...array_values($this->siblingImportNames[$fqcn] ?? [])];
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
     * Format a sibling import name, such as an enum's `…Kind`, applying "Name as Local" syntax when aliased.
     */
    protected function formatSiblingImportName(string $name, string $local): string
    {
        return $local === $name ? $name : $name.' as '.$local;
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

                foreach ($this->siblingImportNames[$fqcn] ?? [] as $name => $local) {
                    $map[$local] = TsNaming::globalNamespace($fqcn).'.'.$name;
                }
            }
        }

        return $map;
    }

    /**
     * Map each sibling name the file aliased to its globally-qualified name, as a globalAliasMap() maps type aliases.
     *
     * @return array<string, string> alias => 'dot.separated.namespace.Name'
     */
    protected function siblingGlobalAliases(): array
    {
        $map = [];

        foreach ($this->siblingImportNames as $fqcn => $names) {
            foreach ($names as $name => $local) {
                if ($local !== $name) {
                    $map[$local] = TsNaming::globalNamespace($fqcn).'.'.$name;
                }
            }
        }

        return $map;
    }

    /**
     * Register beside its type name each enum's `…Kind` that an entry whose import channels carry the enum spells, so
     * the registry names the two alike and no `…Kind` the file never spells claims a name.
     *
     * @param  array<string, string>  $enumFqcnMap  FQCN => unaliased enum type name
     * @param  array<array-key, TypedEntry>  $entries  key => the entry, its type unaliased
     * @param  array<array-key, list<string>>  $queues  key => the FQCNs its import channels carry
     */
    protected function registerEnumKinds(ImportNameRegistry $registry, array $enumFqcnMap, array $entries, array $queues): void
    {
        foreach ($queues as $key => $fqcns) {
            foreach (array_unique($fqcns) as $fqcn) {
                if (! isset($enumFqcnMap[$fqcn], $entries[$key])) {
                    continue;
                }

                foreach (ValueResult::enumSiblingNames($fqcn) as $name) {
                    if (TsTypeString::typeNameOccursIn($name, $entries[$key]['type'])) {
                        $registry->registerSibling($fqcn, $name);
                    }
                }
            }
        }
    }

    /**
     * Resolve a type registry and its sibling const registry, then apply both, the types first.
     *
     * A type import and a const import are both local names in the file, so a const steps aside for every name a type
     * took: enum `Role`'s type and enum `RoleType`'s const would otherwise both be `RoleType`. Both step aside for the
     * names the file's custom imports bring, which the app wrote and no alias may rename.
     *
     * @param  array<string, string>  $typeNames  FQCN => unaliased TypeScript type name
     * @param  array<string, list<string>>  $customImports  import path => the names a `#[TsType]`, `#[TsCasts]` or
     *                                                      `#[TsExtends]` import brings into the file
     */
    protected function applyImportNameRegistries(
        ImportNameRegistry $types,
        ImportNameRegistry $consts,
        array $typeNames,
        array $customImports = [],
    ): void {
        $customNames = array_values(array_unique(array_merge(...array_values($customImports))));
        $types->reserveMany(...$customNames);
        $consts->reserveMany(...$customNames);

        $resolved = $types->resolve();
        $siblings = $types->resolveSiblings($resolved);
        $siblingLocals = array_merge([], ...array_values(array_map(array_values(...), $siblings)));
        $consts->reserveMany(...array_values($resolved), ...$siblingLocals);

        $this->applyResolvedImportNames($resolved, $typeNames, $consts->resolve(), $siblings);
    }

    /**
     * Apply the registries' resolved names to the alias maps, then rewrite type references when a type was aliased.
     *
     * A const is aliased on its own account: its name can be taken while its enum's type name is free.
     *
     * @param  array<string, string>  $resolved  FQCN => final local type name
     * @param  array<string, string>  $typeNames  FQCN => unaliased TypeScript type name
     * @param  array<string, string>  $constNames  FQCN => final local const name (empty when the caller has no const imports)
     * @param  SiblingImportNames  $siblings  each sibling name's final local name
     */
    protected function applyResolvedImportNames(
        array $resolved,
        array $typeNames,
        array $constNames = [],
        array $siblings = [],
    ): void {
        $this->siblingImportNames = $siblings;

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

        $siblingAliased = array_any(
            $siblings,
            static fn (array $names): bool => array_any($names, static fn (string $local, string $name): bool => $local !== $name),
        );

        if ($this->importAliases !== [] || $siblingAliased) {
            $this->rewriteTypeReferences();
        }
    }
}
