<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeSignature;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeWide;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;

/**
 * Reads arrays as shapes: an analysis's rows, each with the channels it keeps under the key's name.
 *
 * @phpstan-import-type LooseMaps from ShapeWide
 * @phpstan-import-type MapOrder from ShapeWide
 *
 * @internal
 */
final class ArrayShapes
{
    /**
     * An analysis's rows lifted into a shape, each row one entry in its order, every channel and flag kept.
     */
    public static function ofAnalysis(MethodAnalysis $analysis): ArrayShape
    {
        $entries = [];
        $owned = [];

        foreach ($analysis->properties as $row) {
            $name = $row['name'];
            $owned[$name] = true;
            $value = KeyValue::of($analysis, $row);
            $entries[] = IndexSignatureKey::is($name)
                ? new ShapeSignature($name, $value)
                : new ShapeKey($name, $value, KeyPresence::ofRow($row));
        }

        [$order, $loose] = self::mapsBeside($analysis, $owned);

        return new ArrayShape($entries, wide: new ShapeWide(
            customImports: $analysis->customImports,
            carried: $analysis->carried,
            readsCompletely: $analysis->readsCompletely,
            holdsUnreadKeys: $analysis->holdsUnreadKeys,
            holdsUnnamedKeys: $analysis->holdsUnnamedKeys,
            flatTypeAlias: $analysis->flatTypeAlias,
            flatTypeAliasFqcn: $analysis->flatTypeAliasFqcn,
            variants: $analysis->variants,
            spreadArms: $analysis->spreadArms(),
            order: $order,
            loose: $loose,
        ));
    }

    /**
     * Each channel map's key order, and its entries under a key no row names.
     *
     * @param  array<array-key, true>  $owned  each row's name
     * @return array{MapOrder, LooseMaps}
     */
    private static function mapsBeside(MethodAnalysis $analysis, array $owned): array
    {
        $order = [];
        $loose = [];

        if ($analysis->nestedResources !== []) {
            $order['nestedResources'] = array_keys($analysis->nestedResources);
            $loose['nestedResources'] = array_diff_key($analysis->nestedResources, $owned);
        }

        if ($analysis->directEnumFqcns !== []) {
            $order['directEnumFqcns'] = array_keys($analysis->directEnumFqcns);
            $loose['directEnumFqcns'] = array_diff_key($analysis->directEnumFqcns, $owned);
        }

        if ($analysis->modelFqcns !== []) {
            $order['modelFqcns'] = array_keys($analysis->modelFqcns);
            $loose['modelFqcns'] = array_diff_key($analysis->modelFqcns, $owned);
        }

        if ($analysis->inlineEnumFqcns !== []) {
            $order['inlineEnumFqcns'] = array_keys($analysis->inlineEnumFqcns);
            $loose['inlineEnumFqcns'] = array_diff_key($analysis->inlineEnumFqcns, $owned);
        }

        if ($analysis->inlineModelFqcns !== []) {
            $order['inlineModelFqcns'] = array_keys($analysis->inlineModelFqcns);
            $loose['inlineModelFqcns'] = array_diff_key($analysis->inlineModelFqcns, $owned);
        }

        if ($analysis->inlineResourceFqcns !== []) {
            $order['inlineResourceFqcns'] = array_keys($analysis->inlineResourceFqcns);
            $loose['inlineResourceFqcns'] = array_diff_key($analysis->inlineResourceFqcns, $owned);
        }

        if ($analysis->inlineEnumResourceFqcns !== []) {
            $order['inlineEnumResourceFqcns'] = array_keys($analysis->inlineEnumResourceFqcns);
            $loose['inlineEnumResourceFqcns'] = array_diff_key($analysis->inlineEnumResourceFqcns, $owned);
        }

        if ($analysis->wrappedEnums !== []) {
            $order['wrappedEnums'] = array_keys($analysis->wrappedEnums);
            $loose['wrappedEnums'] = array_diff_key($analysis->wrappedEnums, $owned);
        }

        if ($analysis->casts !== []) {
            $order['casts'] = array_keys($analysis->casts);
            $loose['casts'] = array_diff_key($analysis->casts, $owned);
        }

        if ($analysis->declaredTokenFqcns !== []) {
            $order['declaredTokenFqcns'] = array_keys($analysis->declaredTokenFqcns);
            $loose['declaredTokenFqcns'] = array_diff_key($analysis->declaredTokenFqcns, $owned);
        }

        if ($analysis->alikeClassFqcns !== []) {
            $order['alikeClassFqcns'] = array_keys($analysis->alikeClassFqcns);
            $loose['alikeClassFqcns'] = array_diff_key($analysis->alikeClassFqcns, $owned);
        }

        if ($analysis->heldObjectKeys !== []) {
            $order['heldObjectKeys'] = array_keys($analysis->heldObjectKeys);
            $loose['heldObjectKeys'] = array_diff_key($analysis->heldObjectKeys, $owned);
        }

        if ($analysis->closedToDottedKeys !== []) {
            $order['closedToDottedKeys'] = array_keys($analysis->closedToDottedKeys);
            $loose['closedToDottedKeys'] = array_diff_key($analysis->closedToDottedKeys, $owned);
        }

        return [$order, $loose];
    }
}
