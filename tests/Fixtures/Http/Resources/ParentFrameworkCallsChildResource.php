<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Models\Tag;

/**
 * A test-only subclass that declares only `toArray()`: its parent's `parent::` calls run this class's `toArray()`.
 *
 * @mixin Tag
 */
class ParentFrameworkCallsChildResource extends ParentFrameworkCallsResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'slug' => $this->slug];
    }
}
