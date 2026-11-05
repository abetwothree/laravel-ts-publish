<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use ArrayObject;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A model-less resource reading methods PHP types only tentatively as `array` off typed properties of its own. */
final class InternalServiceResource extends JsonResource
{
    private DateTimeZone $zone;

    private ArrayObject $object;

    /**
     * Hold the resource beside a London time zone and an array object.
     */
    public function __construct(mixed $resource)
    {
        parent::__construct($resource);
        $this->zone = new DateTimeZone('Europe/London');
        $this->object = new ArrayObject(['a' => 1]);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'object_copy' => $this->object->getArrayCopy(),
            'zone_location' => $this->zone->getLocation(),
        ];
    }
}
