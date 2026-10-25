<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fixture: a model-less resource whose `toArray()` calls and spreads helpers NoticeResource overrides, spreads the
 * `with()` it leaves to JsonResource, and reads a constant and an untyped property NoticeResource redeclares.
 */
class BaseNoticeResource extends JsonResource
{
    public const LABEL = 'base';

    protected $tier = 'gold';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'headline' => $this->headline(),
            'label' => static::LABEL,
            'tier' => $this->tier,
            ...$this->extra(),
            ...$this->with($request),
        ];
    }

    public function headline(): int|string
    {
        return 0;
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['base_only' => 1];
    }
}
