<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Workbench\App\Models\Post;

/**
 * Calls typed by the call alone: Str::upper() and Str::lower() of the title, whose `@return` is a conditional.
 *
 * @mixin Post
 */
final class DirectCallsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'upper' => Str::upper($this->title),
            'lower' => Str::lower($this->title),
        ];
    }
}
