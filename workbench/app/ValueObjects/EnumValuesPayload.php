<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Workbench\App\Enums\Color;
use Workbench\App\Enums\ExcludedEnum;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Visibility;

/**
 * Fixture: every kind of enum whose `->value` and `->name` a payload reads: string- and int-backed, pure, one whose
 * cases #[TsCase] renames and revalues, one #[TsExclude] keeps out of publishing, a list mapped to its values, and a
 * case name read inside a nested array.
 */
final class EnumValuesPayload
{
    public ?Grade $grade = null;

    public Grade $fallbackGrade = Grade::Pass;

    public Priority $priority = Priority::Low;

    public Visibility $visibility = Visibility::Public;

    public Color $color = Color::Amber;

    public ExcludedEnum $excluded = ExcludedEnum::Foo;

    /** @var list<Grade> */
    public array $grades = [Grade::Pass];

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'grade' => $this->grade?->value,
            'fallback_grade' => $this->fallbackGrade->value,
            'grade_name' => $this->fallbackGrade->name,
            'priority' => $this->priority->value,
            'priority_name' => $this->priority->name,
            'visibility' => $this->visibility->name,
            'color' => $this->color->value,
            'color_name' => $this->color->name,
            'excluded' => $this->excluded->value,
            'excluded_name' => $this->excluded->name,
            'grade_list' => collect($this->grades)->map(fn (Grade $g) => $g->value)->all(),
            'meta' => ['grade_name' => $this->fallbackGrade->name],
        ];
    }
}
