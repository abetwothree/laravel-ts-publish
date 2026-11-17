import type { PriorityKind, PriorityType, StatusType } from '../../enums';

/**
 * Case values a branch writes over the enum's alias, and an empty list over a list (GH-166).
 *
 * @see Workbench\App\Http\Resources\RedundantArmsResource
 */
export interface RedundantArmsResource
{
    status: StatusType | 0 | 1;
    priorities: (PriorityType | null)[] | never[];
    status_or_draft: 0 | StatusType;
    priority_name: 'Low' | PriorityKind;
}
