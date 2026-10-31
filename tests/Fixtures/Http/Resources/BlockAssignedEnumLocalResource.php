<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Wraps a status in an EnumResource through a local each branch of an `if` assigns an enum read, and the status a
 * request asks for through a local its block assigns.
 *
 * @mixin Post
 */
final class BlockAssignedEnumLocalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->boolean('draft')) {
            $status = Status::Draft;
        } else {
            $status = $this->status;
        }

        if ($request->has('asked')) {
            $asked = Status::from($request->integer('asked'));

            return ['status' => EnumResource::make($status), 'asked' => EnumResource::make($asked)];
        }

        return ['status' => EnumResource::make($status)];
    }
}
