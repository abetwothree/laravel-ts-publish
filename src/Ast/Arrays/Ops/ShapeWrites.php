<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeSignature;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeWide;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use Closure;
use LogicException;

/**
 * Writes into an array shape as MethodAnalysis's merge(), addProperty(), addSignatureEntry(), forgetChannels() and
 * dropKeys() write into an analysis, so each result lowers to the analysis those writes leave, map order included. A
 * written shape keeps its extras and opaque parts but no never-empty proof, which an unset() or a write may void.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type KeyChannels from KeyValue
 * @phpstan-import-type EmbeddedClasses from KeyValue
 * @phpstan-import-type LooseMaps from ShapeWide
 * @phpstan-import-type MapOrder from ShapeWide
 * @phpstan-import-type CarriedMap from MethodAnalysis
 * @phpstan-import-type ImportMapType from MethodAnalysis
 *
 * @phpstan-type Entry = ShapeKey|ShapeSignature
 * @phpstan-type HeldChannels = array{KeyChannels, EmbeddedClasses}
 * @phpstan-type MapKeys = array<string, list<array-key>>
 * @phpstan-type AdoptedEntries = array{array<int, Entry>, LooseMaps}
 *
 * @internal
 */
final class ShapeWrites
{
    /** The maps forgetChannels() clears under a key: all but its held objects and its dotted-key close. */
    private const array FORGOTTEN = [
        ...KeyValue::CLASS_MAPS, ...KeyValue::QUEUES, 'casts', 'declaredTokenFqcns', 'alikeClassFqcns',
    ];

    /**
     * A shape's keys as merge() lays them into an empty analysis: no held object, type alias, variants or spread arm
     * comes with them, each carried class once, and its extras and opaque parts as the unread keys its lowering holds.
     */
    public static function seeded(ArrayShape $seed): ArrayShape
    {
        $seed = ShapeLowering::folded($seed);
        $entries = [];

        foreach ($seed->entries as $entry) {
            $channels = $entry->value->channels;
            unset($channels['heldObjectKeys']);
            $value = new KeyValue($entry->value->row, $channels, $entry->value->embedded);
            $entries[] = self::withValue($entry, $value, self::presenceOf($entry));
        }

        $wide = $seed->wide;
        $order = $wide->order;
        $loose = $wide->loose;
        unset($order['heldObjectKeys'], $loose['heldObjectKeys']);

        return new ArrayShape($entries, wide: new ShapeWide(
            customImports: $wide->customImports,
            carried: MethodAnalysis::carriedWith([], $wide->carried),
            readsCompletely: $wide->readsCompletely,
            holdsUnreadKeys: $wide->holdsUnreadKeys,
            holdsUnnamedKeys: $wide->holdsUnnamedKeys,
            order: $order,
            loose: $loose,
        ));
    }

