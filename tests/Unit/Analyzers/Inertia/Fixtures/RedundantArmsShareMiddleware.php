<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/** Shares a case value beside its enum's alias, an empty list beside a list, and a case value beside its wrap (GH-166). */
final class RedundantArmsShareMiddleware extends Middleware
{
    public function __construct(private readonly Post $post = new Post) {}

    /** Shares `status` and `kept` ternaries the `c` query flag picks, and `tags` empty in one return only. */
    public function share(Request $request): array
    {
        $c = $request->boolean('c');

        if ($this->post->exists) {
            return [
                'status' => $c ? Status::Draft->value : $this->post->status->value,
                'tags' => [],
                'kept' => $c ? EnumResource::make($this->post->status) : Status::Draft->value,
            ];
        }

        return [
            'status' => $c ? Status::Draft->value : $this->post->status->value,
            'tags' => [$this->post->priority],
            'kept' => $c ? EnumResource::make($this->post->status) : Status::Draft->value,
        ];
    }
}
