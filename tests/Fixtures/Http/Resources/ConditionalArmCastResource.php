<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose cast is a conditional type whose last arm names a model another key reads.
 *
 * @mixin Post
 */
#[TsCasts(['k' => ['type' => 'Foo extends Bar ? string : number | User', 'import' => '@/types/meta']])]
class ConditionalArmCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['k' => $this->title, 'author' => $this->author];
    }
}
