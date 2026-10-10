<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable is written in try, catch and switch bodies, paths that may not run.
 *
 * @mixin Tag
 */
class ReturnedGuardedVariableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        try {
            $data['slug'] = $this->slug;
        } catch (Throwable) {
            $data['failed'] = true;
        }

        switch ($request->query('mode')) {
            case 'full':
                $data['color'] = $this->color;

                break;
        }

        return $data;
    }
}
