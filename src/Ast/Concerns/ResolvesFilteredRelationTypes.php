<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Type a filtered subset of a model's members: `Pick<Model, …>` when every key is a published column, else an inline
 * shape (a member naming a token is `unknown` where the scope cannot import), and `Record<string, unknown>` for keys
 * read at runtime. ResolvesModelTypes still composes it so ResourceAstAnalyzerTest can probe the except-branch rule.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
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
     * @param  bool  $tokenMembersUnknown  spell a member whose type names a token `unknown`, for a scope that cannot
     *                                     import it, so the rest of the shape survives; an accessor member's getter
     *                                     is analyzed without imports too
     * @return array{type: string, enumFqcns: list<class-string>, modelFqcns: list<class-string>, customImports: TypesImportMap}
     */
    protected function resolveFilteredRelationType(
        string $relatedModelClass,
        array $keys,
        bool $include,
        bool $tokenMembersUnknown = false,
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
                $tsInfo = $resolver->resolveAttribute($relatedModelClass, $key, carriesImports: ! $tokenMembersUnknown);

                // The except branch yields columns now, so in practice this gate is only()'s: a write-only
                // mutator with no getter and no docblock Get has no shape to emit, unlike a getter-backed one.
                if ($tsInfo['type'] !== 'unknown' || ! $resolver->isOmittedMutator($relatedModelClass, $key)) {
                    if ($tokenMembersUnknown && TsTypeString::shapeValueHasUnimportableToken($tsInfo['type'])) {
                        $parts[] = $key.': unknown';

                        continue;
                    }

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
                if ($tokenMembersUnknown && TsTypeString::shapeValueHasUnimportableToken($relationInfo['type'])) {
                    $parts[] = $key.': unknown';

                    continue;
                }

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

    /**
     * A literal-key filter on one model: the `Pick<>` reference when every key is a published column, else the inline
     * shape, or null when the keys name nothing.
     *
     * @param  class-string<Model>  $modelFqcn
     * @param  list<string>  $keys
     * @param  bool  $carriesImports  false where the answer carries no import: the shape is inline, since a `Pick<>`
     *                                would drop the enclosing shape, and a member naming a token is `unknown`
     * @return ValueExpressionResult|null
     */
    protected function literalKeyFilterResult(string $modelFqcn, array $keys, bool $include, bool $carriesImports = true): ?array
    {
        // Every filter key is a plain DB column: reference the emitted model interface directly so its
        // #[TsCasts]/@property refinements stay authoritative instead of being re-derived and lost.
        $modelReference = $carriesImports ? $this->relationFilterModelReference($modelFqcn, $keys, $include) : null;

        if ($modelReference !== null) {
            return [...ValueResult::unknown(), 'type' => $modelReference, 'modelFqcn' => $modelFqcn];
        }

        $filtered = $this->resolveFilteredRelationType($modelFqcn, $keys, $include, tokenMembersUnknown: ! $carriesImports);

        if ($filtered['type'] === 'unknown') {
            return null;
        }

        return [
            ...ValueResult::unknown(),
            'type' => $filtered['type'],
            'embeddedEnumFqcns' => $filtered['enumFqcns'],
            'embeddedModelFqcns' => $filtered['modelFqcns'],
            'customImports' => $filtered['customImports'],
        ];
    }

    /**
     * The `Record<string, unknown>` a model filter returns for keys it cannot type, such as a runtime key list.
     *
     * @return ValueExpressionResult
     */
    protected function attributeRecordResult(bool $nullable): array
    {
        return [...ValueResult::unknown(), 'type' => $nullable ? 'Record<string, unknown> | null' : 'Record<string, unknown>'];
    }

    /**
     * Build a Pick<Model, …> reference when every filter key is a declared model column.
     *
     * Targets the bare model interface: except() iterates only $this->getAttributes(), so relations and
     * accessors never surface. Picks the complement, not Omit<>, to stay independent of the active template.
     *
     * @param  class-string<Model>  $modelFqcn
     * @param  list<string>  $keys
     */
    protected function relationFilterModelReference(string $modelFqcn, array $keys, bool $include): ?string
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $columns = $resolver->publishedColumnNames($modelFqcn);

        if ($columns === []) {
            return null; // @codeCoverageIgnore
        }

        foreach ($keys as $key) {
            if (! in_array($key, $columns, true)) {
                return null;
            }
        }

        $picked = $include ? $keys : array_values(array_diff($columns, $keys));

        if ($picked === []) {
            return 'Pick<'.class_basename($modelFqcn).', never>';
        }

        $quoted = implode(' | ', array_map(fn (string $k): string => "'".$k."'", $picked));

        return 'Pick<'.class_basename($modelFqcn).', '.$quoted.'>';
    }
}
