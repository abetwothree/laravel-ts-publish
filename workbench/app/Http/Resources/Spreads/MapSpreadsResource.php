<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\CreditLine;

/**
 * Spreads of maps and lists that write keys again or add numbered keys (GH-161, GH-153): a map local given a named
 * key over an earlier key, a list spread after a map, and a list spread beside a named key.
 *
 * @property-read CreditLine $resource
 */
class MapSpreadsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tones = $this->resource->tones();
        $tones['by'] = $this->resource->title;

        return [
            'named_key_over_key' => ['by' => $this->resource->author, 'z' => 1, ...$tones],
            'list_after_map' => [...$this->resource->labels, ...$this->resource->ints],
            'list_beside_key' => [...$this->resource->ints, 'x' => $this->resource->title],
        ];
    }
}
