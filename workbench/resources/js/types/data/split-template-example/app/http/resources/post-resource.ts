import { type AsEnum } from '@tolki/ts';

import { Priority, Status, Visibility } from '../../enums';
import type { Comment } from '../../models';
import type { CommentResource } from '.';

/** @see Workbench\App\Http\Resources\PostResource */
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
    comments: Comment[];
    comments_limited: Comment[];
    published: boolean;
    rating_display: number;
    word_count: string;
    heading_content: { title: string; summary: string };
    publishable: boolean;
    comments_count: number;
    is_featured: boolean;
    category_is_first?: boolean | null;
    category_is_active?: boolean | null;
    category_breadcrumb?: string | null;
    comments_resolved?: CommentResource[];
    post_class_name: string;
    post_table_name: string;
    category_class_name?: string;
    category_table_name?: string;
}
