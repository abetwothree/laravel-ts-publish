<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;

class MergeArrayMergeChildResource extends MergeParentResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'who' => $this->last_user_activity_by,
        ]);
    }
}
