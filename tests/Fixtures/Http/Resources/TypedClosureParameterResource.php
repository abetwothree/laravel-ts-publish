<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\DockCode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\MapOption;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use UnitEnum;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * A closure parameter's native class type holds for every call, so reads through it type whatever the element is.
 *
 * @mixin Post
 */
class TypedClosureParameterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'codes' => collect([new DockCode('a')])->map(fn (DockCode $code) => ['id' => $code->code])->all(),
            'opaque_codes' => collect($this->opaqueRows())->map(fn (DockCode $code) => ['id' => $code->code])->all(),
            'spread_comments' => collect($this->comments)->map(fn (Comment $comment) => [...$comment->toArray(), 'flag' => true])->all(),
            'comment_authors' => collect($this->comments)->map(fn (Comment $comment) => $comment->user?->name)->all(),
            'options' => collect([new MapOption])->map(fn (MapOption $option) => ['id' => $option->itemId, 'd' => $option->doubled()])->all(),
            'nullable_options' => collect([new MapOption])->map(fn (?MapOption $option) => ['id' => $option->itemId])->all(),
            // The write replaces the parameter with another class, so the read past it takes that class.
            'replaced_options' => collect([new MapOption])->map(function (MapOption $option) {
                $option = new DockCode('a');

                return ['id' => $option->code];
            })->all(),
            // when() passes nothing, so each parameter holds its default, which says more than its declared class.
            'null_default' => $this->when(true, fn (?DockCode $code = null) => $code),
            'null_default_member' => $this->when(true, fn (?DockCode $code = null) => $code?->code),
            'enum_default' => $this->when(true, fn (BackedEnum $status = Status::Published) => $status->value),
            'unit_enum_default' => $this->when(true, fn (UnitEnum $status = Status::Published) => $status->name),
        ];
    }

    /** @return array<int, mixed> */
    private function opaqueRows(): array
    {
        return json_decode('[]', true);
    }
}
