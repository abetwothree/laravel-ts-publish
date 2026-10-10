<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use Closure;

/**
 * Fits each key a `#[TsCasts]` entry retypes to the text that entry publishes.
 *
 * A class the displaced value carried stays queued only for a token the text spells and the entry's import does not
 * bring, so no pass rebuilds the text's shape (the `AsEnum` rewrite) or aliases a name the app brings itself.
 *
 * @phpstan-import-type CastInForce from MethodAnalysis
 * @phpstan-import-type CastMap from MethodAnalysis
 *
 * @internal
 */
final class CastChannels
{
    /**
     * The names a cast's own import brings into the file, each the app's own, which no class of the package supplies.
     *
     * @param  CastInForce  $cast
     * @return list<string>
     */
    public static function brings(array $cast): array
    {
        return $cast['import'] !== null ? TsTypeString::extractImportableTypes($cast['type']) : [];
    }

    /**
     * The class behind each token of a cast's text among the given ones, leaving out a class whose name its import
     * brings: the per-name rule every publisher's fit and late registration shares.
     *
     * @param  list<class-string>  $fqcns
     * @param  list<string>  $brought  brings() for the cast
     * @param  Closure(class-string): string  $nameOf
     * @return list<class-string>
     */
    public static function spells(array $fqcns, string $type, array $brought, Closure $nameOf): array
    {
        $queue = array_values(array_filter(
            $fqcns,
            fn (string $fqcn): bool => ! in_array($nameOf($fqcn), $brought, true),
        ));

        return new ClassTokenQueue($queue, $nameOf)->take($type);
    }

