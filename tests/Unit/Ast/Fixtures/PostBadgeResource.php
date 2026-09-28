<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A badge PostCardResource spreads into both of its sections. */
final class PostBadgeResource extends JsonResource
{
    /** A literal badge. */
    public function toArray(Request $request): array
    {
        return ['badge' => 'new'];
    }
}
