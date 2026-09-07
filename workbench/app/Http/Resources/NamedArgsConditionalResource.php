<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The conditional family called with named arguments. PHP binds each argument to its parameter by name
 * and Laravel then tests func_num_args(), so a named `default:` is a real default however it is written.
 *
 * @mixin Post
 */
class NamedArgsConditionalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // A named default after a positional value: func_num_args() is 2, so the default unions in
            // and the key is required — `string | number`, the same as whenNotNull($x, 0).
            'not_null_named_default' => $this->whenNotNull($this->published_at, default: 0),

            // Every argument named: `string | number`, required.
            'when_all_named' => $this->when(condition: $this->id > 0, value: $this->title, default: 0),

            // A named default with no value: func_num_args() is 3, and Laravel swaps the null value for the
            // identity closure, so the loaded arm is the relation itself. `[]` is assignable to Comment[],
            // so the default arm folds away: `Comment[]`, required.
            'loaded_named_default' => $this->whenLoaded('comments', default: []),

            // The default written before the relationship: three arguments to Laravel, `number`, required.
            'counted_named_out_of_order' => $this->whenCounted(default: 0, relationship: 'comments'),
        ];
    }
}
