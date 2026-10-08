<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Handover;

/**
 * A test-only resource over Handover that writes its `who` key twice: the CRM receiver first, then the app's sender.
 *
 * @mixin Handover
 */
class HandoverRewrittenKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];
        $data['who'] = ['p' => $this->receiver];
        $data['who'] = ['p' => $this->sender];

        return $data;
    }
}
