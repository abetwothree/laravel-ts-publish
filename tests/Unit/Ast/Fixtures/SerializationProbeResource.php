<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Reads each value a JSON serialization rule covers through every path the engine types: a receiver's method, the
 * resource's own property, an inline `@var`, an in-place `new`, and a parameter default.
 */
final class SerializationProbeResource extends JsonResource
{
    /** @var Exception */
    public $ownError;

    public StringableNullableJson $ownNote;

    /**
     * Wrap a resource with the service it reads.
     */
    public function __construct(mixed $resource, private readonly SerializationProbeService $service)
    {
        parent::__construct($resource);
    }

    /**
     * The keys, one per path.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Exception $caught */
        $caught = $this->service->failure();

        return [
            'note' => $this->service->note(),
            'own_error' => $this->ownError,
            'own_note' => $this->ownNote,
            'caught' => $caught,
            'checked_at' => $request->boolean('fresh') ? new Carbon : null,
            'default_checked_at' => $this->whenLoaded('author', fn ($author, $at = true ? new Carbon : null) => $at),
            'default_note' => $this->whenLoaded('author', fn ($author, $note = new StringableNullableJson) => $note),
        ];
    }
}
