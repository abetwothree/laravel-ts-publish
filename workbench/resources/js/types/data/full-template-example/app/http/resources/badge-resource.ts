import { type AsEnum } from '@tolki/ts';

import { ClearanceType as CrmClearanceType } from '../../../crm/enums';
import type { ClearanceType } from '../../enums';

/**
 * Reads one enum bare and wraps the other, so the file imports Clearance's type and ClearanceType's const: two
 * imports that would both be named `ClearanceType`.
 *
 * @see Workbench\App\Http\Resources\BadgeResource
 */
export interface BadgeResource
{
    id: number;
    label: string;
    clearance: ClearanceType;
    clearance_type: AsEnum<typeof CrmClearanceType>;
    summary: { clearance: ClearanceType; clearance_type: AsEnum<typeof CrmClearanceType> };
}