    /**
     * A whole write merged in as merge() merges one, its extras and opaque parts read as its lowering holds them: a
     * held named key keeps its place and held object and takes the write's value, other entries append, each with the
     * write's presence beside the held one's where the write may not run.
     */
    public static function merged(ArrayShape $held, ArrayShape $write, bool $conditional): ArrayShape
    {
        $write = ShapeLowering::folded($write);
        $named = array_values(array_filter(
            array_map(static fn (ShapeKey|ShapeSignature $entry): string => $entry->writtenName(), $write->entries),
            static fn (string $name): bool => ! IndexSignatureKey::is($name),
        ));

        // A literal's fold writes each named key once, so the entries below never meet a key the write repeats.
        if (count(array_unique($named)) !== count($named)) {
            throw new LogicException('A whole write holds each named key once; a signature entry is one more runtime key.');
        }

        $entries = $held->entries;
        $loose = $held->wide->loose;
        $sets = array_map(array_flip(...), $write->wide->order);
        $removed = [];
        $signatures = [];

        foreach ($write->entries as $entry) {
            $name = $entry->writtenName();
            $signature = IndexSignatureKey::is($name);
            // A signature entry is one more runtime key its signature covers, so it replaces no earlier entry.
            $at = $signature ? null : self::firstOf($entries, $name);
            $laid = self::presenceOf($entry)->after(
                $at === null ? KeyPresence::missing() : self::presenceOf($entries[$at]),
                $conditional,
            );

            foreach (['casts', 'declaredTokenFqcns', 'alikeClassFqcns'] as $map) {
                if (! isset($sets[$map][$name])) {
                    $removed[$map][] = $name;
                }
            }

            $removed['closedToDottedKeys'][] = $name;

            // Every entry of a pattern shares one set of channels, merged once and laid on each below.
            if ($signature) {
                if (! isset($signatures[$name])) {
                    [$channels, $embedded] = self::channelsUnder($entries, $loose, $name);
                    $signatures[$name] = self::mergedChannels($channels, $embedded, $entry->value);
                    $loose = self::looseWithout($loose, $name);
                }

                $entries[] = self::withValue($entry, $entry->value, $laid);

                continue;
            }

            if ($at === null) {
                [$channels, $embedded] = self::channelsUnder([], $loose, $name);
                [$channels, $embedded] = self::mergedChannels($channels, $embedded, $entry->value);
                $loose = self::looseWithout($loose, $name);
                $entries[] = self::withValue($entry, new KeyValue($entry->value->row, $channels, $embedded), $laid);

                continue;
            }

            foreach (self::FORGOTTEN as $map) {
                $removed[$map][] = $name;
            }

            $loose = self::looseWith($loose, $entries[$at]->value->embedded);
            [$channels, $embedded] = self::mergedChannels(KeyValue::classless($entries[$at]->value->channels), [], $entry->value);
            $entries[$at] = self::withValue($entry, new KeyValue($entry->value->row, $channels, $embedded), $laid);
        }

        foreach ($entries as $at => $entry) {
            $merged = $signatures[$entry->writtenName()] ?? null;

            if ($merged !== null) {
                $entries[$at] = self::withValue($entry, new KeyValue($entry->value->row, $merged[0], $merged[1]));
            }
        }

        return self::mergedWide($held, $write, $entries, $loose, $removed);
    }

    /**
     * A shape with one named key written as addProperty() writes it over the key's forgotten channels, with the given
     * presence: a key held already keeps its first position, and its old embedded classes stay.
     *
     * @param  ValueExpressionResult  $value
     */
    public static function keyWritten(
        ArrayShape $shape,
        string $name,
        array $value,
        bool $optional,
        KeyPresence $presence,
    ): ArrayShape {
        $slice = self::slice($name, $value, $optional, false);
        $new = $slice->entries[0];
        $entries = $shape->entries;
        $at = self::firstOf($entries, $name);
        $loose = self::looseWithout($shape->wide->loose, $name);
        $removed = self::forgetting($name);

        foreach (['heldObjectKeys', 'closedToDottedKeys'] as $map) {
            if (! isset($new->value->channels[$map])) {
                $removed[$map] = [$name];
            }
        }

        // Every entry of the name holds the one set of channels the analysis keeps under it.
        foreach ($entries as $index => $entry) {
            if ($entry->writtenName() === $name) {
                $loose = self::looseWith($loose, $entry->value->embedded);
                $entries[$index] = self::withValue($entry, new KeyValue($entry->value->row, $new->value->channels, $new->value->embedded));
            }
        }

        $entries[$at ?? count($entries)] = self::withValue($new, $new->value, $presence);
        $loose = self::looseMerged($loose, $slice->wide->loose);
        $order = self::reordered($shape->wide->order, $removed, $slice->wide->order);

        return self::rebuilt($shape, $entries, $loose, $order, $slice->wide->customImports, $slice->wide->carried);
    }

