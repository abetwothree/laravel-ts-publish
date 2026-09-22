<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides one key with a template literal that never closes, before a model read and an enum read whose types each
 * name the type and then open a template literal.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'label' => '`${string}\'s label',
    'app' => 'User | `x`',
    'state' => 'StatusType | `x`',
])]
final class UnclosedTemplateCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->id,
            'app' => $this->manager,
            'state' => $this->status,
        ];
    }
}
