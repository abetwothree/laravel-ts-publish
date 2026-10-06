<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable starts from a parent toArray() whose fallback left a return unread.
 *
 * @mixin Tag
 */
class ReturnedDecliningParentChildResource extends ReturnedDecliningParentResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'name' => $this->name];
        }

        $data = parent::toArray($request);
        $data['extra'] = true;

        return $data;
    }
}
