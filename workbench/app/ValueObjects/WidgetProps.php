<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Workbench\App\Enums\MediaType;
use Workbench\App\Enums\Priority;

/**
 * Fixture (GH-166): a key a branch rewrites with case values over an enum read, and a list property a branch empties.
 */
final class WidgetProps
{
    public protected(set) ?MediaType $kind = null;

    public string $method = 'get';

    public bool $blocked = false;

    /** @var list<Priority> */
    public protected(set) array $modes = [];

    /** @return array<string, mixed> */
    public function props(): array
    {
        $props = ['kind' => $this->kind?->value, 'method' => $this->method, 'modes' => $this->modes];

        if ($this->blocked) {
            $props['kind'] = $props['method'] === 'get' ? MediaType::Image->value : MediaType::Video->value;
            $props['modes'] = [];
        }

        return $props;
    }
}
