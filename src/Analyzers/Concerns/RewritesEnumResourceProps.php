<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Support\Facades\Config;

/**
 * The page props and shared data an `EnumResource` wraps, which Inertia sends as the resource's objects rather than the
 * bare cases, published as `AsEnum<typeof Const>` the way a resource file publishes them.
 *
 * @phpstan-import-type PagePropMap from InertiaPageAnalyzer
 */
trait RewritesEnumResourceProps
{
    /**
     * Rewrite each EnumResource-wrapped prop to `AsEnum<typeof Const>`.
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

        foreach ($analysis->enumResources as $name => $fqcn) {
            if (! isset($props[$name])) {
                continue;
            }

            $props[$name]['type'] = TsTypeString::substituteEnumType(
                $props[$name]['type'],
                ValueResult::enumTypeName($fqcn),
                'AsEnum<typeof '.ValueResult::enumConstName($fqcn).'>',
            );
        }

        return $props;
    }
}
