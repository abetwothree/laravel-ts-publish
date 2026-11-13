<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\MailRecipient;
use Workbench\App\ValueObjects\RecipientList;

/**
 * Maps a value object's recipient lists through array_map() with no `toArray()` docblock (GH-154): an untyped callback
 * parameter over a promoted and a plain `@var list<MailRecipient>` property, and a typed one.
 *
 * @property-read RecipientList $resource
 */
class UndocumentedRecipientsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $list = $this->resource;

        return [
            'recipients' => array_map(fn ($r) => ['name' => $r->name, 'email' => $r->email], $list->recipients),
            'copied' => array_map(fn ($r) => ['name' => $r->name, 'email' => $r->email], $list->copied),
            'typed' => array_map(fn (MailRecipient $r) => ['name' => $r->name, 'email' => $r->email], $list->recipients),
        ];
    }
}
