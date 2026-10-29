<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource whose `toArray()` reads untyped array defaults only its subclass writes by element. */
class AppendedTagsBaseResource extends JsonResource
{
    protected $tags = ['base'];

    protected $empty = [];

    protected $keyed = ['a' => 1];

    protected $counts = ['a' => 1];

    protected $mixed = ['m'];

    protected $pruned = ['p'];

    protected $labels = ['a' => 'x'];

    protected $both = ['a'];

    protected $dynamic = ['a' => 1];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'tags' => $this->tags,
            'empty' => $this->empty,
            'keyed' => $this->keyed,
            'counts' => $this->counts,
            'mixed' => $this->mixed,
            'pruned' => $this->pruned,
            'labels' => $this->labels,
            'both' => $this->both,
            'dynamic' => $this->dynamic,
        ];
    }
}
