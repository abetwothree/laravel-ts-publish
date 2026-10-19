<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use JsonSerializable;
use ReturnTypeWillChange;

/**
 * A label whose jsonSerialize() declares no native return type; only its `@return` says it writes a string.
 */
class DockLabel implements JsonSerializable
{
    /**
     * The label json_encode() writes.
     *
     * @return string
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return 'dock';
    }
}
