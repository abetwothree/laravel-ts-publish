<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\Byline;

/**
 * Spreads a helper's keys and then an array that overrides one of them, where PHP keeps the later key (GH-154): the
 * byline's `author` is the title string, not the helper's recipient.
 *
 * @property-read Byline $resource
 */
class BylineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['byline' => [...$this->credited(), ...['author' => $this->resource->title]]];
    }

    /**
     * The byline's own keys, one of which toArray() overrides.
     */
    private function credited(): array
    {
        return ['id' => 1, 'author' => $this->resource->author];
    }
}
