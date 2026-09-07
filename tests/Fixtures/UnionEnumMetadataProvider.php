<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

final class UnionEnumMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide one value two direct enums can produce, which the engine keys by FQCN rather than by property.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return ['role' => $model->exists ? $this->role() : $this->status()];
    }

    /**
     * One branch of the union.
     */
    private function role(): Role
    {
        return Role::Admin;
    }

    /**
     * The other branch of the union.
     */
    private function status(): Status
    {
        return Status::Draft;
    }
}
