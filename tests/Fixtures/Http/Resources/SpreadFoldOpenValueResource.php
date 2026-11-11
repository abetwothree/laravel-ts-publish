<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;

/**
 * A test-only resource writing keys, then spreading a value that names one of them beside keys no name names.
 *
 * @property-read FoldBox $resource
 */
final class SpreadFoldOpenValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $openLiteral = [...(array) json_decode('{"z":"q"}', true), 'by' => $this->resource->title];
        $walked = ['by' => $this->resource->title];
        $walked = [...$walked, ...(array) json_decode('{"z":"q"}', true)];
        $signed = $this->resource->labels();
        $signed['by'] = $this->resource->title;
        $helped = $this->openBits();
        $extra = ['x' => 1];
        $titled = ['by' => $this->resource->title];
        $spreads = [...$this->authored(), ...$titled];
        $topTitled = ['top' => $this->resource->title];
        $topSpreads = [...$this->topAuthored(), ...$topTitled];
        $topWalked = ['walked_top' => $this->resource->title];
        $topWalked = [...$topWalked, ...(array) json_decode('{"zz":"q"}', true)];

        return [
            'open_literal' => ['by' => $this->resource->author, 'z' => 1, ...$openLiteral],
            'walked' => ['by' => $this->resource->author, 'z' => 1, ...$walked],
            'signature' => ['by' => $this->resource->author, 'z' => 1, ...$signed],
            'helper' => ['by' => $this->resource->author, 'z' => 1, ...$helped],
            'before_arm' => ['by' => 1, ...$walked, ...UserResource::make($this->resource->author)->resolve($request)],
            'literal_holding_local' => ['by' => $this->resource->author, ...['by' => $this->resource->title, ...$extra]],
            'literal_of_local' => ['by' => $this->resource->author, ...[...$titled]],
            'local_of_spreads' => ['by' => 1, ...$spreads],
            'top' => 1,
            ...$topSpreads,
            'walked_top' => $this->resource->author,
            'zz' => 1,
            ...$topWalked,
        ];
    }

    /** @return array<string, mixed> */
    private function openBits(): array
    {
        return [...(array) json_decode('{"z":"q"}', true), 'by' => $this->resource->title];
    }

    /** @return array<string, mixed> */
    private function authored(): array
    {
        return ['id' => 1, 'by' => $this->resource->author];
    }

    /** @return array<string, mixed> */
    private function topAuthored(): array
    {
        return ['top_id' => 1, 'top' => $this->resource->author];
    }
}
