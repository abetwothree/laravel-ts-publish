<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;

/**
 * Build an inline TypeScript object type for a filtered subset of a related model's members.
 *
 * RelationFilterHandler is the only production caller. ResolvesModelTypes still composes this trait
 * so the analyzer keeps inheriting the method: ResourceAstAnalyzerTest probes it through two
 * anonymous subclasses, which is the only coverage the except-branch column rule has.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @internal
 */
trait ResolvesFilteredRelationTypes
{
    /**
     * Resolve an inline TypeScript type for a filtered subset of a related model's attributes and relations.
     *
     * Used when a resource accesses `$this->relation->only([...])` or `->except([...])`.
     *
     * @param  class-string  $relatedModelClass
     * @param  list<string>  $keys
     * @return array{type: string, enumFqcns: list<class-string>, modelFqcns: list<class-string>, customImports: TypesImportMap}
     */
    protected function resolveFilteredRelationType(
        string $relatedModelClass,
        array $keys,
        bool $include,
    ): array {
        $result = ['type' => 'unknown', 'enumFqcns' => [], 'modelFqcns' => [], 'customImports' => []];
        $resolver = resolve(ModelAttributeResolver::class);

        $relatedAttributes = $resolver->getAttributes($relatedModelClass);
        $relatedRelations = $resolver->getRelations($relatedModelClass);

        if ($relatedAttributes === null || $relatedRelations === null) {
            return $result; // @codeCoverageIgnore
        }

        if ($include) {
            $resolveKeys = $keys;
        } else {
            // HasAttributes::except() iterates getAttributes() only — never $this->relations, and never a
            // get-only accessor, which mergeAttributeFromAttributeCasts() refuses to merge back. Columns only.
            $excludeHidden = $resolver->excludeHiddenAttributes();
            $dbColumns = $resolver->databaseColumnNames($relatedModelClass);

            $attrNames = $relatedAttributes
                ->reject(fn (array $attr): bool => $excludeHidden && $attr['hidden'])
                ->pluck('name')
                ->filter(fn (mixed $name): bool => in_array($name, $dbColumns, true))
                ->all();

            $resolveKeys = array_values(array_filter(
                $attrNames,
                fn (mixed $k) => ! in_array($k, $keys, true),
            ));
        }

        $parts = [];
        /** @var list<class-string> $collectedEnumFqcns */
        $collectedEnumFqcns = [];
        /** @var list<class-string> $collectedModelFqcns */
        $collectedModelFqcns = [];
        /** @var TypesImportMap $collectedCustomImports */
        $collectedCustomImports = [];

        /** @var list<string> $resolveKeys */
        foreach ($resolveKeys as $key) {
            $attr = $relatedAttributes->firstWhere('name', $key);

            if ($attr !== null) {
                $tsInfo = $resolver->resolveAttribute($relatedModelClass, $key);

                // The except branch yields columns now, so in practice this gate is only()'s: a write-only
                // mutator with no getter and no docblock Get has no shape to emit, unlike a getter-backed one.
                if ($tsInfo['type'] !== 'unknown' || ! $resolver->isOmittedMutator($relatedModelClass, $key)) {
                    $parts[] = $key.': '.$tsInfo['type'];

                    /** @var list<class-string> $enumFqcns */
                    $enumFqcns = $tsInfo['enumFqcns'];
                    array_push($collectedEnumFqcns, ...$enumFqcns);

                    // Sibling of the enumFqcns collection above: an inlined attribute can itself
                    // reference another model or a #[TsType(import:)] alias, both needed to compile.
                    /** @var list<class-string> $classFqcns */
                    $classFqcns = $tsInfo['classFqcns'];
                    array_push($collectedModelFqcns, ...$classFqcns);

                    foreach ($tsInfo['customImports'] as $path => $names) {
                        $collectedCustomImports[$path] = [...($collectedCustomImports[$path] ?? []), ...$names];
                    }
                }

                continue;
            }

            // Relation
            $relationInfo = $resolver->resolveRelation($relatedModelClass, $key);

            if ($relationInfo['type'] !== 'unknown') {
                $parts[] = $key.': '.$relationInfo['type'];

                if ($relationInfo['modelFqcn'] !== null) {
                    /** @var class-string $relatedFqcn */
                    $relatedFqcn = $relationInfo['modelFqcn'];
                    $collectedModelFqcns[] = $relatedFqcn;
                }

                array_push($collectedModelFqcns, ...$relationInfo['morphFqcns']);
            }
        }

        $inlineType = $parts === [] ? 'unknown' : '{ '.implode('; ', $parts).' }';

        return [
            ...$result,
            'type' => $inlineType,
            'enumFqcns' => $collectedEnumFqcns,
            'modelFqcns' => $collectedModelFqcns,
            'customImports' => $collectedCustomImports,
        ];
    }
}
