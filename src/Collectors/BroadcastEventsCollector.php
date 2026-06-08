<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use ReflectionClass;

/**
 * Collects all broadcast event class-strings from the application.
 *
 * Discovers every class implementing ShouldBroadcast or ShouldBroadcastNow
 * within the configured directories (default: app/Events).
 *
 * @extends CoreCollector<ShouldBroadcast>
 */
class BroadcastEventsCollector extends CoreCollector
{
    /**
     * Default scan directory for broadcast event classes.
     */
    protected function defaultDirectory(): string
    {
        return app_path('Events');
    }

    /**
     * Accept only classes that implement ShouldBroadcast or ShouldBroadcastNow.
     *
     * @param  ReflectionClass<object>  $reflection
     */
    protected function classFilter(ReflectionClass $reflection): bool
    {
        return $this->validateBroadcastEvent($reflection);
    }

    /**
     * Resolve included/excluded/additional_directories from config.
     *
     * @return array{included: list<string>, excluded: list<string>, additional_directories: list<string>}
     */
    protected function finderSettings(): array
    {
        return [
            'included' => $this->sanitizeAllowSetting(config()->array('ts-publish.broadcast_events.included', [])),
            'excluded' => $this->sanitizeAllowSetting(config()->array('ts-publish.broadcast_events.excluded', [])),
            'additional_directories' => $this->sanitizeAllowSetting(config()->array('ts-publish.broadcast_events.additional_directories', [])),
        ];
    }
}
