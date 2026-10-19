<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

/** A label Inertia sends as its toResponse() data, where json_encode() writes its public properties. */
final class ResponsableLabel implements Responsable
{
    public function __construct(
        public string $text = 'label',
    ) {}

    /**
     * The response whose data an Inertia prop sends.
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['label' => $this->text]);
    }
}
