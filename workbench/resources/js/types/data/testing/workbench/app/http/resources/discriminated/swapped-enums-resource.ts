import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as WorkbenchStatusType } from '../../../enums';

/**
 * Objects holding two same-named enums, one of them inside a nested value: each literal splits, every token naming its
 * own enum. Swapped at the top level, swapped nested with another text, and a shared key beside a dependent's nested
 * enum (GH-157, GH-76).
 *
 * @see Workbench\App\Http\Resources\Discriminated\SwappedEnumsResource
 */
export type SwappedEnumsResource = {
    meta: { priority: 0; stages: { primary: WorkbenchStatusType; secondary: CrmStatusType } } | { priority: 1; stages: { primary: CrmStatusType; secondary: WorkbenchStatusType; extra: boolean } } | { priority: 2 | 3 | null; stages: null };
    shared: { status: WorkbenchStatusType; is_pinned: true; lead: { stage: CrmStatusType } } | { status: WorkbenchStatusType; is_pinned: false; lead: null };
} & (
    | { priority: 0; stages: { primary: WorkbenchStatusType; secondary: CrmStatusType } }
    | { priority: 1; stages: { primary: CrmStatusType; secondary: WorkbenchStatusType } }
    | { priority: 2 | 3 | null; stages: null }
);
