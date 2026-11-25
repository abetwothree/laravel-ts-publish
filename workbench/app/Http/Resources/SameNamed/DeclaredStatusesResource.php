<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\SameNamed;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A helper's declared shape alternating the app's and the CRM's `Status` over an untyped read (GH-81). Each token names
 * the enum its member declares.
 */
class DeclaredStatusesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'statuses' => $this->statuses(),
        ];
    }

    /**
     * The app's status, the CRM's, then the app's again.
     *
     * @return array{a: Status, b: CrmStatus, c: Status|null}
     */
    private function statuses(): array
    {
        return cache('statuses');
    }
}
