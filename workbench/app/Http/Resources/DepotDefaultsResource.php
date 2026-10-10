<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Depot;

/**
 * Untyped properties of the resource's own, each typed from its default as a parameter default is. `name` shadows the
 * Depot column: PHP reads the declared property before JsonResource::__get() reaches the model, so the key sends null.
 *
 * @mixin Depot
 */
final class DepotDefaultsResource extends JsonResource
{
    protected $name;

    protected $stage = Status::Draft;

    protected $limits = ['daily' => 5, 'codes' => ['north', 'south']];

    protected $tags = ['bulk', 2];

    protected $notes = [];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stage' => $this->stage,
            'limits' => $this->limits,
            'tags' => $this->tags,
            'notes' => $this->notes,
        ];
    }
}
