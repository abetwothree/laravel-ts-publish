<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;

/** Provides an enum only for the users table, under a docblock key that names it as optional. */
final class OptionalDocblockEnumMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide the table, and the role for the users table only.
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
