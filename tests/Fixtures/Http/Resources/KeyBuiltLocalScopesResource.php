<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose locals built by key writes are read where a closure or a helper writes its own of the
 * same name, where a closure captures one before a later write, and inside a block that writes one before the read.
 *
 * @mixin Tag
 */
class KeyBuiltLocalScopesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $m = ['outer' => 1];
        $early = function () use ($m) {
            return ['m' => $m];
        };
        $m['outer2'] = 2;
        $n = ['a' => 1];
        $nested = null;

        if ($request->boolean('x')) {
            $n['b'] = 'x';
            $nested = $n;
        }

        return [
            'own' => $this->when(true, function () {
                $m = ['inner' => 'x'];
                $m['inner2'] = true;

                return ['m' => $m];
            }),
            'own_text' => $this->when(true, function () {
                $m = 'x';
                $m .= 'y';

                return ['m' => $m];
            }),
            'early' => $this->when(true, $early),
            'helper' => $this->helper(),
            'outer' => $m,
            'nested' => $nested,
        ];
    }

    /**
     * An array the helper builds in a local named as one of toArray()'s.
     *
     * @return array<string, mixed>
     */
    private function helper(): array
    {
        $m = ['inner' => 'x'];
        $m['inner2'] = true;

        return ['m' => $m];
    }
}
