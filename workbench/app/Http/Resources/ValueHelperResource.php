<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Depot;

/**
 * Laravel's value helpers, typed as json_encode() writes what they return: a Carbon as its date string, a Stringable
 * and a URL as strings, and a collection as the list or object its items encode as. `translated` stays unknown,
 * since __() can return an array.
 *
 * @mixin Depot
 */
class ValueHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'generated_at' => now(),
            'day' => today(),
            'title' => str($this->name),
            'link' => url('/depots'),
            'empty_list' => collect(),
            'list' => collect([1, 2]),
            'record' => collect(['a' => 1]),
            'names' => collect([$this->name]),
            'count_or_since' => $this->when($request->has('count'), 1, now()),
            'translated' => __('depot'),
        ];
    }
}
