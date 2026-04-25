<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Workbench\App\Http\Resources\Concerns\ExtendsInterfaces;

/**
 * Resource with no @mixin or TsResource — tests convention-based model guess.
 * Also tests multiple TsExtends in parent class, trait, and locally.
 */
#[TsExtends('BaseResource', import: '@/types/base')]
class WarehouseResource extends RoutableResource
{
    use ExtendsInterfaces;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'review_priority' => $this->review_priority,
            'review_priority_typed' => $this->review_priority_typed,
            'review_priority_typed_short' => $this->review_priority_typed_short,
            'manager' => $this->manager,
            'primary_contact' => $this->primaryContact,
            'secondary_contact' => $this->secondaryContact,
            'last_user_activity_by' => $this->last_user_activity_by,
            'last_user_activity_by_typed' => $this->last_user_activity_by_typed,
            'last_user_activity_by_typed_short' => $this->last_user_activity_by_typed_short,
            'last_user_activity_by_partial' => $this->last_user_activity_by?->only('id', 'name'),
            'last_user_activity_by_mostly' => $this->last_user_activity_by?->except(['id', 'name']),
        ];
    }
}
