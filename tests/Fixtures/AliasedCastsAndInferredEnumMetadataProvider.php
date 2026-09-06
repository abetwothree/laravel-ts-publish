<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;

final class AliasedCastsAndInferredEnumMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide two same-named cast types from different paths beside a body-inferred enum of that same name.
     *
     * @return array<string, mixed>
     */
    #[TsCasts([
        'first' => ['type' => 'RoleType', 'import' => '@/types/first'],
        'second' => ['type' => 'RoleType', 'import' => '@/types/second'],
    ])]
    public function provide(Model $model): array
    {
        return [
            'first' => 'Admin',
            'second' => 'Guest',
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
