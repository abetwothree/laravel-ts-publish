<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose `with()`, `resolve()` and `jsonSerialize()` each build on the JsonResource method they
 * override: Laravel returns `$with` for the first, and the resource's own `toArray()` and `resolve()` for the others.
 *
 * @mixin Tag
 */
class ParentFrameworkCallsResource extends JsonResource
{
    /** @var array<string, mixed> */
    public $with = ['version' => '1.0', 'flags' => ['beta' => true]];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        $data = parent::with($request);
        $data['extra'] = 'x';

        return $data;
    }

    /** @return array<string, mixed> */
    public function resolve($request = null): array
    {
        return [...parent::resolve($request), 'resolved' => true];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['serialized'] = 1;

        return $data;
    }
}
