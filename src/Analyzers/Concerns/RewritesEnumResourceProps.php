<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\AnalysisComposer;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Support\Facades\Config;

/**
 * The page props and shared data an `EnumResource` wraps, which Inertia sends as the resource's objects rather than the
 * bare cases, published as `AsEnum<typeof Const>` the way a resource file publishes them.
 *
 * @phpstan-import-type PagePropMap from InertiaPageAnalyzer
 * @phpstan-import-type EnumResourceArm from AnalysisComposer
 */
trait RewritesEnumResourceProps
{
    /**
     * Rewrite each EnumResource-wrapped prop to `AsEnum<typeof Const>`, each arm of a ternary as Inertia sends it.
     *
     * Applied per property rather than to the whole rendered type, so an enum some other prop still
     * reads bare keeps that prop's plain type name — and with it its type import.
     *
     * @param  PagePropMap  $props
     * @return PagePropMap
     */
    protected function rewriteEnumResourceTypes(array $props, MethodAnalysis $analysis): array
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return $props;
        }

        $types = array_map(static fn (array $prop): string => $prop['type'], $props);

        foreach (AnalysisComposer::enumResourceArms($analysis, $types) as $name => $arm) {
            $types[$name] = isset($analysis->directEnumFqcns[$name])
                ? self::wrapBesideBareRead($types[$name], $arm)
                : self::wrapEnum($types[$name], $arm['fqcn']);
        }

        foreach ($analysis->multiEnumResourceFqcns as $name => $fqcns) {
            if (isset($types[$name])) {
                $types[$name] = self::wrapEachBranch($types[$name], $fqcns);
            }
        }

        foreach (array_keys($props) as $name) {
            $props[$name]['type'] = $types[$name];
        }

        return $props;
    }

    /**
     * The `AsEnum<typeof Const>` an enum's resource publishes as.
     *
     * @param  class-string  $fqcn
     */
    private static function asEnum(string $fqcn): string
    {
        return 'AsEnum<typeof '.ValueResult::enumConstName($fqcn).'>';
    }

    /**
     * The type with each of an enum's type tokens spelled as its resource's `AsEnum<typeof Const>`.
     *
     * @param  class-string  $fqcn
     */
    private static function wrapEnum(string $type, string $fqcn): string
    {
        return TsTypeString::substituteEnumType($type, ValueResult::enumTypeName($fqcn), self::asEnum($fqcn));
    }

    /**
     * The type of a prop that wraps its enum in one branch and reads it bare in another, each arm as Inertia sends it.
     *
     * Every member but the bare read's is substituted, as a wrapped collection is `{ data: … }` here. A wrap whose own
     * spelling no member holds merged into the bare read's member, which a `make()` always does, so it goes in front.
     *
     * @param  EnumResourceArm  $arm
     */
    private static function wrapBesideBareRead(string $type, array $arm): string
    {
        $bare = ValueResult::enumTypeName($arm['fqcn']).($arm['directIsArray'] ? '[]' : '');
        $own = self::asEnum($arm['fqcn']).($arm['wrapIsCollection'] ? '[]' : '');
        $members = TsTypeString::splitTopLevelUnion($type);
        $wrapped = array_map(
            static fn (string $member): string => $member === $bare ? $member : self::wrapEnum($member, $arm['fqcn']),
            $members,
        );

        return implode(' | ', in_array($bare, $members, true) && ! in_array($own, $wrapped, true)
            ? [$own, ...$wrapped]
            : $wrapped);
    }

    /**
     * The type of a prop whose branches wrap different enums, each non-null member's own enum spelled as its wrap.
     *
     * @param  list<class-string>  $fqcns  each branch's enum, in branch order
     */
    private static function wrapEachBranch(string $type, array $fqcns): string
    {
        $members = [];
        $index = 0;

        foreach (TsTypeString::splitTopLevelUnion($type) as $member) {
            $fqcn = $member === 'null' ? null : ($fqcns[$index++] ?? null);
            $members[] = $fqcn === null ? $member : self::wrapEnum($member, $fqcn);
        }

        return implode(' | ', $members);
    }
}
