<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Services\PriceQuoteService;

/**
 * A vague `: array` helper reached two ways — through a container instance and as a static call — so
 * both reflection sites fall back to the literal body.
 *
 * @mixin Post
 */
final class ServiceReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'quote' => resolve(PriceQuoteService::class)->quote(),
            'tiers' => PriceQuoteService::tierLabels(),
        ];
    }
}
