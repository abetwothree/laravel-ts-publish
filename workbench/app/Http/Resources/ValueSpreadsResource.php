<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\CreditLine;

/**
 * Spreads a value that writes a key again, after a helper or a key and before a resource arm (GH-154).
 *
 * @property-read CreditLine $resource
 */
class ValueSpreadsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $afterHelper = ['by' => $this->resource->title];
        $afterKey = ['by' => $this->resource->title];
        $optional = [];

        if ($this->resource->flag) {
            $optional['by'] = $this->resource->title;
        }

        $beforeArm = ['name' => 1];
        $maybe = [];

        if ($this->resource->flag) {
            $maybe['name'] = 1;
        }

        $untyped = ['by' => json_decode('{"a":1}')];

        return [
            'helper_then_value' => [...$this->credited(), ...$afterHelper],
            'key_then_value' => ['by' => $this->resource->author, ...$afterKey],
            'helper_then_optional' => [...$this->credited(), ...$optional],
            'value_then_arm' => [...$beforeArm, ...MailRecipientResource::make($this->resource->author)->resolve($request)],
            'key_then_arm' => [
                'name' => $this->resource->author,
                ...$maybe,
                ...MailRecipientResource::make($this->resource->author)->resolve($request),
            ],
            'merged_when' => ['id' => 1, $this->mergeWhen($this->resource->flag, ['by' => $this->resource->author, ...$afterKey])],
            'by' => $this->resource->author,
            ...$untyped,
        ];
    }

    /**
     * The credit's own keys, which a later spread writes again.
     */
    private function credited(): array
    {
        return ['id' => 1, 'by' => $this->resource->author];
    }
}