    /**
     * A shape with one more signature entry, as addSignatureEntry() records it: every entry of its pattern shares one
     * set of channels, its queues appended, and the pattern's cast goes.
     *
     * @param  ValueExpressionResult  $value
     */
    public static function signatureWritten(ArrayShape $shape, string $pattern, array $value): ArrayShape
    {
        $slice = self::slice($pattern, $value, false, true);
        $written = $slice->entries[0]->value;
        $own = $written->channels;
        [$held, $heldEmbedded] = self::channelsUnder($shape->entries, $shape->wide->loose, $pattern);
        $channels = array_filter([
            ...self::classesOver($held, $own),
            'declaredTokenFqcns' => $held['declaredTokenFqcns'] ?? null,
            'alikeClassFqcns' => $own['alikeClassFqcns'] ?? null,
            'heldObjectKeys' => $own['heldObjectKeys'] ?? null,
            'closedToDottedKeys' => $own['closedToDottedKeys'] ?? null,
        ], static fn (mixed $channel): bool => $channel !== null);
        $removed = ['casts' => [$pattern]];

        foreach (['alikeClassFqcns', 'heldObjectKeys', 'closedToDottedKeys'] as $map) {
            if (! isset($own[$map])) {
                $removed[$map] = [$pattern];
            }
        }

        $embedded = self::embeddedWith($heldEmbedded, $written->embedded);
        $entries = [];

        foreach ([...$shape->entries, $slice->entries[0]] as $entry) {
            $entries[] = $entry->writtenName() === $pattern
                ? self::withValue($entry, new KeyValue($entry->value->row, $channels, $embedded))
                : $entry;
        }

        $loose = self::looseMerged(self::looseWithout($shape->wide->loose, $pattern), $slice->wide->loose);
        $order = self::reordered($shape->wide->order, $removed, $slice->wide->order);

        return self::rebuilt($shape, $entries, $loose, $order, $slice->wide->customImports, $slice->wide->carried);
    }

    /**
     * A shape without a key's entries and channels, as forgetChannels() and dropping its rows leave it: its held
     * object, dotted-key close and embedded classes stay behind.
     */
    public static function without(ArrayShape $shape, string $name): ArrayShape
    {
        $loose = self::looseWithout($shape->wide->loose, $name, keepsHeld: true);
        $entries = [];

        foreach ($shape->entries as $entry) {
            if ($entry->writtenName() !== $name) {
                $entries[] = $entry;

                continue;
            }

            $channels = $entry->value->channels;
            $loose = self::looseWith($loose, $entry->value->embedded);

            if (isset($channels['heldObjectKeys'])) {
                $loose['heldObjectKeys'][$name] = $channels['heldObjectKeys'];
            }

            if (isset($channels['closedToDottedKeys'])) {
                $loose['closedToDottedKeys'][$name] = true;
            }
        }

        return self::rebuilt($shape, $entries, $loose, self::reordered($shape->wide->order, self::forgetting($name), []));
    }

    /**
     * A shape with a key's channels forgotten, as forgetChannels() leaves them: no class behind its value.
     */
    public static function withoutChannels(ArrayShape $shape, string $name): ArrayShape
    {
        $loose = self::looseWithout($shape->wide->loose, $name, keepsHeld: true);
        $entries = [];

        foreach ($shape->entries as $entry) {
            if ($entry->writtenName() === $name) {
                $loose = self::looseWith($loose, $entry->value->embedded);
                $entry = self::withValue($entry, new KeyValue($entry->value->row, KeyValue::classless($entry->value->channels)));
            }

            $entries[] = $entry;
        }

        return self::rebuilt($shape, $entries, $loose, self::reordered($shape->wide->order, self::forgetting($name), []));
    }

    /**
     * A shape less each named key of $names, with every channel under it, as dropKeys() drops them; signature entries,
     * imports and embedded classes stay.
     *
     * @param  list<string>  $names
     */
    public static function withoutKeys(ArrayShape $shape, array $names): ArrayShape
    {
        $entries = [];
        $dropped = [];

        foreach ($shape->entries as $entry) {
            $name = $entry->writtenName();

            if (IndexSignatureKey::is($name) || ! in_array($name, $names, true)) {
                $entries[] = $entry;
            } else {
                $dropped[] = $name;
            }
        }

        $removed = array_fill_keys(array_keys($shape->wide->order), $dropped);

        return self::rebuilt($shape, $entries, $shape->wide->loose, self::reordered($shape->wide->order, $removed, []));
    }

