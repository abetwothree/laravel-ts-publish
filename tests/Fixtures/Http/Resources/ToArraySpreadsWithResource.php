<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose `toArray()` spreads its `$with` keys, through `$this->with()` and through a helper built
 * on `parent::with()`.
 *
 * @mixin Tag
 */
class ToArraySpreadsWithResource extends JsonResource
{
    /** @var array<string, mixed> */
    public $with = ['version' => '1.0'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, ...$this->with($request), ...$this->meta($request)];
    }

    /** @return array<string, mixed> */
    protected function meta(Request $request): array
    {
        $data = parent::with($request);
        $data['extra'] = 'x';

        return $data;
    }
}
