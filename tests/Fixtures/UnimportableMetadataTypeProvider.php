<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;

final class UnimportableMetadataTypeProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata whose inferred type would require an import.
     *
     * @return array{table: string, role?: Role}
     */
    public function provide(Model $model): array
    {
        $metadata = [
            'table' => $model->getTable(),
        ];

        if ($model->getTable() === 'users') {
            $metadata['role'] = Role::Admin;
        }

        return $metadata;
    }
}
