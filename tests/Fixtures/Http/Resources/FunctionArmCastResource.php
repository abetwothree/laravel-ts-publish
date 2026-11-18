<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose cast is a function type whose return names a model another key reads.
 *
 * @mixin Post
 */
#[TsCasts(['k' => ['type' => '(x: number) => string | User', 'import' => '@/types/meta']])]
class FunctionArmCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['k' => $this->title, 'author' => $this->author];
    }
}
