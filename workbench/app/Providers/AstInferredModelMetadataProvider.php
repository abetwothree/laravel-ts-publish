<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class AstInferredModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata whose types can be inferred from the method body.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['role' => ['type' => 'RoleType', 'import' => '../enums']])]
    public function provide(Model $model): array
    {
        return [
            'morphClass' => (string) $model->getMorphClass(),
            'enabled' => true,
            'limits' => [
                'minimum' => 1,
                'maximum' => null,
            ],
            'role' => 'Admin',
        ];
    }
}
