<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The groups preg_match() writes, read through `??` and bare, beside a named group and a flag, which key or nest them,
 * and calls that may not run: over a value they would replace, over a name nothing else writes, over a name another
 * write or a flagged call reaches, and over a closure parameter, beside a parameter's call that always runs.
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

        $decoded = json_decode('{"x":1}', true);
        preg_match('/(.)/', (string) $this->title, $flagged, PREG_OFFSET_CAPTURE);

        if ($request->has('match')) {
            preg_match('/(.)/', (string) $this->title, $decoded);
            preg_match('/(.)/', (string) $this->title, $flagged);
            preg_match('/(.)/', (string) $this->title, $elseNull);
            preg_match('/(.)/', (string) $this->title, $elseNumber);
        } else {
            $elseNull = null;
            $elseNumber = 5;
        }

        $list = json_decode('[1]', true);
        $request->has('match') && preg_match('/(.)/', (string) $this->title, $list);

        return [
            'm' => $m ?? [],
            'bare' => $m,
            'named' => $named ?? [],
            'offsets' => $offsets ?? [],
            'kept' => $kept,
            'maybe' => $maybe ?? [],
            'decoded' => $decoded,
            'flagged' => $flagged,
            'else_null' => $elseNull,
            'else_number' => $elseNumber,
            'list' => $list,
            'parameter' => collect(['ab'])->map(function (string $s) use ($request) {
                if ($request->has('match')) {
                    preg_match('/(.)/', $s, $s);
                }

                return $s;
            })->all(),
            'listed_parameter' => collect([['x']])->map(function (array $l) use ($request) {
                if ($request->has('match')) {
                    preg_match('/(.)/', 'ab', $l);
                }

                return $l;
            })->all(),
            'run_parameter' => collect(['ab'])->map(function (string $s) {
                preg_match('/(.)/', $s, $s);

                return $s;
            })->all(),
        ];
    }
}
