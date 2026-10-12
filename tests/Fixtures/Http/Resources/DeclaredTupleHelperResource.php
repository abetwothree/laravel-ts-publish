<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Workbench\App\Models\Post;

/**
 * A helper whose `@return` declares a list shape, which the resource's filter re-indexes as the list it already is,
 * and a call whose `@return` is conditional.
 *
 * @mixin Post
 */
class DeclaredTupleHelperResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pair' => $this->pair(),
            'title_first' => Str::ucfirst($this->title),
        ];
    }

    /**
     * @return list{int, string}
     */
    protected function pair(): array
    {
        return json_decode('[1,"a"]', true);
    }
}
