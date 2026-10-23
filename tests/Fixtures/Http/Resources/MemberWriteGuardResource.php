<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * Member writes on a local between its early-exit `instanceof` guard and the reads after it. Each leaves the class the
 * guard proves, but only a member no column types, or a value its column holds, leaves every typed member as the proof
 * reads it. `imageable` is a morphTo over int-keyed Post and User and string-keyed Product.
 *
 * @mixin Image
 */
final class MemberWriteGuardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $m = $this->imageable;

        if (! $m instanceof Post) {
            return [];
        }

        $m->touched = true;

        return ['title' => $m->title, 'key' => $m->getKey()];
    }

    /**
     * @return array<string, mixed>
     */
    public function fitting(): array
    {
        $m = $this->imageable;

        if (! $m instanceof Post) {
            return [];
        }

        $m->title = 'x';

        return ['title' => $m->title];
    }

    /**
     * @return array<string, mixed>
     */
    public function nulled(Request $request): array
    {
        $m = $this->imageable;

        if (! $m instanceof Post) {
            return [];
        }

        if ($request->boolean('r')) {
            $m->title = null;
        }

        return ['title' => $m->title];
    }

    /**
     * @return array<string, mixed>
     */
    public function mistyped(): array
    {
        $m = $this->imageable;

        if (! $m instanceof Post) {
            return [];
        }

        $m->title = 5;

        return ['title' => $m->title];
    }

    /**
     * @return array<string, mixed>
     */
    public function misrelated(): array
    {
        $m = $this->imageable;

        if (! $m instanceof Post) {
            return [];
        }

        $m->author = 'not a user';

        return ['author' => $m->author];
    }
}