    /**
     * Whether a published text spells this name with no class of that name behind it: an extends clause or type alias,
     * or a key whose own import channels hold none. Only such a text keeps a carried class imported.
     *
     * @param  array<array-key, string>  $types  key => its published type
     * @param  array<array-key, list<string>>  $ownNames  key => the names of the classes its import channels hold
     * @param  list<string>  $extra  the texts no key stands behind: extends clauses, a type alias
     */
    public static function spelledUnclaimed(string $name, array $types, array $ownNames, array $extra = []): bool
    {
        if (TsTypeString::typeNameOccursIn($name, ...$extra)) {
            return true;
        }

        foreach ($types as $key => $type) {
            if (! in_array($name, $ownNames[$key] ?? [], true) && TsTypeString::typeNameOccursIn($name, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rewrite every cast key's import channels to the classes its text spells, as embedded names, and carry the rest.
     *
     * A publisher calls it once, over the casts in force, after it knows every cast source. Each name a cast's import
     * brings is bound by that import, so a custom import of it from another path is dropped.
     *
     * @param  CastMap  $casts  key => the cast in force for it: its text, and the path of its own import, if any
     */
    public function fit(MethodAnalysis $analysis, array $casts): void
    {
        if ($casts === []) {
            return;
        }

        $this->unbindOtherPaths($analysis, $casts);

        /** @var list<class-string> $broughtOver */
        $broughtOver = [];

        /** @var list<class-string> $displaced */
        $displaced = [];

        foreach ($casts as $key => $castInForce) {
            // PHP stores a numeric-string key such as '6' as an int.
            $name = (string) $key;

            if (! $analysis->hasFqcnChannel($name)) {
                continue;
            }

            $type = $castInForce['type'];
            $brought = self::brings($castInForce);

            $enums = $this->enumsOf($analysis, $name);
            $models = $this->modelsOf($analysis, $name);
            $resources = $this->resourcesOf($analysis, $name);
            $wraps = $this->wrapsOf($analysis, $name);

            $cast = $analysis->casts[$name] ?? null;
            $analysis->forgetChannels($name);

            if ($cast !== null) {
                $analysis->casts[$name] = $cast;
            }

            [$kept, $carriedEnums] = $this->divide($enums, $type, $brought, $this->enumTypeName(...), $broughtOver);
            $this->queue($analysis->inlineEnumFqcns, $analysis->directEnumFqcns, $name, $kept);

            [$kept, $carriedModels] = $this->divide($models, $type, $brought, class_basename(...), $broughtOver);
            $this->queue($analysis->inlineModelFqcns, $analysis->modelFqcns, $name, $kept);

            $resourceName = static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn);
            [$kept, $carriedResources] = $this->divide($resources, $type, $brought, $resourceName, $broughtOver);
            $this->queue($analysis->inlineResourceFqcns, $analysis->nestedResources, $name, $kept);

            $analysis->carry(['enums' => $carriedEnums, 'models' => $carriedModels, 'resources' => $carriedResources]);
            array_push($displaced, ...$carriedEnums, ...$carriedModels, ...$carriedResources);

            // The text may write a const after `typeof` by an alias only the publisher knows, so every wrap the import
            // does not bring stays, and the publisher keeps the ones the text writes.
            $keptWraps = array_values(array_filter(
                $wraps,
                fn (string $fqcn): bool => ! in_array($this->enumConstName($fqcn), $brought, true),
            ));

            if ($keptWraps !== []) {
                $analysis->inlineEnumResourceFqcns[$name] = $keptWraps;
            }
        }

        $this->settle($analysis, [...$displaced, ...$broughtOver], $broughtOver);
    }

    /**
     * The enums whose type names a key's value carried, in the order its import channels queue them.
     *
     * @return list<class-string>
     */
    private function enumsOf(MethodAnalysis $analysis, string $name): array
    {
        return [
            ...(isset($analysis->enumResources[$name]) ? [$analysis->enumResources[$name]] : []),
            ...(isset($analysis->directEnumFqcns[$name]) ? [$analysis->directEnumFqcns[$name]] : []),
            ...($analysis->multiEnumResourceFqcns[$name] ?? []),
            ...($analysis->inlineEnumFqcns[$name] ?? []),
        ];
    }

    /**
     * The models a key's value carried, in the order its import channels queue them.
     *
     * @return list<class-string>
     */
    private function modelsOf(MethodAnalysis $analysis, string $name): array
    {
        return [
            ...(isset($analysis->modelFqcns[$name]) ? [$analysis->modelFqcns[$name]] : []),
            ...($analysis->inlineModelFqcns[$name] ?? []),
        ];
    }

    /**
     * The resources a key's value carried, in the order its import channels queue them.
     *
     * @return list<class-string>
     */
    private function resourcesOf(MethodAnalysis $analysis, string $name): array
    {
        return [
            ...(isset($analysis->nestedResources[$name]) ? [$analysis->nestedResources[$name]] : []),
            ...($analysis->inlineResourceFqcns[$name] ?? []),
        ];
    }

    /**
     * The enums a key's value wrapped in an `EnumResource`, whose const an `AsEnum<typeof …>` would spell.
     *
     * @return list<class-string>
     */
    private function wrapsOf(MethodAnalysis $analysis, string $name): array
    {
        return array_values(array_unique([
            ...(isset($analysis->enumResources[$name]) ? [$analysis->enumResources[$name]] : []),
            ...($analysis->multiEnumResourceFqcns[$name] ?? []),
            ...($analysis->inlineEnumResourceFqcns[$name] ?? []),
        ]));
    }

    /**
     * Split a cast key's classes of one kind into the class behind each token its text spells, leaving out the names
     * the entry's import brings, and the classes to carry: every other class whose name the import does not bring.
     *
     * @param  list<class-string>  $fqcns
     * @param  list<string>  $brought
     * @param  Closure(class-string): string  $nameOf
     * @param  list<class-string>  $broughtOver  collects each class whose name the import brings
     * @return array{list<class-string>, list<class-string>}
     */
    private function divide(array $fqcns, string $type, array $brought, Closure $nameOf, array &$broughtOver): array
    {
        $kept = self::spells($fqcns, $type, $brought, $nameOf);
        $carried = [];

        foreach (array_unique($fqcns) as $fqcn) {
            if (in_array($nameOf($fqcn), $brought, true)) {
                $broughtOver[] = $fqcn;
            } elseif (! in_array($fqcn, $kept, true)) {
                $carried[] = $fqcn;
            }
        }

        return [$kept, $carried];
    }

    /**
     * Queue a cast key's kept classes as the names its text spells, each self-keyed too, as an embedded class is.
     *
     * @param  array<string, list<class-string>>  $queues
     * @param  array<string, class-string>  $embedded
     * @param  list<class-string>  $kept
     */
    private function queue(array &$queues, array &$embedded, string $name, array $kept): void
    {
        if ($kept === []) {
            return;
        }

        $queues[$name] = $kept;

        foreach ($kept as $fqcn) {
            $embedded[$fqcn] = $fqcn;
        }
    }

    /**
     * Drop each custom import of a name a cast in force brings from another path.
     *
     * @param  CastMap  $casts
     */
    private function unbindOtherPaths(MethodAnalysis $analysis, array $casts): void
    {
        $paths = [];

        foreach ($casts as $cast) {
            foreach (self::brings($cast) as $name) {
                $paths[$name][] = $cast['import'];
            }
        }

        foreach ($analysis->customImports as $path => $names) {
            $bound = array_values(array_filter(
                $names,
                fn (string $name): bool => ! isset($paths[$name]) || in_array($path, $paths[$name], true),
            ));

            if ($bound === []) {
                unset($analysis->customImports[$path]);
            } else {
                $analysis->customImports[$path] = $bound;
            }
        }
    }

    /**
     * Drop the self-keyed entry of each displaced class no import channel references any more, so only the carried
     * record holds it, and drop from that record each class an import brought over.
     *
     * @param  list<class-string>  $displaced
     * @param  list<class-string>  $broughtOver
     */
    private function settle(MethodAnalysis $analysis, array $displaced, array $broughtOver): void
    {
        $names = array_flip(array_column($analysis->properties, 'name'));
        $referenced = [];

        foreach ([
            $analysis->enumResources,
            $analysis->directEnumFqcns,
            $analysis->modelFqcns,
            $analysis->nestedResources,
        ] as $map) {
            foreach ($map as $key => $fqcn) {
                if (isset($names[$key])) {
                    $referenced[$fqcn] = true;
                }
            }
        }

        foreach ([
            $analysis->multiEnumResourceFqcns,
            $analysis->inlineEnumFqcns,
            $analysis->inlineModelFqcns,
            $analysis->inlineResourceFqcns,
        ] as $map) {
            foreach ($map as $fqcns) {
                foreach ($fqcns as $fqcn) {
                    $referenced[$fqcn] = true;
                }
            }
        }

        foreach (array_unique($displaced) as $fqcn) {
            if (isset($referenced[$fqcn]) || isset($names[$fqcn])) {
                continue;
            }

            unset($analysis->directEnumFqcns[$fqcn], $analysis->modelFqcns[$fqcn], $analysis->nestedResources[$fqcn]);
        }

        // A class another key wraps stays carried too: only the carried record says some text may read it bare.
        $brought = array_flip($broughtOver);

        foreach ($analysis->carried as $kind => $fqcns) {
            $left = array_values(array_filter($fqcns, fn (string $fqcn): bool => ! isset($brought[$fqcn])));

            if ($left === []) {
                unset($analysis->carried[$kind]);
            } else {
                $analysis->carried[$kind] = $left;
            }
        }
    }

    /** The name an enum's type is spelled with. */
    private function enumTypeName(string $fqcn): string
    {
        return LaravelTsPublish::toTsType($fqcn)['enumTypes'][0] ?? class_basename($fqcn).'Type';
    }

    /** The name an enum's const is spelled with. */
    private function enumConstName(string $fqcn): string
    {
        return LaravelTsPublish::toTsType($fqcn)['enums'][0] ?? class_basename($fqcn);
    }
}
