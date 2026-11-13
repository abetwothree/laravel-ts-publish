<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Returns one key from two branches, an object reading the CRM status in one and the app status in the other (GH-154).
 */
final class BranchRowsOfSameNamedEnumsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($request->boolean('crm')) {
            return ['row' => ['c' => CrmStatus::Active]];
        }

        return ['row' => ['s' => Status::Published]];
    }
}
