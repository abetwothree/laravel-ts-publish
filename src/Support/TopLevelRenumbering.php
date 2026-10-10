<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * The keys Laravel sends at a top level it `array_merge()`s, which renumbers every int key from 0: an Inertia page
 * merged over shared data (`PropsResolver::resolve()`) and a `broadcastWith()` payload merged with its socket.
 *
 * @internal
 */
final class TopLevelRenumbering
{
    /**
     * Map each top-level name to the key Laravel sends it on: int-like names renumber from 0 in source order.
     *
     * @param  list<string>  $names
     * @return array<array-key, string> keyed by name, which PHP stores as an int when it is int-like
     */
    public static function renumber(array $names): array
    {
        $next = 0;
        $renumbered = [];

        foreach ($names as $name) {
            $renumbered[$name] = PhpArrayKey::storedInt($name) === null ? $name : (string) $next++;
        }

        return $renumbered;
    }

    /**
     * Re-key a top-level map by the key renumber() gave each name, keeping its order.
     *
     * @template TValue
     *
     * @param  array<array-key, TValue>  $map
     * @param  array<array-key, string>  $renumbered
     * @return array<array-key, TValue>
     */
    public static function rekey(array $map, array $renumbered): array
    {
        $rekeyed = [];

        foreach ($map as $name => $value) {
            $rekeyed[$renumbered[$name] ?? $name] = $value;
        }

        return $rekeyed;
    }

    /**
     * Warn once for each key Laravel sends on another number.
     *
     * @param  array<array-key, string>  $renumbered
     */
    public static function warnRenumbered(string $subject, string $topLevel, array $renumbered): void
    {
        foreach ($renumbered as $name => $published) {
            if ((string) $name !== $published) {
                AnalysisWarnings::addOnce($subject, sprintf(
                    'The key "%s" is renumbered to "%s" by Laravel at the top level of %s; use a string key.',
                    $name,
                    $published,
                    $topLevel,
                ));
            }
        }
    }
}
