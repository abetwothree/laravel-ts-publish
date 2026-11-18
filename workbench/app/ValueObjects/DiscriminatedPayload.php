<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Workbench\App\Enums\Status;

/**
 * Fixture (GH-157): keys that follow a sibling key's value, read through `AstEngine::analyze()`: a boolean at the top
 * level and an enum `match` inside a nested literal.
 */
final class DiscriminatedPayload
{
    public bool $archived = false;

    public Status $status = Status::Draft;

    public ?string $reason = null;

    public int $count = 0;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'archived' => $this->archived,
            'reason' => $this->archived ? $this->reason : null,
            'meta' => [
                'status' => $this->status,
                'count' => $this->count,
                'detail' => match ($this->status) {
                    Status::Draft => $this->reason,
                    Status::Published => $this->count,
                },
            ],
        ];
    }
}
