<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrapPicks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Spreads two helpers whose `@return` types their wraps: a status or a role beside rows it fills bare, and the app's
 * or the CRM's status (GH-160).
 *
 * @property-read WrapPicks $resource
 */
final class SpreadWrapHelpersResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...$this->subject(), ...$this->pair()];
    }

    /**
     * A status or a role wrapped, else rows whose enums only the `@return` types.
     *
     * @return array{subject: Status|Role|list<array{status: Status, role: Role}>}
     */
    private function subject(): array
    {
        return [
            'subject' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : ($this->resource->d
                    ? EnumResource::make($this->resource->role)
                    : array_map(fn ($r) => ['status' => cache('status'), 'role' => cache('role')], $this->resource->rows)),
        ];
    }

    /**
     * The app's or the CRM's status wrapped.
     *
     * @return array{pair: Status|CrmStatus}
     */
    private function pair(): array
    {
        return [
            'pair' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : EnumResource::make($this->resource->crmStatus),
        ];
    }
}
