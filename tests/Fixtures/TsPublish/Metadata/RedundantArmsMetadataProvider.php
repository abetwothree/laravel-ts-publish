<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/** Provides a case value beside its enum's alias, an empty list beside a list, and a case value beside a wrap (GH-166). */
final class RedundantArmsMetadataProvider implements ModelMetadataProvider
{
    public function __construct(private readonly Post $post = new Post) {}

    /**
     * Provide each ternary, whose arms the model's existence picks.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        $c = $model->exists;

        return [
            'status' => $c ? Status::Draft->value : $this->post->status->value,
            'tags' => $c ? [] : [$this->post->priority],
            'kept' => $c ? EnumResource::make($this->post->status) : Status::Draft->value,
        ];
    }
}
