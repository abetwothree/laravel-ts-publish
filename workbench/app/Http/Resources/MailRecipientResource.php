<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\MailRecipient;

/**
 * A recipient's name and email, the resource arm ValueSpreadsResource spreads (GH-154).
 *
 * @property-read MailRecipient $resource
 */
class MailRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['name' => $this->resource->name, 'email' => $this->resource->email];
    }
}
