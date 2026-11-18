<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;

final class NestedNameCastMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide a cast, with an import, that builds on the enum its value holds beside a name only it spells, and a cast
     * with no import that spells that name too.
     *
     * @return array<string, mixed>
     */
    #[TsCasts([
        'role' => ['type' => '{ role: RoleType; meta: Meta }', 'import' => '@/types/meta'],
        'note' => 'Meta | null',
    ])]
    public function provide(Model $model): array
    {
        return ['role' => $this->role(), 'note' => null];
    }

    /**
     * Named return type so inference reads the enum.
     */
    private function role(): Role
    {
        return Role::Admin;
    }
}
