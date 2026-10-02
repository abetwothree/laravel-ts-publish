import { defineEnum } from '@tolki/ts';

/**
 * Shares its name with the Grade model, whose own file imports this enum's const.
 *
 * @see Workbench\App\Enums\Grade
 */
export const Grade = defineEnum({
    Pass: 'pass',
    Fail: 'fail',
    backed: true,
    _cases: ['Pass', 'Fail'],
} as const);

export type GradeType = 'pass' | 'fail';

export type GradeKind = 'Pass' | 'Fail';
