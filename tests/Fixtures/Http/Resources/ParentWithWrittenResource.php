<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose constructor replaces `$with`, so `parent::with()` no longer returns its default.
 *
 * @mixin Tag
 */
class ParentWithWrittenResource extends JsonResource
{
    /** @var array<string, mixed> */
    public $with = ['version' => '1.0'];

    /**
     * Wrap the tag and replace the default top-level keys.
     */
    public function __construct(mixed $resource)
    {
        parent::__construct($resource);

        $this->with = ['late' => true];
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        $data = parent::with($request);
        $data['extra'] = 'x';

        return $data;
    }
}