    /**
     * A shape with each entry under a name, or every entry where none is named, mapped.
     *
     * @param  Closure(Entry): Entry  $map
     */
    public static function mapped(ArrayShape $shape, ?string $name, Closure $map): ArrayShape
    {
        $entries = array_map(
            static fn (ShapeKey|ShapeSignature $entry): ShapeKey|ShapeSignature => $name === null || $entry->writtenName() === $name
                ? $map($entry)
                : $entry,
            $shape->entries,
        );

        return new ArrayShape($entries, $shape->extras, $shape->opaque, wide: $shape->wide);
    }

    /**
     * A shape with its read flags set where given, all else kept.
     */
    public static function withFlags(
        ArrayShape $shape,
        ?bool $readsCompletely = null,
        ?bool $holdsUnreadKeys = null,
    ): ArrayShape {
        return new ArrayShape(
            $shape->entries,
            $shape->extras,
            $shape->opaque,
            $shape->nonEmptyProof,
            $shape->wide->flagged($readsCompletely, $holdsUnreadKeys),
        );
    }

    /**
     * An entry holding another value, and a presence where given, its value's row saying it too, as a lift reads it.
     *
     * @param  Entry  $entry
     * @return Entry
     */
    public static function withValue(
        ShapeKey|ShapeSignature $entry,
        KeyValue $value,
        ?KeyPresence $presence = null,
    ): ShapeKey|ShapeSignature {
        $value = $presence === null ? $value : $value->withPresence($presence);

        return $entry instanceof ShapeKey
            ? new ShapeKey($entry->name, $value, $presence ?? $entry->presence, $entry->armOnly)
            : new ShapeSignature($entry->pattern, $value);
    }

    /**
     * A value with another printed type, its other fields and its channels kept.
     */
    public static function retyped(KeyValue $value, string $type): KeyValue
    {
        return $type === $value->type()
            ? $value
            : new KeyValue(['type' => $type] + $value->row, $value->channels, $value->embedded);
    }

    /**
     * Whether an entry is sent, as its lowered row says it.
     *
     * @param  Entry  $entry
     */
    public static function presenceOf(ShapeKey|ShapeSignature $entry): KeyPresence
    {
        return $entry instanceof ShapeKey ? $entry->presence : $entry->value->presence();
    }

    /**
     * The position of the first entry written under a name, or null.
     *
     * @param  array<int, Entry>  $entries
     */
    public static function firstOf(array $entries, string $name): ?int
    {
        foreach ($entries as $at => $entry) {
            if ($entry->writtenName() === $name) {
                return $at;
            }
        }

        return null;
    }

    /**
     * Each key's type, by name, the last entry of a name winning, as a column of the lowered rows reads it.
     *
     * @return array<array-key, string>
     */
    public static function typesOf(ArrayShape $shape): array
    {
        $types = [];

        foreach ($shape->entries as $entry) {
            $types[$entry->writtenName()] = $entry->value->type();
        }

        return $types;
    }

    /**
     * The entries no key owns with another's merged in, as merge() merges each map: a queue appended, any other entry
     * replaced; a held object never comes, and a join's merge drops the casts, slots and dotted-key closes too.
     *
     * @param  LooseMaps  $loose
     * @param  LooseMaps  $more
     * @return LooseMaps
     */
    public static function looseMerged(array $loose, array $more, bool $dropsDeclared = false): array
    {
        foreach ([...KeyValue::CLASS_MAPS, 'alikeClassFqcns'] as $map) {
            if (isset($more[$map])) {
                $loose[$map] = array_replace($loose[$map] ?? [], $more[$map]);
            }
        }

        foreach (KeyValue::QUEUES as $queue) {
            foreach ($more[$queue] ?? [] as $name => $classes) {
                $loose[$queue][$name] = [...$loose[$queue][$name] ?? [], ...$classes];
            }
        }

        if ($dropsDeclared) {
            return $loose;
        }

        if (isset($more['casts'])) {
            $loose['casts'] = array_replace($loose['casts'] ?? [], $more['casts']);
        }

        if (isset($more['declaredTokenFqcns'])) {
            $loose['declaredTokenFqcns'] = array_replace($loose['declaredTokenFqcns'] ?? [], $more['declaredTokenFqcns']);
        }

        if (isset($more['closedToDottedKeys'])) {
            $loose['closedToDottedKeys'] = array_replace($loose['closedToDottedKeys'] ?? [], $more['closedToDottedKeys']);
        }

        return $loose;
    }

