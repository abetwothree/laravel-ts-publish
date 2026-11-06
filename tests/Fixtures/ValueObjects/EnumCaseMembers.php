<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use LogicException;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;

/**
 * Reads `->value` and `->name` off enum cases themselves, alone, as ternary or `match` arms, beside null or a `throw`,
 * and a method off a `match` of cases.
 */
final class EnumCaseMembers
{
    public bool $flag = true;

    public int $pick = 1;

    /**
     * The members each case sends.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'value' => Grade::Pass->value,
            'int_value' => Status::Published->value,
            'name' => Grade::Pass->name,
            'ternary' => ($this->flag ? Grade::Pass : Grade::Fail)->value,
            'matched' => (match ($this->pick) {
                1 => Grade::Pass,
                default => Grade::Fail,
            })->value,
            'arm_or_null' => $this->flag ? Grade::Pass->value : null,
            'nullsafe' => ($this->flag ? Grade::Pass : null)?->value,
            'matched_or_throw' => (match ($this->pick) {
                1 => Grade::Pass,
                default => throw new LogicException('No grade'),
            })->value,
            'matched_label' => (match ($this->pick) {
                1 => Priority::High,
                2 => Priority::Low,
                default => throw new LogicException('No priority'),
            })->label(),
        ];
    }
}
