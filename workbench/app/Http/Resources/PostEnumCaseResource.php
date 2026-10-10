<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A bare enum case in every position a resource key reads one, with two enums that share a basename. A backed case
 * sends its value; a pure case would make the response throw, so none is here.
 *
 * @mixin Post
 */
class PostEnumCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => Status::Published,
            'crm_status' => CrmStatus::Active,
            'kept_status' => $this->status ?? Status::Draft,
            'priority_or_low' => $this->priority ?? Priority::Low,
            'flagged' => $this->title !== '' ? Status::Published : null,
            'either' => $this->title !== '' ? Status::Published : CrmStatus::Lead,
            'when_published' => $this->when($this->title !== '', Status::Published),
            'when_default' => $this->when($this->title !== '', 'live', Status::Draft),
            'matched' => match ($this->title) {
                '' => Status::Draft,
                default => Status::Published,
            },
            'pair' => ['app' => Status::Draft, 'crm' => CrmStatus::Lead],
            'cases' => [Status::Draft, Status::Published],
            // A case beside a read of the same enum: one element type, one import.
            'case_list' => [Status::Draft, $this->status],
            'wrapped' => EnumResource::make(Status::Draft),
            'wrapped_kept' => EnumResource::make($this->status ?? Status::Draft),
        ];
    }
}
