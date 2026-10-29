<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Priority;

/**
 * Fixture: members read off an enum case itself, a local holding one, a ternary of cases and the cases from() and
 * tryFrom() build, rather than off a typed property.
 */
final class EnumCasePayload
{
    public ?string $code = null;

    public bool $strict = false;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $fallback = Grade::Fail;

        return [
            'case_value' => Grade::Pass->value,
            'case_name' => Priority::High->name,
            'local_value' => $fallback->value,
            'picked' => ($this->strict ? Grade::Fail : Grade::Pass)->value,
            'parsed' => Grade::from('pass')->value,
            'maybe' => Grade::tryFrom((string) $this->code)?->value,
            'label' => Priority::High->label(),
        ];
    }
}