    /**
     * One written value in a shape of its own, as addProperty(), or addSignatureEntry() for a signature, records it:
     * its row and channels, and the imports, carried and embedded classes beside it.
     *
     * @param  ValueExpressionResult  $value
     */
    private static function slice(string $name, array $value, bool $optional, bool $signature): ArrayShape
    {
        $slice = new MethodAnalysis;

        if ($signature) {
            $slice->addSignatureEntry($name, $value);
        } else {
            $slice->addProperty($name, $value, $optional);
        }

        return ArrayShapes::ofAnalysis($slice);
    }

    /**
     * The shape past a whole write's entries, with what merge() takes from beside its keys: imports appended, classes
     * carried, read flags, the entries no key owns but held objects, and each map's order; the variants go.
     *
     * @param  array<int, Entry>  $entries
     * @param  LooseMaps  $loose
     * @param  MapKeys  $removed
     */
    private static function mergedWide(
        ArrayShape $held,
        ArrayShape $write,
        array $entries,
        array $loose,
        array $removed,
    ): ArrayShape {
        $kept = $held->wide;
        $own = $write->wide;
        $set = $own->order;
        unset($set['heldObjectKeys']);
        [$entries, $loose] = self::adopted($entries, self::looseMerged($loose, $own->loose));

        return new ArrayShape(array_values($entries), $held->extras, $held->opaque, wide: new ShapeWide(
            customImports: MethodAnalysis::importsWith($kept->customImports, $own->customImports),
            carried: MethodAnalysis::carriedWith($kept->carried, $own->carried),
            readsCompletely: $kept->readsCompletely && $own->readsCompletely,
            holdsUnreadKeys: $kept->holdsUnreadKeys || $own->holdsUnreadKeys,
            holdsUnnamedKeys: $kept->holdsUnnamedKeys || $own->holdsUnnamedKeys,
            flatTypeAlias: $kept->flatTypeAlias,
            flatTypeAliasFqcn: $kept->flatTypeAliasFqcn,
            spreadArms: $kept->spreadArms,
            order: self::reordered($kept->order, $removed, $set),
            loose: $loose,
        ));
    }

    /**
     * Entries that take each entry no key owned under their own name, as a map entry under a key's name is that key's:
     * a queue appends to the key's own, any other entry replaces it.
     *
     * @param  array<int, Entry>  $entries
     * @param  LooseMaps  $loose
     * @return AdoptedEntries
     */
    private static function adopted(array $entries, array $loose): array
    {
        $names = [];

        foreach ($entries as $at => $entry) {
            $name = $entry->writtenName();
            $stray = self::channelsUnder([], $loose, $name)[0];

            if ($stray === []) {
                continue;
            }

            $names[$name] = true;
            $own = $entry->value->channels;
            $channels = [...$own, ...$stray, ...self::classesOver(array_intersect_key($own, array_flip(KeyValue::QUEUES)), $stray)];
            $entries[$at] = self::withValue($entry, new KeyValue($entry->value->row, $channels, $entry->value->embedded));
        }

        foreach (array_keys($names) as $name) {
            $loose = self::looseWithout($loose, (string) $name);
        }

        return [$entries, $loose];
    }

