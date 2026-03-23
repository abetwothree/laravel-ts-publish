import type { PriorityType, StatusType, VisibilityType } from '../../enums';

export interface ApiPostResource
{
    morphValue: string;
    id: number;
    title: string;
    content: string;
    status: StatusType;
    visibility: VisibilityType | null;
    priority: PriorityType | null;
}
