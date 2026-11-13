<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource writing a key, then writing it again through a helper, a local or a merge that may leave it.
 *
 * @property-read FoldBox $resource
 */
final class SpreadFoldMaybeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $maybeApp = [];
        $maybeCrm = [];
        $maybeTitle = [];

        if ($request->boolean('picked')) {
            $maybeApp['u'] = $this->resource->author;
            $maybeCrm['c'] = $this->resource->crmStatus;
            $maybeTitle['m'] = $this->resource->title;
        }

        return [
            ...$this->appUser(),
            ...$this->maybeCrmUser($request),
            ...$this->authored(),
            ...$this->titledWhenPicked($request),
            'row_user' => ['u' => $this->resource->crmAuthor, ...$maybeApp],
            'row_status' => ['c' => EnumResource::make($this->resource->status), ...$maybeCrm],
            $this->mergeWhen($request->boolean('flagged'), ['m' => $this->resource->author, ...$maybeTitle]),
        ];
    }

    /** @return array<string, mixed> */
    private function appUser(): array
    {
        return ['u' => $this->resource->author];
    }

    /** @return array<string, mixed> */
    private function maybeCrmUser(Request $request): array
    {
        $out = [];

        if ($request->boolean('picked')) {
            $out['u'] = $this->resource->crmAuthor;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function authored(): array
    {
        return ['by' => $this->resource->author];
    }

    /** @return array<string, mixed> */
    private function titledWhenPicked(Request $request): array
    {
        if ($request->boolean('picked')) {
            return ['by' => $this->resource->title];
        }

        return [];
    }
}
