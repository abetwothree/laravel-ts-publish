<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\User;

/**
 * An `if` testing a `$this->prop` morphTo's author, whose block hands the morph to a `whenLoaded()` callback that
 * only reads the author off it.
 *
 * @mixin Image
 */
final class ImageableAuthorWhenLoadedReadGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable->author instanceof User) {
            $this->whenLoaded('imageable', fn (Model $imageable) => $imageable->author->getKey());

            return ['k' => $this->imageable->author->getKey(), 'n' => $this->imageable->author->name];
        }

        return ['k' => null, 'n' => null];
    }
}
