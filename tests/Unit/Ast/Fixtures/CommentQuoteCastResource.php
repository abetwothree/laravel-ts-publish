<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides a model, an enum and a `#[TsType]` read each with a block comment holding an apostrophe, then the type,
 * then a quoted string.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'app' => "/* it's */ User | 'x'",
    'state' => "/* it's */ StatusType | 'x'",
    'settings' => "/* it's */ MenuSettingsType | 'x'",
])]
final class CommentQuoteCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'app' => $this->manager,
            'state' => $this->status,
            'settings' => $this->menu_config,
        ];
    }
}
