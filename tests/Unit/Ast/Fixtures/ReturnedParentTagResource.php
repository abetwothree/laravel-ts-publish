<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * Starts its variable from `parent::toArray()`, which writes Tag's relations only once loaded, and adds one key.
 *
 * @mixin Tag
 */
final class ReturnedParentTagResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['extra'] = 'x';

        return $data;
    }
}
