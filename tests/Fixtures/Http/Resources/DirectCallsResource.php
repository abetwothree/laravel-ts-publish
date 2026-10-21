<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Workbench\App\Models\Post;

/**
 * Calls typed by the call alone: Str::upper() and Str::lower() of the title, whose `@return` is a conditional, and
 * array_filter() of the options or of a list, with and without a callback. It keeps its keys, so a gap a filter leaves
 * in a list reaches the response.
 *
 * @mixin Post
 */
final class DirectCallsResource extends JsonResource
{
    public $preserveKeys = true;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'upper' => Str::upper($this->title),
            'lower' => Str::lower($this->title),
            'filtered' => array_filter($this->options),
            'filtered_by' => array_filter($this->options, fn (string $option): bool => $option !== 'x'),
            'filtered_list' => array_filter(explode('B', $this->title)),
            'filtered_nulls' => array_filter([$this->title, null]),
            'filtered_nulls_by' => array_filter([$this->title, null], fn (?string $title): bool => $title !== ''),
        ];
    }
}
