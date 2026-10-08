<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;

/**
 * A test-only resource with no model whose returned variable starts from a parent that delegates to no model.
 */
class ReturnedBodylessParentChildResource extends ReturnedBodylessParentResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => 1, 'name' => 'x'];
        }

        $data = parent::toArray($request);
        $data['extra'] = true;

        return $data;
    }
}
