import { type AsEnum } from '@tolki/enum';

import { Priority, Status, Visibility } from '../../enums';
import type { PriorityType, StatusType, VisibilityType } from '../../enums';

export interface ApiPostResource
{
    morphValue: string;
    id: number;
    title: string;
    content: string;
    status: StatusType;
    status_new: AsEnum<typeof Status>;
    visibility: VisibilityType | null;
    visibility_new: AsEnum<typeof Visibility> | null;
    priority: PriorityType | null;
    priority_new: AsEnum<typeof Priority> | null;
}
