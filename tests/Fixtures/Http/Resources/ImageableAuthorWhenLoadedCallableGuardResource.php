<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\AuthorSwapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\User;

/**
 * An `if` testing a `$this->prop` morphTo's author, whose block hands the morph to `whenLoaded()` with a first-class
 * callable replacing the author.
 *
 * @mixin Image
 */
final class ImageableAuthorWhenLoadedCallableGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable->author instanceof User) {
            $this->whenLoaded('imageable', AuthorSwapper::swap(...));

            return ['k' => $this->imageable->author->getKey(), 'n' => $this->imageable->author->name];
        }

        return ['k' => null, 'n' => null];
    }
}
