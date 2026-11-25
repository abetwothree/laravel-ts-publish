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

            foreach ($entry->value->embedded as $map => $classes) {
                match ($map) {
                    'nestedResources' => $nested += $classes,
                    'directEnumFqcns' => $direct += $classes,
                    'modelFqcns' => $models += $classes,
                };
            }

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
     * Replace the channels an analysis keeps under a key's name with a value's, each where it stands, beside the
     * classes its queues key by themselves.
     */
    public static function layChannels(MethodAnalysis $target, string $name, KeyValue $value): void
    {
        $channels = $value->channels;
        self::lay($target->nestedResources, $name, $channels['nestedResources'] ?? null);
        self::lay($target->directEnumFqcns, $name, $channels['directEnumFqcns'] ?? null);
        self::lay($target->modelFqcns, $name, $channels['modelFqcns'] ?? null);
        self::lay($target->inlineEnumFqcns, $name, $channels['inlineEnumFqcns'] ?? null);
        self::lay($target->inlineModelFqcns, $name, $channels['inlineModelFqcns'] ?? null);
        self::lay($target->inlineResourceFqcns, $name, $channels['inlineResourceFqcns'] ?? null);
        self::lay($target->inlineEnumResourceFqcns, $name, $channels['inlineEnumResourceFqcns'] ?? null);
        self::lay($target->wrappedEnums, $name, $channels['wrappedEnums'] ?? null);
        self::lay($target->casts, $name, $channels['casts'] ?? null);
        self::lay($target->declaredTokenFqcns, $name, $channels['declaredTokenFqcns'] ?? null);
        self::lay($target->alikeClassFqcns, $name, $channels['alikeClassFqcns'] ?? null);
        self::lay($target->heldObjectKeys, $name, $channels['heldObjectKeys'] ?? null);
        self::lay($target->closedToDottedKeys, $name, $channels['closedToDottedKeys'] ?? null);
        $target->nestedResources = array_replace($target->nestedResources, $value->embedded['nestedResources'] ?? []);
        $target->directEnumFqcns = array_replace($target->directEnumFqcns, $value->embedded['directEnumFqcns'] ?? []);
        $target->modelFqcns = array_replace($target->modelFqcns, $value->embedded['modelFqcns'] ?? []);
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
     * Set one key's entry in a channel map where the key stands, or drop it where the value holds none.
     *
     * @template T
     *
     * @param  array<array-key, T>  $map
     * @param  T|null  $entry
     *
     * @param-out array<array-key, T>  $map
     */
    private static function lay(array &$map, string $name, mixed $entry): void
    {
        if ($entry === null) {
            unset($map[$name]);
        } else {
            $map[$name] = $entry;
        }
    }

    /**
     * A channel map in the order the shape kept: each key where it stood, then a key the shape gained, then an entry
     * no key owns that the order never held.
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
        $map = [];

        foreach ($order as $key) {
            if (array_key_exists($key, $named)) {
                $map[$key] = $named[$key];
            } elseif (array_key_exists($key, $loose)) {
                $map[$key] = $loose[$key];
            }
        }

        return $map + $named + $loose;
    }
}
