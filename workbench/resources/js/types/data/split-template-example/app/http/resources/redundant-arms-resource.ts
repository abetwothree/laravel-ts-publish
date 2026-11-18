import type { PriorityKind, PriorityType, StatusType } from '../../enums';

/**
 * Case values a branch writes over the enum's alias, and an empty list over a list (GH-166).
 *
 * @see Workbench\App\Http\Resources\RedundantArmsResource
 */
export interface RedundantArmsResource
{
    status: StatusType;
    priorities: (PriorityType | null)[];
    status_or_draft: StatusType;
    priority_name: PriorityKind;
}
