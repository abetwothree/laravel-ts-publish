<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

/**
 * Fixture: the runtime class of BaseNoticeResource's `toArray()`, overriding the helper it calls, the helper it
 * spreads, the `with()` it spreads, and the constant and property it reads.
 */
class NoticeResource extends BaseNoticeResource
{
    public const LABEL = 7;

    protected $tier = 3;

    public function headline(): string
    {
        return 'x';
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        return ['meta' => 1];
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['child_only' => true];
    }
}
