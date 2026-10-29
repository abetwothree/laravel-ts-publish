<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose merge closures unset a key a local holds: one the closure imports, and one of its own
 * that shadows an outer local of the same name.
 *
 * @mixin Tag
 */
class MergeClosureLocalKeyUnsetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $k = 'slug';
        $hidden = 'name';

        return [
            'id' => $this->id,
            $this->merge(function () use ($k) {
                $d = ['name' => $this->name, 'slug' => $this->slug];
                unset($d[$k]);

                return $d;
            }),
            $this->merge(function () use ($request) {
                $hidden = $request->string('hide')->toString();
                $d = ['color' => $this->color, 'label' => $this->name];
                unset($d[$hidden]);

                return $d;
            }),
        ];
    }
}
