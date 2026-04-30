import { type AsEnum } from '@tolki/ts';

import { Priority, Status, Visibility } from '../../enums';
import type { User } from '../../models';

/**
 * @see Workbench\App\Http\Resources\PostResource
 */
export interface PostResource
{
    morphValue: string;
    id: number;
    title: string;
    content: string;
    status: AsEnum<typeof Status>;
    status_new: AsEnum<typeof Status>;
    visibility: AsEnum<typeof Visibility> | null;
    visibility_new: AsEnum<typeof Visibility> | null;
    priority: AsEnum<typeof Priority> | null;
    priority_new: AsEnum<typeof Priority> | null;
    comments: { id: number; content: string; user: User }[];
}
