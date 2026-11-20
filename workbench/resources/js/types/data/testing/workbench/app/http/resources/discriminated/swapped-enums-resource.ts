import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { PriorityType, StatusType as WorkbenchStatusType } from '../../../enums';

/**
 * Objects holding two same-named enums, one of them inside a nested value: each literal stays flat, since a union of
 * such objects reads one object's enum for the next one's token. Swapped at the top level, swapped nested with
 * another text, and a shared key beside a dependent's nested enum (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\SwappedEnumsResource
 */
export interface SwappedEnumsResource
{
    priority: PriorityType | null;
    stages: { primary: WorkbenchStatusType; secondary: CrmStatusType } | { primary: CrmStatusType; secondary: WorkbenchStatusType } | null;
    meta: { priority: PriorityType | null; stages: { primary: WorkbenchStatusType; secondary: CrmStatusType } | { primary: CrmStatusType; secondary: WorkbenchStatusType; extra: boolean } | null };
    shared: { status: WorkbenchStatusType; is_pinned: boolean; lead: { stage: CrmStatusType } | null };
}
