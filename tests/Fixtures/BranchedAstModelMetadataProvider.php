<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\User;

final class BranchedAstModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide a different statically analyzable payload for each model branch.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        if ($model instanceof User) {
            return ['userModel' => true];
        }

        return ['otherModel' => 1];
    }
}
