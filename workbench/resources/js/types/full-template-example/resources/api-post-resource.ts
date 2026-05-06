import { type AsEnum } from '@tolki/enum';

import { Priority, Status, Visibility } from '../enums';
import type { PriorityType, StatusType, VisibilityType } from '../enums';
import type { User } from '../models';
import type { CommentResource } from './';

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
    published: boolean;
    publishable: boolean;
    comments_count: number;
    is_featured: boolean;
    category_is_first?: boolean | null;
    category_is_active?: boolean | null;
    category_breadcrumb?: string | null;
    comments_resolved?: CommentResource[];
}
