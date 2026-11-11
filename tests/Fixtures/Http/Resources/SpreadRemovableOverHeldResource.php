<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource writing held keys again through helpers whose arrays hold a when() value, passed in or local.
 *
 * @mixin Post
 */
final class SpreadRemovableOverHeldResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seeded' => $this->author,
            ...$this->defaulted(['seeded' => $this->when($request->boolean('picked'), $this->title)]),
            'local' => $this->author,
            ...$this->whenLocal($request),
        ];
    }

    /**
     * The given array with `seeded` defaulted, which a when() value's MissingValue never takes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function defaulted(array $data): array
    {
        $data['seeded'] ??= 'none';

        return $data;
    }

    /** @return array<string, mixed> */
    private function whenLocal(Request $request): array
    {
        $local = ['local' => $this->when($request->boolean('picked'), $this->title)];

        return ['n' => 1, ...$local];
    }
}
