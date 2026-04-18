import { type AsEnum } from '@tolki/enum';

import { Priority, Status, Visibility } from '../enums';
import type { PriorityType, StatusType, VisibilityType } from '../enums';
import type { User } from '../models';

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
    comments: { id: number; content: string; user: User }[];
}
