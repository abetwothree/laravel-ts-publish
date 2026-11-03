import { defineEnum } from '@tolki/ts';

/**
 * Plain backed enum for Team::week_days — no #[TsEnum] family attributes and no
 * ArchTech\Enums traits. This fixture exercises AsEnumCollection casting, not
 * enum-feature generation (Status and Season already cover that).
 *
 * @see Workbench\App\Enums\WeekDays
 */
export const WeekDays = defineEnum({
    Monday: 'monday',
    Tuesday: 'tuesday',
    Wednesday: 'wednesday',
    Thursday: 'thursday',
    Friday: 'friday',
    Saturday: 'saturday',
    Sunday: 'sunday',
    backed: true,
    _cases: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
} as const);

export type WeekDaysType = 'monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday' | 'sunday';

export type WeekDaysKind = 'Monday' | 'Tuesday' | 'Wednesday' | 'Thursday' | 'Friday' | 'Saturday' | 'Sunday';

export type WeekDays = WeekDaysType;
