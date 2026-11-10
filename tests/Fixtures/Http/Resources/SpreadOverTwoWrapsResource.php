<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Spreads keys wrapping a status or a role, then a later spread replaces them with two enums named `Status` (GH-154).
 */
final class SpreadOverTwoWrapsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->wrapped($request), ...$this->plain()];
    }

    /**
     * The keys as a status or a role in an `EnumResource`.
     *
     * @return array<string, mixed>
     */
    private function wrapped(Request $request): array
    {
        return [
            'subject' => $request->query('subject') === 'status'
                ? EnumResource::make(Status::Published)
                : EnumResource::make(Role::Guest),
            'rows' => $request->query('subject') === 'status'
                ? EnumResource::make(Status::Published)
                : EnumResource::make(Role::Guest),
        ];
    }

    /**
     * The keys as an object and rows reading the CRM status, then the app status, bare.
     *
     * @return array<string, mixed>
     */
    private function plain(): array
    {
        return [
            'subject' => ['c' => CrmStatus::Active],
            'rows' => [['c' => CrmStatus::Active, 's' => Status::Published]],
        ];
    }
}
