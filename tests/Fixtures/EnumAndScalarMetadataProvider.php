<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;

final class EnumAndScalarMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide one enum-typed and one scalar value from a generic declaration.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'role' => $this->role(),
            'count' => 3,
        ];
    }

    /**
     * Named return type so inference carries the enum FQCN channel.
     */
    private function role(): Role
    {
        return Role::Admin;
    }
}
