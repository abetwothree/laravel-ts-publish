<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The matches preg_match() and preg_match_all() write by their flags and their pattern's groups, read through `??`
 * and bare, a group read past a call that matched, beside a pattern the call computes.
 *
 * @mixin Post
 */
final class PregMatchFlagsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pattern = $request->has('pattern') ? '/(B)/' : '/(x)/';
        preg_match('/(B)(x)?/', (string) $this->title, $plain);
        preg_match('/(?<first>B)/', (string) $this->title, $named);
        preg_match('/(B)/', (string) $this->title, $offset, PREG_OFFSET_CAPTURE);
        preg_match('/(B)(x)?/', (string) $this->title, $unmatched, PREG_UNMATCHED_AS_NULL);
        preg_match('/(?<first>B)(?<second>x)?/', (string) $this->title, $namedUnmatched, PREG_UNMATCHED_AS_NULL);
        preg_match('/(?<first>B)/', (string) $this->title, $namedOffset, PREG_OFFSET_CAPTURE);
        preg_match('/(B)/', (string) $this->title, $zeroFlags, 0);
        preg_match_all('/(B)/', (string) $this->title, $all);
        preg_match($pattern, (string) $this->title, $computed);
        $firstMatched = preg_match('/(?<first>B)/', (string) $this->title, $matched) === 1 ? $matched['first'] : 'none';

        return [
            'plain' => $plain ?? [],
            'plain_direct' => $plain,
            'named' => $named ?? [],
            'named_direct' => $named,
            'named_first' => $named['first'] ?? null,
            'first_matched' => $firstMatched,
            'offset' => $offset ?? [],
            'unmatched' => $unmatched ?? [],
            'named_unmatched' => $namedUnmatched ?? [],
            'named_offset' => $namedOffset ?? [],
            'zero_flags' => $zeroFlags ?? [],
            'all' => $all ?? [],
            'all_direct' => $all,
            'computed' => $computed ?? [],
        ];
    }
}
