<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose local built by key writes starts from parent::toArray(), which no value read types, and is
 * read between that whole write and its key write.
 *
 * @mixin Tag
 */
class KeyBuiltLocalGapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $m = parent::toArray($request);
        $first = $m;
        $m['extra'] = 1;

        return ['first' => $first, 'm' => $m];
    }
}
