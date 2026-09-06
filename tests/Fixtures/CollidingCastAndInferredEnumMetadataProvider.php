<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;

final class CollidingCastAndInferredEnumMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide one cast type sharing its name with a body-inferred enum, which nothing can alias apart.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['label' => ['type' => 'RoleType', 'import' => '@/types/label']])]
    public function provide(Model $model): array
    {
        return [
            'label' => 'Admin',
            'role' => $this->role(),
        ];
    }

    /**
     * Named return type so inference claims the unaliased RoleType.
     */
    private function role(): Role
    {
        return Role::Admin;
    }
}
