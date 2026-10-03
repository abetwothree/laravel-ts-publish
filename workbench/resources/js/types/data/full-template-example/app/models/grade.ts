import { type AsEnum } from '@tolki/ts';

import { Grade } from '../enums';
import type { GradeType } from '../enums';

/**
 * Named like the enum it casts to, so its file declares `Grade` and imports a const named `Grade`.
 *
 * @see Workbench\App\Models\Grade
 */
export interface Grade
{
    // Columns
    id: number;
    subject: string;
    grade: GradeType;
    created_at: string | null;
    updated_at: string | null;
}

export interface GradeResource extends Omit<Grade, 'grade'>
{
    grade: AsEnum<typeof Grade>;
}