    /**
     * The channels and embedded classes a shape holds under a name: its first entry's, or the entries no key owns.
     *
     * @param  array<int, Entry>  $entries
     * @param  LooseMaps  $loose
     * @return HeldChannels
     */
    private static function channelsUnder(array $entries, array $loose, string $name): array
    {
        $at = self::firstOf($entries, $name);

        if ($at !== null) {
            return [$entries[$at]->value->channels, $entries[$at]->value->embedded];
        }

        return [array_filter([
            'nestedResources' => $loose['nestedResources'][$name] ?? null,
            'directEnumFqcns' => $loose['directEnumFqcns'][$name] ?? null,
            'modelFqcns' => $loose['modelFqcns'][$name] ?? null,
            'inlineEnumFqcns' => $loose['inlineEnumFqcns'][$name] ?? null,
            'inlineModelFqcns' => $loose['inlineModelFqcns'][$name] ?? null,
            'inlineResourceFqcns' => $loose['inlineResourceFqcns'][$name] ?? null,
            'inlineEnumResourceFqcns' => $loose['inlineEnumResourceFqcns'][$name] ?? null,
            'wrappedEnums' => $loose['wrappedEnums'][$name] ?? null,
            'casts' => $loose['casts'][$name] ?? null,
            'declaredTokenFqcns' => $loose['declaredTokenFqcns'][$name] ?? null,
            'alikeClassFqcns' => $loose['alikeClassFqcns'][$name] ?? null,
            'heldObjectKeys' => $loose['heldObjectKeys'][$name] ?? null,
            'closedToDottedKeys' => $loose['closedToDottedKeys'][$name] ?? null,
        ], static fn (mixed $channel): bool => $channel !== null), []];
    }

    /**
     * A key's channels once merge() lays a written value over those it held: a class the write names wins, a queue
     * appends, the cast, slots, alike record and dotted-key close are the write's, and the held object stays its own.
     *
     * @param  KeyChannels  $held
     * @param  EmbeddedClasses  $heldEmbedded
     * @return HeldChannels
     */
    private static function mergedChannels(array $held, array $heldEmbedded, KeyValue $written): array
    {
        $own = $written->channels;
        $channels = array_filter([
            ...self::classesOver($held, $own),
            'casts' => $own['casts'] ?? null,
            'declaredTokenFqcns' => $own['declaredTokenFqcns'] ?? null,
            'alikeClassFqcns' => $own['alikeClassFqcns'] ?? null,
            'heldObjectKeys' => $held['heldObjectKeys'] ?? null,
            'closedToDottedKeys' => $own['closedToDottedKeys'] ?? null,
        ], static fn (mixed $channel): bool => $channel !== null);

        return [$channels, self::embeddedWith($heldEmbedded, $written->embedded)];
    }

    /**
     * A key's class channels once a value is written over those it held: a class the value names wins, a queue
     * appends.
     *
     * @param  KeyChannels  $held
     * @param  KeyChannels  $own
     * @return KeyChannels
     */
    private static function classesOver(array $held, array $own): array
    {
        return array_filter([
            'nestedResources' => $own['nestedResources'] ?? $held['nestedResources'] ?? null,
            'directEnumFqcns' => $own['directEnumFqcns'] ?? $held['directEnumFqcns'] ?? null,
            'modelFqcns' => $own['modelFqcns'] ?? $held['modelFqcns'] ?? null,
            'inlineEnumFqcns' => self::queued($held['inlineEnumFqcns'] ?? null, $own['inlineEnumFqcns'] ?? null),
            'inlineModelFqcns' => self::queued($held['inlineModelFqcns'] ?? null, $own['inlineModelFqcns'] ?? null),
            'inlineResourceFqcns' => self::queued($held['inlineResourceFqcns'] ?? null, $own['inlineResourceFqcns'] ?? null),
            'inlineEnumResourceFqcns' => self::queued(
                $held['inlineEnumResourceFqcns'] ?? null,
                $own['inlineEnumResourceFqcns'] ?? null,
            ),
            'wrappedEnums' => self::queued($held['wrappedEnums'] ?? null, $own['wrappedEnums'] ?? null),
        ], static fn (mixed $channel): bool => $channel !== null);
    }

    /**
     * A key's queue with a written value's appended, or null where neither holds one.
     *
     * @param  list<class-string>|null  $held
     * @param  list<class-string>|null  $own
     * @return list<class-string>|null
     */
    private static function queued(?array $held, ?array $own): ?array
    {
        return $held === null && $own === null ? null : [...$held ?? [], ...$own ?? []];
    }

