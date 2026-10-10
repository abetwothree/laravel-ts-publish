<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose array key is a class constant holding text that reads as an index signature.
 *
 * @mixin Post
 */
class SignatureTextConstantKeyResource extends JsonResource
{
    public const string K = '[key: string]';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [self::K => 5, 'x' => 1];
    }
}
