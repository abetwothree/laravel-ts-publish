<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * array_filter() of shapes, a named-key one and an int-keyed one the response re-indexes into a list, and the values of
 * a filtered shape.
 *
 * @mixin Post
 */
final class ArrayFilterShapesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'shape' => array_filter(['a' => $this->title, 'b' => $this->published_at]),
            'null_member' => array_filter(['a' => $this->title, 'b' => null]),
            'int_keyed' => array_filter([1 => $this->title, 2 => $this->content]),
            'values' => array_values(array_filter(['a' => $this->title, 'b' => $this->published_at])),
        ];
    }
}
