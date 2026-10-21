<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The groups preg_match() writes, read through `??` and bare, beside a named group and a flag, which key or nest them,
 * and two calls in an `if` that may not run: one over a string the call would replace, one over a name nothing binds.
 *
 * @mixin Post
 */
final class PregMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        preg_match('/(.)(.)/', (string) $this->title, $m);
        preg_match('/(?<first>.)/', (string) $this->title, $named);
        preg_match('/(.)/', (string) $this->title, $offsets, PREG_OFFSET_CAPTURE);
        $kept = 'none';

        if ($request->has('match')) {
            preg_match('/(.)/', (string) $this->title, $kept);
            preg_match('/(.)/', (string) $this->title, $maybe);
        }

        return [
            'm' => $m ?? [],
            'bare' => $m,
            'named' => $named ?? [],
            'offsets' => $offsets ?? [],
            'kept' => $kept,
            'maybe' => $maybe ?? [],
        ];
    }
}
