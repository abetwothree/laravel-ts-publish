<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\AlwaysProp;
use Inertia\MergeProp;
use Inertia\OptionalProp;
use Workbench\App\Models\Post;

/**
 * A test-only resource holding Inertia prop objects a `new` builds, which json_encode() sends as `{}`.
 *
 * @mixin Post
 */
class PropObjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'always' => new AlwaysProp('en'),
            'optional' => new OptionalProp(fn () => 1),
            'merged' => new MergeProp([1, 2]),
        ];
    }
}
