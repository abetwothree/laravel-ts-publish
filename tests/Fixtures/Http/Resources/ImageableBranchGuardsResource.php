<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * `if` tests on a `$this->prop` morphTo that assign what their blocks read: alone, with an `else`, through
 * `$this->resource`, and around a ternary testing the same chain for a narrower class.
 *
 * @mixin Image
 */
final class ImageableBranchGuardsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $assigned = null;

        if ($this->imageable instanceof Post) {
            $assigned = $this->imageable->title;
        }

        if ($this->imageable instanceof Post) {
            $branched = $this->imageable->title;
        } else {
            $branched = null;
        }

        if ($this->resource->imageable instanceof Post) {
            $proxied = $this->resource->imageable->title;
        } else {
            $proxied = null;
        }

        if ($this->imageable instanceof Model) {
            $nested = $this->imageable instanceof Post ? $this->imageable->title : null;
        } else {
            $nested = null;
        }

        return ['assigned' => $assigned, 'branched' => $branched, 'proxied' => $proxied, 'nested' => $nested];
    }
}
