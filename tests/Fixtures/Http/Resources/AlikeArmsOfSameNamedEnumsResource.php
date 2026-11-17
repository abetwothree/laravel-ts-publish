<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A test-only resource whose ternaries pick one shape from two alike but for which of two same-named enums it holds,
 * so its arms print alike until the publisher names each enum apart (GH-166).
 */
final class AlikeArmsOfSameNamedEnumsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $crm = $request->boolean('crm');

        return [
            'row' => $crm ? ['s' => $request->boolean('none') ? [] : [CrmStatus::Active]] : ['s' => [Status::Published]],
            'list' => $crm ? [CrmStatus::Active] : [Status::Published],
        ];
    }
}
