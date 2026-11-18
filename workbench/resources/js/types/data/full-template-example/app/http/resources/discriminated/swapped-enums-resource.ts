import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { PriorityType, StatusType as EnumsStatusType } from '../../../enums';

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
    stages: { primary: EnumsStatusType; secondary: CrmStatusType } | { primary: CrmStatusType; secondary: EnumsStatusType } | null;
    meta: { priority: PriorityType | null; stages: { primary: EnumsStatusType; secondary: CrmStatusType } | { primary: CrmStatusType; secondary: EnumsStatusType; extra: boolean } | null };
    shared: { status: EnumsStatusType; is_pinned: boolean; lead: { stage: CrmStatusType } | null };
}
