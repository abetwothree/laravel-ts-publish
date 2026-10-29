<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The root of a chain whose middle class's helper reaches this toArray() through `parent::`. */
class DeclaredReadsRootResource extends JsonResource
{
    public const LEVEL = 'root';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['root' => 1];
    }
}
