<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource merging arrays whose spread or later key writes a key again, at the top level and nested.
 *
 * @mixin Post
 */
final class SpreadMergeJoinResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $titled = ['by' => $this->title];
        $named = ['name' => $this->title];
        $maybe = [];

        if ($request->boolean('titled')) {
            $maybe['maybe'] = $this->title;
        }

        return [
            'id' => $this->id,
            $this->mergeWhen($request->boolean('titled'), ['by' => $this->author, ...$titled]),
            $this->mergeWhen($request->boolean('titled'), ['note' => $this->author, ...['note' => $this->title]]),
            $this->mergeWhen($request->boolean('merged'), ['maybe' => $this->author, ...$maybe]),
            $this->mergeUnless($request->boolean('titled'), [...$named, 'name' => $this->author]),
            'row' => ['id' => $this->id, $this->mergeWhen($request->boolean('titled'), ['by' => $this->author, ...$titled])],
        ];
    }
}
