<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * Fixture resource used to test #[TsResourceCasts] placed on the toArray() method
 * rather than on the class. No class-level annotation is present on purpose so that
 * method-level behavior is tested in isolation.
 *
 * @mixin User
 */
class ToArrayCastsResource extends JsonResource
{
    #[TsResourceCasts([
        'role' => 'string',
        'email' => ['type' => 'string | null', 'optional' => true],
        'injected_field' => 'Record<string, unknown>',
        'coordinates' => ['type' => 'GeoPoint', 'import' => '@/types/geo'],
    ])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}
