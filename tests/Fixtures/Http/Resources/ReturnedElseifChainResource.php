<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable re-sets a key in an if/elseif chain with no else, where neither may run.
 *
 * @mixin Tag
 */
class ReturnedElseifChainResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'tier' => 0];

        if ($request->query('tier') === 'a') {
            $data['tier'] = 'first';
            $data['first_only'] = true;
        } elseif ($request->query('tier') === 'b') {
            $data['tier'] = false;
        }

        return $data;
    }
}