    /**
     * Embedded classes with more of them, each keyed by itself.
     *
     * @param  EmbeddedClasses  $embedded
     * @param  EmbeddedClasses  $more
     * @return EmbeddedClasses
     */
    private static function embeddedWith(array $embedded, array $more): array
    {
        foreach ($more as $map => $classes) {
            $embedded[$map] = array_replace($embedded[$map] ?? [], $classes);
        }

        return $embedded;
    }

    /**
     * The entries no key owns with a value's embedded classes, which a class map keeps by themselves once the key goes.
     *
     * @param  LooseMaps  $loose
     * @param  EmbeddedClasses  $embedded
     * @return LooseMaps
     */
    private static function looseWith(array $loose, array $embedded): array
    {
        foreach (KeyValue::CLASS_MAPS as $map) {
            if (isset($embedded[$map])) {
                $loose[$map] = array_replace($loose[$map] ?? [], $embedded[$map]);
            }
        }

        return $loose;
    }

    /**
     * The entries no key owns, less those under a name, its held object and dotted-key close kept where $keepsHeld.
     *
     * @param  LooseMaps  $loose
     * @return LooseMaps
     */
    private static function looseWithout(array $loose, string $name, bool $keepsHeld = false): array
    {
        foreach ([...KeyValue::CLASS_MAPS, ...KeyValue::QUEUES, 'alikeClassFqcns'] as $map) {
            unset($loose[$map][$name]);
        }

        unset($loose['casts'][$name], $loose['declaredTokenFqcns'][$name]);

        if (! $keepsHeld) {
            unset($loose['heldObjectKeys'][$name], $loose['closedToDottedKeys'][$name]);
        }

        return $loose;
    }

    /**
     * Each channel map's key order once $removed's keys leave it and $set's keys are set: a key the map still holds
     * keeps its place, as an array assignment keeps it, and a new one goes last.
     *
     * @param  MapOrder  $order
     * @param  MapKeys  $removed
     * @param  MapOrder  $set
     * @return MapOrder
     */
    private static function reordered(array $order, array $removed, array $set): array
    {
        foreach ($removed as $map => $names) {
            if (isset($order[$map]) && $names !== []) {
                $gone = array_flip(array_map(strval(...), $names));
                $order[$map] = array_values(array_filter(
                    $order[$map],
                    static fn (int|string $key): bool => ! isset($gone[(string) $key]),
                ));
            }
        }

        foreach ($set as $map => $keys) {
            $held = array_flip($order[$map] ?? []);

            foreach ($keys as $key) {
                if (! isset($held[$key])) {
                    $order[$map][] = $key;
                    $held[$key] = true;
                }
            }
        }

        return $order;
    }

    /**
     * The key forgetChannels() takes out of each map it clears.
     *
     * @return MapKeys
     */
    private static function forgetting(string $name): array
    {
        return array_fill_keys(self::FORGOTTEN, [$name]);
    }

    /**
     * A shape with other entries, entries no key owns and map order, and more imports and carried classes where given.
     *
     * @param  array<int, Entry>  $entries
     * @param  LooseMaps  $loose
     * @param  MapOrder  $order
     * @param  ImportMapType  $imports
     * @param  CarriedMap  $carried
     */
    private static function rebuilt(
        ArrayShape $shape,
        array $entries,
        array $loose,
        array $order,
        array $imports = [],
        array $carried = [],
    ): ArrayShape {
        $wide = $shape->wide;

        return new ArrayShape(array_values($entries), $shape->extras, $shape->opaque, wide: new ShapeWide(
            MethodAnalysis::importsWith($wide->customImports, $imports), MethodAnalysis::carriedWith($wide->carried, $carried),
            $wide->readsCompletely, $wide->holdsUnreadKeys, $wide->holdsUnnamedKeys, $wide->flatTypeAlias,
            $wide->flatTypeAliasFqcn, $wide->variants, $wide->spreadArms, $order, $loose,
        ));
    }
}
