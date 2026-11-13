<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AttributeTitleUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MagicTitleUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * Locals a branch replaces with a model that may answer a member it does not list, read past the branch through a
 * member only the old model lists: a user whose `__get()` or `getAttribute()` answers it, a value declared only as
 * `Model`, and an unbounded morphTo's.
 *
 * @mixin Post
 */
final class JoinedOpenModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $magic = $this->resource;

        if ($this->is_pinned) {
            $magic = $this->magicUser();
        }

        $attribute = $this->resource;

        if ($this->is_pinned) {
            $attribute = $this->attributeUser();
        }

        $any = $this->comments->first();

        if ($this->is_pinned) {
            $any = $this->anyModel();
        }

        $morphed = $this->comments->first();

        if ($this->is_pinned) {
            $morphed = $this->image()->imageable;
        }

        return [
            'magic_title' => $magic->title,
            'attribute_title' => $attribute->title,
            'any_content' => $any->content,
            'morphed_content' => $morphed->content,
        ];
    }

    /** A user whose `__get()` answers `title`. */
    private function magicUser(): MagicTitleUser
    {
        return new MagicTitleUser()->forceFill(['id' => 1, 'name' => 'Magic']);
    }

    /** A user whose `getAttribute()` answers `title`. */
    private function attributeUser(): AttributeTitleUser
    {
        return new AttributeTitleUser()->forceFill(['id' => 2, 'name' => 'Attribute']);
    }

    /** A model declared only as `Model`. */
    private function anyModel(): Model
    {
        return new Comment()->forceFill(['id' => 8, 'content' => 'any']);
    }

    /** An image whose unbounded morphTo holds a comment. */
    private function image(): Image
    {
        return new Image()->forceFill(['id' => 6])->setRelation('imageable', new Comment()->forceFill(['id' => 9, 'content' => 'morphed']));
    }
}
