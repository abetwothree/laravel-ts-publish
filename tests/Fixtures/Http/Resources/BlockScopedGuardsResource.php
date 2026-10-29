<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * Early-exit `instanceof` guards nested in a block, each for the rest of that block: in an `if` it returns from, a
 * loop body it continues, a `try` it throws from, and a `switch` case it leaves by `continue 2`. A read past each block
 * is unnarrowed. `imageable` is a morphTo over int-keyed Post and User and string-keyed Product.
 *
 * @mixin Image
 */
final class BlockScopedGuardsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->imageable;
        $data = [];

        if ($request->has('if')) {
            if (! $owner instanceof Post) {
                return $data;
            }

            $data['if_key'] = $owner->getKey();
        }

        foreach ([1] as $_) {
            if (! $owner instanceof Post) {
                continue;
            }

            $data['loop_key'] = $owner->getKey();
        }

        try {
            if (! $owner instanceof Post) {
                throw new RuntimeException('Not a post.');
            }

            $data['try_key'] = $owner->getKey();
        } catch (RuntimeException) {
        }

        foreach ([1] as $kind) {
            switch ($kind) {
                case 1:
                    if (! $owner instanceof Post) {
                        continue 2;
                    }

                    $data['case_key'] = $owner->getKey();
                    break;
            }

            $data['past_switch_key'] = $owner->getKey();
        }

        $data['after_blocks'] = $owner->getKey();

        return $data;
    }
}
