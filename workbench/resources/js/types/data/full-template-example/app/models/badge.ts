import { type AsEnum } from '@tolki/ts';

import { ClearanceType as CrmClearanceType } from '../../crm/enums';
import { Clearance, Grade as EnumsGrade } from '../enums';
import type { ClearanceTypeType } from '../../crm/enums';
import type { ClearanceType, GradeType } from '../enums';
import type { Grade } from '.';

/**
 * Imports names that cross between types and consts: Clearance's type and ClearanceType's const are both
 * `ClearanceType`, and the Grade model's type and the Grade enum's const are both `Grade`.
 *
 * @see Workbench\App\Models\Badge
 */
export interface Badge
{
    // Columns
    id: number;
    label: string;
    clearance: ClearanceType;
    clearance_type: ClearanceTypeType;
    minimum_grade: GradeType;
    grade_id: number;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    /** The grade record the badge was awarded for. */
    grade: Grade;
    // Counts
    grade_count: number;
    // Exists
    grade_exists: boolean;
}

export interface BadgeResource extends Omit<Badge, 'clearance' | 'clearance_type' | 'minimum_grade'>
{
    clearance: AsEnum<typeof Clearance>;
    clearance_type: AsEnum<typeof CrmClearanceType>;
    minimum_grade: AsEnum<typeof EnumsGrade>;
}
