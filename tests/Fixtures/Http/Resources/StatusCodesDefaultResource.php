<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Depot;

/**
 * A test-only resource reading the int-keyed default StatusCodesDefaultEvent also declares, which the resource's filter
 * re-indexes into a list.
 *
 * @mixin Depot
 */
final class StatusCodesDefaultResource extends JsonResource
{
    protected $codes = [200 => 'OK'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['codes' => $this->codes];
    }
}
