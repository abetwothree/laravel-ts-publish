<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Post;

final class ForwardedRelationCallMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide a post's comment count, which its relation counts on the query, and null for any other model.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'comment_count' => $model instanceof Post ? $model->comments()->count() : null,
        ];
    }
}
