<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\PropertyContext;
use Inertia\ProvidesInertiaProperty;
use JsonSerializable;
use Workbench\App\Packages\Ui\Node;

/**
 * Inherits an override while every serialization rule would answer it: an `Arrayable`, a string `jsonSerialize()`, a
 * `__toString()`, a response, an Inertia property and a public property.
 */
final class EveryWayNode extends Node implements JsonSerializable, ProvidesInertiaProperty, Responsable
{
    public string $label = 'node';

    /**
     * The label, as a string.
     */
    public function __toString(): string
    {
        return $this->label;
    }

    /**
     * The label, which json_encode() writes.
     */
    public function jsonSerialize(): string
    {
        return $this->label;
    }

    /**
     * The node's array, as a JSON response.
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray());
    }

    /**
     * The node's array, as Inertia sends it.
     *
     * @return array{kind: string}
     */
    public function toInertiaProperty(PropertyContext $prop): array
    {
        return $this->toArray();
    }
}
