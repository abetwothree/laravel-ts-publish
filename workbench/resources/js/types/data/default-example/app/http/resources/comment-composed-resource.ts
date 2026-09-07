import { type AsEnum } from '@tolki/ts';

import { Priority, Status, Visibility } from '../../enums';
import type { MembershipLevelType, RoleType } from '../../enums';
import type { Comment, Tag, User } from '../../models';
import type { CommentResource } from '.';

/**
 * Three key-less spreads at the top level of toArray(): a resource's resolve(), a model's toArray(),
 * and a collection's toArray(). Each flattens into this resource's own properties.
 *
 * @see Workbench\App\Http\Resources\CommentComposedResource
 */
export interface CommentComposedResource
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
    comments_limited: Pick<Comment, 'id' | 'content'>[];
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
    name: string;
    email: string;
    email_verified_at: string | null;
    password: string;
    options: Record<string, unknown> | null;
    remember_token: string | null;
    created_at: string | null;
    updated_at: string | null;
    role: RoleType | null;
    membership_level: MembershipLevelType | null;
    phone: string | null;
    avatar: string | null;
    bio: string | null;
    settings: { theme: "light" | "dark"; notifications: boolean; locale: string } | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    [key: number]: Tag;
}
