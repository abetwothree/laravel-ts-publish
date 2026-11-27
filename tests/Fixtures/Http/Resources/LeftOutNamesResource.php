<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose warnings must name the value each leaves out: a local's key that defaults to itself, which
 * the walk also reads under an alias of its own, and the parent's two-key return beside a `@return` that types one.
 *
 * @mixin Post
 */
final class LeftOutNamesResource extends ReturnedKeyLeftBesideResource
{
    /**
     * A key read back as its own default, and the parent's pair under a `@return` that types only `a`.
     *
     * @return array{pair: array{a: string}}
     */
    public function toArray(Request $request): array
    {
        return ['own_default' => $this->ownDefault(), 'pair' => [...$this->pair()]];
    }

    /**
     * A key read back as its own default.
     */
    private function ownDefault(): array
    {
        $o = ['k' => null];
        $o['k'] = $o['k'] ?? $this->title;

        return $o;
    }
}
