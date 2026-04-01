<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Models\User;

/**
 * Exercises: parent spread inheriting customImports from parent trait TsResourceCasts.
 *
 * @mixin User
 */
class ExtendedAddressResource extends TraitSpreadCoverageResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'extra_field' => 'value',
        ];
    }
}
