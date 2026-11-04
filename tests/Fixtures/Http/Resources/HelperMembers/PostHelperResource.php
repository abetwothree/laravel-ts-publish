<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/** A post's enum, author and filtered author, each shape written in place and returned by a bare `: array` helper. */
final class PostHelperResource extends JsonResource
{
    public const KINDS = [Status::Draft, Status::Published];

    /** @var Post */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'in_s' => ['s' => EnumResource::make($this->status), 'title' => (string) $this->title],
            'helper_s' => $this->withEnumResource(),
            'in_card' => ['author' => $this->author, 'seen' => true],
            'helper_card' => $this->decorate(['author' => $this->author]),
            'in_cast' => ['v' => $this->status],
            'helper_cast' => $this->withCast(),
            'in_constant' => ['v' => self::KINDS],
            'helper_constant' => $this->withConstant(),
            'in_inline' => ['v' => [Status::Draft, Status::Published]],
            'helper_inline' => $this->withInlineCases(),
            'in_collected' => ['v' => collect([Status::Draft])->all()],
            'helper_collected' => $this->withCollectedCases(),
            'in_single' => ['v' => Status::Draft],
            'helper_single' => $this->withSingleCase(),
            'in_filtered' => ['author' => $this->author->only('id', 'name')],
            'helper_filtered' => $this->withFilteredAuthor(),
            'in_built' => ['author' => $this->author, 'title' => (string) $this->title],
            'helper_built' => self::make($this->resource)->authorCard(),
        ];
    }

    /** The author beside the title, called on a resource the body builds. */
    public function authorCard(): array
    {
        return ['author' => $this->author, 'title' => (string) $this->title];
    }

    /** The status wrapped in an enum resource beside the title. */
    private function withEnumResource(): array
    {
        return ['s' => EnumResource::make($this->status), 'title' => (string) $this->title];
    }

    /** The given array, marked seen. */
    private function decorate(array $data): array
    {
        $data['seen'] = true;

        return $data;
    }

    /** The status column read through its cast. */
    private function withCast(): array
    {
        return ['v' => $this->status];
    }

    /** A class constant listing two cases. */
    private function withConstant(): array
    {
        return ['v' => self::KINDS];
    }

    /** A list literal of two cases. */
    private function withInlineCases(): array
    {
        return ['v' => [Status::Draft, Status::Published]];
    }

    /** A collected list of one case. */
    private function withCollectedCases(): array
    {
        return ['v' => collect([Status::Draft])->all()];
    }

    /** One case. */
    private function withSingleCase(): array
    {
        return ['v' => Status::Draft];
    }

    /** The author narrowed to two columns. */
    private function withFilteredAuthor(): array
    {
        return ['author' => $this->author->only('id', 'name')];
    }
}
