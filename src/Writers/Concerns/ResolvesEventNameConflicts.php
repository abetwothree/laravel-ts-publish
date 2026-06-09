<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared logic for detecting and resolving event-name conflicts in broadcast event writers.
 *
 * When two events from different namespaces share the same PHP short class name,
 * `resolveEventNameConflicts()` assigns each a namespace-prefix alias so that
 * TypeScript identifiers, import bindings, and export names remain unique.
 *
 * The `extraConflictFields()` hook lets each writer inject additional fields
 * (such as `constKey`) into the aliased event data without modifying this trait.
 */
trait ResolvesEventNameConflicts
{
    /**
     * Resolve conflicts when two events share the same short class name.
     *
     * Each element in $events must have at minimum an `eventName` and `namespacePath`
     * key. Non-conflicting events are returned unchanged with `importedAs` and
     * `exportedName` set to the original `eventName`. Conflicting events receive
     * namespace-prefix aliases (e.g. 'AppUserSynced', 'CrmUserSynced').
     *
     * @param  Collection<int, array<string, mixed>>  $events
     * @return Collection<int, array<string, mixed>>
     */
    protected function resolveEventNameConflicts(Collection $events): Collection
    {
        $byName = $events->groupBy('eventName');

        return $events->map(function (array $event) use ($byName): array {
            /** @var string $eventName */
            $eventName = $event['eventName'];

            if (($byName->get($eventName)?->count() ?? 1) <= 1) {
                /** @var array<string, mixed> $entry */
                $entry = [...$event, 'importedAs' => $eventName, 'exportedName' => $eventName];

                return $entry;
            }

            /** @var string $namespacePath */
            $namespacePath = $event['namespacePath'];
            $alias = $this->computeEventAlias($namespacePath, $eventName);

            /** @var array<string, mixed> $entry */
            $entry = [
                ...$event,
                ...$this->extraConflictFields($event, $alias),
                'importedAs' => $eventName.' as '.$alias,
                'exportedName' => $alias,
            ];

            return $entry;
        });
    }

    /**
     * Return extra array fields to include when an event name conflict is resolved.
     *
     * Override in the using class to inject writer-specific fields.
     * For example, BroadcastEventsIndexWriter returns ['constKey' => $this->quoteKey($alias)].
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function extraConflictFields(array $event, string $alias): array
    {
        return [];
    }

    /**
     * Compute a unique alias for an event using its namespace path as a discriminator.
     *
     * For namespace path 'crm/events' and event 'UserSynced' returns 'CrmUserSynced'.
     * Walks backwards through the non-leaf path segments to find a meaningful prefix.
     */
    private function computeEventAlias(string $namespacePath, string $eventName): string
    {
        $segments = array_values(array_filter(explode('/', $namespacePath)));
        $prefixSegments = array_slice($segments, 0, -1);
        $skip = ['events'];

        foreach (array_reverse($prefixSegments) as $segment) {
            if (! in_array($segment, $skip, true)) {
                return Str::studly($segment).$eventName;
            }
        }

        $first = reset($prefixSegments);

        return ($first !== false ? Str::studly($first) : '').$eventName;
    }
}
