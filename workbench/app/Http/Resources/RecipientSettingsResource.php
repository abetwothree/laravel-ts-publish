<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\RecipientList;

/**
 * Maps a value object's recipients to each one's cached settings, a read no rule types, under a `toArray()` `@return`
 * docblock that types it (GH-154): the docblock fills that member alone, and the body types the name.
 *
 * @property-read RecipientList $resource
 */
class RecipientSettingsResource extends JsonResource
{
    /**
     * @return array{recipients: list<array{name: string|null, settings: array{digest: bool}|null}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'recipients' => array_map(
                fn ($r) => ['name' => $r->name, 'settings' => cache('mail.settings.'.$r->email)],
                $this->resource->recipients,
            ),
        ];
    }
}
