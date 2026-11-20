<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use function AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Stubs\byReferenceHelper;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource passing its discriminant to a namespaced helper between the discriminant and a key it decides,
 * as a cast whose value nothing types.
 *
 * @mixin Post
 */
class ByReferenceHelperResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $flag = $this->is_pinned;

        return ['flag' => $flag, 'r' => (bool) byReferenceHelper($flag), 'detail' => $flag ? 'title' : null];
    }
}
