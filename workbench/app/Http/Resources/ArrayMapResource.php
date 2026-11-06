<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Exercises array_map(): one array keeps its keys, two arrays re-index into a list, and a callable of any form is
 * called with each element.
 *
 * @mixin Post
 */
final class ArrayMapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // A typed list maps to the list of what the callback returns.
            'messages' => array_map(fn (array $row): string => $row['message'], $this->rows()),

            // One string-keyed array keeps its keys, so json_encode() writes an object.
            'doubled' => array_map(fn (int $count): int => $count * 2, ['low' => 1, 'high' => 2]),

            // A null callback zips two arrays into tuples, each padded with null where its array is shorter.
            'zipped' => array_map(null, $this->labels(), $this->counts()),

            // A string callable is called with each element, as a closure is.
            'names' => array_map('strtoupper', $this->comments->pluck('content')->all()),
        ];
    }

    /**
     * @return list<array{message: string}>
     */
    private function rows(): array
    {
        return [['message' => 'first'], ['message' => 'second']];
    }

    /**
     * @return list<string>
     */
    private function labels(): array
    {
        return ['a', 'b'];
    }

    /**
     * @return list<int>
     */
    private function counts(): array
    {
        return [1, 2, 3];
    }
}
