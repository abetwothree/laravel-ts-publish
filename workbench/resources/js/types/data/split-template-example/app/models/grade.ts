import { type AsEnum } from '@tolki/ts';

import { Grade as EnumsGrade } from '../enums';
import type { GradeType } from '../enums';

/**
 * Named like the enum it casts to, so its file declares `Grade` and imports the enum's const under an alias.
 *
 * @see Workbench\App\Models\Grade
 */
export interface Grade
{
    id: number;
    subject: string;
    grade: GradeType;
    created_at: string | null;
    updated_at: string | null;
}

export interface GradeResource extends Omit<Grade, 'grade'>
{
    grade: AsEnum<typeof EnumsGrade>;
}

export interface GradeAllResource extends GradeResource {}
