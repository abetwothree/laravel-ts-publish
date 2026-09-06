<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;

final class DocblockOverridesEnumMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide an enum the docblock retypes as the string it normalizes to, so the body's import must not survive.
     *
     * @return array{role: string}
     */
    public function provide(Model $model): array
    {
        return ['role' => $this->role()];
    }

    /**
     * Named return type so inference alone would import RoleType.
     */
    private function role(): Role
    {
        return Role::Admin;
    }
}
