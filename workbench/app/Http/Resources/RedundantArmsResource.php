<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Case values a branch writes over the enum's alias, and an empty list over a list (GH-166).
 *
 * @mixin Post
 */
class RedundantArmsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = ['status' => $this->status->value, 'priorities' => [$this->priority]];

        if ($this->id > 1) {
            $data['status'] = $this->id > 2 ? Status::Draft->value : Status::Published->value;
            $data['priorities'] = [];
        }

        $data['status_or_draft'] = $this->id > 3 ? Status::Draft->value : $this->status->value;
        $data['priority_name'] = $this->id > 3 ? Priority::Low->name : $this->priority->name;

        return $data;
    }
}
