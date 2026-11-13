<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Spreads keys holding the app status or a role, then a later spread replaces each with the CRM status (GH-154).
 *
 * @mixin Post
 */
final class SpreadOverHeldEnumsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->held($request), ...$this->plain()];
    }

    /**
     * Two wraps under an object key, rows before two wraps, and the app status bare under an object key.
     *
     * @return array<string, mixed>
     */
    private function held(Request $request): array
    {
        return [
            'row' => ['subject' => $request->query('subject') === 'status'
                ? EnumResource::make(Status::Published)
                : EnumResource::make(Role::Guest)],
            'subject' => $request->query('subject') === 'rows'
                ? array_map(fn ($comment) => ['n' => 1], $this->comments->all())
                : ($request->query('subject') === 'status'
                    ? EnumResource::make(Status::Published)
                    : EnumResource::make(Role::Guest)),
            'bare' => ['s' => Status::Published],
        ];
    }

    /**
     * Each key as an object reading the CRM status.
     *
     * @return array<string, mixed>
     */
    private function plain(): array
    {
        return [
            'row' => ['c' => CrmStatus::Active],
            'subject' => ['c' => CrmStatus::Active],
            'bare' => ['c' => CrmStatus::Active],
        ];
    }
}
