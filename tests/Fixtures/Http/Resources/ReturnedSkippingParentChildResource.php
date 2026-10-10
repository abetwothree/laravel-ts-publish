<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable starts from a parent toArray() whose sweep skipped a return.
 *
 * @mixin Tag
 */
class ReturnedSkippingParentChildResource extends ReturnedSkippingParentResource
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
