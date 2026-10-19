<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose `with()` unsets a key of the `$with` default `parent::with()` returns.
 *
 * @mixin Tag
 */
class ReturnedUnsetWithKeyResource extends JsonResource
{
    /** @var array<string, mixed> */
    public $with = ['version' => '1.0', 'debug' => true];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        $data = parent::with($request);
        unset($data['debug']);

        return $data;
    }
}
