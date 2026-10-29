<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/**
 * A test-only resource that names the model its parent's helpers read.
 */
#[TsResource(model: Post::class)]
class ModelHelperResource extends ModelHelperBaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'via_protected' => $this->viaProtected(),
            'via_self' => $this->viaSelf(),
            'via_self_shape' => $this->viaSelfShape(),
            'prot' => $this->protectedTitle(),
        ];
    }
}
