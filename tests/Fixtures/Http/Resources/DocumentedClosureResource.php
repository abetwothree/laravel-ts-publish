<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Closures whose bodies type nothing, documented by a `@return` or `@param` tag in each placement, beside one with a
 * native return type.
 *
 * @mixin Post
 */
final class DocumentedClosureResource extends JsonResource
{
    protected $label = 'none';

    /**
     * The value closures, one placement of their docblock each, and the label a closure argument writes.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'doc_arrow' => $this->whenHas('title', /** @return string|null */ fn ($t) => json_decode($t)),
            'doc_fn' => $this->whenHas('title', /** @return string|null */ function ($t) {
                return json_decode($t);
            }),
            'doc_named' => $this->when(true, value: /** @return int */ fn () => json_decode('1')),
            'doc_class' => $this->whenHas('title', /** @return Post|null */ fn ($t) => json_decode($t)),
            'native_return' => $this->whenHas('title', fn ($t): ?string => json_decode($t)),
            'doc_call' => /** @return string|null */ $this->whenHas('title', fn ($t) => json_decode($t)),
            /** @return string|null */
            'doc_item' => $this->whenHas('title', fn ($t) => json_decode($t)),
            'doc_vague' => $this->whenHas('title', /** @return mixed */ fn ($t) => json_decode($t)),
            'doc_param' => $this->label,
        ];
    }

    /**
     * Write the label from a closure argument whose parameter only the docblock before it types.
     */
    public function relabel(): static
    {
        array_map(/** @param int $n */ function ($n): void {
            $this->label = $n;
        }, [1]);

        return $this;
    }
}
