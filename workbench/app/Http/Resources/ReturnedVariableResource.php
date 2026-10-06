<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;
use Workbench\App\Services\QuoteLinesService;

/**
 * Builds its payload in a local variable and returns it: a key written on one path only publishes optional.
 *
 * @mixin Tag
 */
final class ReturnedVariableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
        ];

        if ($request->boolean('with_slug')) {
            $data['slug'] = $this->slug;
        }

        $data['posts_count'] = $this->whenCounted('posts');
        $data['quote'] = resolve(QuoteLinesService::class)->lines();

        return $data;
    }
}
