<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DroppedArms;

use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A flag, a pick and two users whose classes share a name, which the dropped-arm probes read (GH-161).
 */
final class ArmHolder
{
    public function __construct(
        public User $owner,
        public CrmUser $contact,
        public bool $flag = false,
        public string $pick = '',
    ) {}

    /**
     * A value published as an arrow.
     */
    public function arrow(): ArrowLabel
    {
        return new ArrowLabel;
    }
}
