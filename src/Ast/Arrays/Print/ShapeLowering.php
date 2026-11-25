<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;

/**
 * Writes a shape into the analysis publishers read: each entry a row in its order, each value's channels under its
 * name in the order the shape keeps, and what the shape holds beside its keys. No row can say the typed keys extras
 * bring, so the analysis holds them as unread keys.
 *
 * @phpstan-import-type AnalyzedProperty from MethodAnalysis
 *
 * @internal
 */
final class ShapeLowering
{
    /**
     * Write a shape's rows, channels and wide data into an analysis, replacing what it held.
     */
    public static function into(MethodAnalysis $target, ArrayShape $shape): void
    {
        $properties = [];
        $nested = $direct = $models = $inlineEnums = $inlineModels = $inlineResources = $wrapConsts = [];
        $wrapped = $casts = $slots = $alike = $held = $closed = [];

        foreach ($shape->entries as $entry) {
            $name = $entry instanceof ShapeKey ? $entry->name : $entry->pattern;
            $properties[] = self::row($name, $entry->value, $entry instanceof ShapeKey ? $entry->presence : null);
            $channels = $entry->value->channels;

            if ($channels === []) {
                continue;
            }

            if (isset($channels['nestedResources'])) {
                $nested[$name] = $channels['nestedResources'];
            }

            if (isset($channels['directEnumFqcns'])) {
                $direct[$name] = $channels['directEnumFqcns'];
            }

            if (isset($channels['modelFqcns'])) {
                $models[$name] = $channels['modelFqcns'];
            }

            if (isset($channels['inlineEnumFqcns'])) {
                $inlineEnums[$name] = $channels['inlineEnumFqcns'];
            }

            if (isset($channels['inlineModelFqcns'])) {
                $inlineModels[$name] = $channels['inlineModelFqcns'];
            }

            if (isset($channels['inlineResourceFqcns'])) {
                $inlineResources[$name] = $channels['inlineResourceFqcns'];
            }

            if (isset($channels['inlineEnumResourceFqcns'])) {
                $wrapConsts[$name] = $channels['inlineEnumResourceFqcns'];
            }

            if (isset($channels['wrappedEnums'])) {
                $wrapped[$name] = $channels['wrappedEnums'];
            }

            if (isset($channels['casts'])) {
                $casts[$name] = $channels['casts'];
            }

            if (isset($channels['declaredTokenFqcns'])) {
                $slots[$name] = $channels['declaredTokenFqcns'];
            }

            if (isset($channels['alikeClassFqcns'])) {
                $alike[$name] = $channels['alikeClassFqcns'];
            }

            if (isset($channels['heldObjectKeys'])) {
                $held[$name] = $channels['heldObjectKeys'];
            }

            if (isset($channels['closedToDottedKeys'])) {
                $closed[$name] = true;
            }
        }

        $wide = $shape->wide;
        $order = $wide->order;
        $loose = $wide->loose;
        $target->properties = $properties;
        $target->nestedResources = self::ordered($order['nestedResources'] ?? [], $loose['nestedResources'] ?? [], $nested);
        $target->directEnumFqcns = self::ordered($order['directEnumFqcns'] ?? [], $loose['directEnumFqcns'] ?? [], $direct);
        $target->modelFqcns = self::ordered($order['modelFqcns'] ?? [], $loose['modelFqcns'] ?? [], $models);
        $target->inlineEnumFqcns = self::ordered($order['inlineEnumFqcns'] ?? [], $loose['inlineEnumFqcns'] ?? [], $inlineEnums);
        $target->inlineModelFqcns = self::ordered($order['inlineModelFqcns'] ?? [], $loose['inlineModelFqcns'] ?? [], $inlineModels);
        $target->inlineResourceFqcns = self::ordered(
            $order['inlineResourceFqcns'] ?? [], $loose['inlineResourceFqcns'] ?? [], $inlineResources,
        );
        $target->inlineEnumResourceFqcns = self::ordered(
            $order['inlineEnumResourceFqcns'] ?? [], $loose['inlineEnumResourceFqcns'] ?? [], $wrapConsts,
        );
        $target->wrappedEnums = self::ordered($order['wrappedEnums'] ?? [], $loose['wrappedEnums'] ?? [], $wrapped);
        $target->casts = self::ordered($order['casts'] ?? [], $loose['casts'] ?? [], $casts);
        $target->declaredTokenFqcns = self::ordered($order['declaredTokenFqcns'] ?? [], $loose['declaredTokenFqcns'] ?? [], $slots);
        $target->alikeClassFqcns = self::ordered($order['alikeClassFqcns'] ?? [], $loose['alikeClassFqcns'] ?? [], $alike);
        $target->heldObjectKeys = self::ordered($order['heldObjectKeys'] ?? [], $loose['heldObjectKeys'] ?? [], $held);
        $target->closedToDottedKeys = self::ordered($order['closedToDottedKeys'] ?? [], $loose['closedToDottedKeys'] ?? [], $closed);
        $target->customImports = $wide->customImports;
        $target->carried = $wide->carried;
        $target->readsCompletely = $wide->readsCompletely;
        $target->holdsUnreadKeys = $wide->holdsUnreadKeys || $shape->extras !== null;
        $target->holdsUnnamedKeys = $wide->holdsUnnamedKeys;
        $target->flatTypeAlias = $wide->flatTypeAlias;
        $target->flatTypeAliasFqcn = $wide->flatTypeAliasFqcn;
        $target->variants = $wide->variants;
        $target->setSpreadArms($wide->spreadArms);
    }

    /**
     * One entry's row: its name, then its fields as the value holds them, with the entry's presence where it differs
     * from the one the value was read with.
     *
     * @return AnalyzedProperty
     */
    private static function row(string $name, KeyValue $value, ?KeyPresence $presence): array
    {
        $row = ['name' => $name, ...$value->row];

        if ($presence === null || ($presence->optional === $row['optional']
            && $presence->absent === ($row['absent'] ?? false) && $presence->removed === ($row['removed'] ?? false))) {
            return $row;
        }

        return MethodAnalysis::withPresence($row, $presence->optional, $presence->absent, $presence->removed);
    }

    /**
     * A channel map in the order the shape kept: each key where it stood, a key the shape gained after it.
     *
     * @template T
     *
     * @param  list<array-key>  $order
     * @param  array<array-key, T>  $loose
     * @param  array<array-key, T>  $named
     * @return array<array-key, T>
     */
    private static function ordered(array $order, array $loose, array $named): array
    {
        if ($order === []) {
            return $named;
        }

        $map = [];

        foreach ($order as $key) {
            if (array_key_exists($key, $named)) {
                $map[$key] = $named[$key];
            } elseif (array_key_exists($key, $loose)) {
                $map[$key] = $loose[$key];
            }
        }

        return $map + $named;
    }
}
