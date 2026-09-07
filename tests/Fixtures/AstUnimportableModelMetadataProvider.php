<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;

final class AstUnimportableModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata whose body-inferred type requires an explicit import.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return ['role' => $this->role()];
    }

    /**
     * Return a named type that requires an import.
     */
    private function role(): Role
    {
        return Role::Admin;
    }
}
