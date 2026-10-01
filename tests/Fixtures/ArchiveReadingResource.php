<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Audit\Models\AuditTrail;

/**
 * A test-only resource reading AuditTrail's relation to a model that is published only once its table exists.
 *
 * @mixin AuditTrail
 */
class ArchiveReadingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'archive' => $this->archive,
        ];
    }
}
