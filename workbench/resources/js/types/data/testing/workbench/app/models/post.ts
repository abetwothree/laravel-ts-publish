import { type AsEnum } from '@tolki/ts';

import { Priority, Status, Visibility } from '../enums';
import type { PriorityType, StatusType, VisibilityType } from '../enums';
import type { Attachment, Category, Comment, Image, Tag, User } from '.';

/** @see Workbench\App\Models\Post */
export interface Post
{
    id: number;
    title: string;
    content: string;
    user_id: number;
    status: StatusType;
    published_at: string | null;
    metadata: Record<string, {title: string, content: string}>;
    rating: number | null;
    category: string;
    options: Record<string, string> | null;
    deleted_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    category_id: number | null;
    visibility: VisibilityType | null;
    priority: PriorityType | null;
    word_count: number | null;
    reading_time_minutes: number | null;
    featured_image_url: string | null;
    is_pinned: boolean;
}

export interface PostResource extends Omit<Post, 'status' | 'visibility' | 'priority'>
{
    status: AsEnum<typeof Status>;
    visibility: AsEnum<typeof Visibility> | null;
    priority: AsEnum<typeof Priority> | null;
}

export interface PostMutators
{
    /** Title displayed in uppercase */
    title_display: string | null;
    /** Excerpt of the post content */
    excerpt: string | null;
    /** Estimated reading time formatted */
    reading_time: string;
    /** Newest comment, typed by a single model FQCN and deliberately not appended. */
    latest_comment: Comment | null;
}

export interface PostRelations
{
    // Relations
    author: User;
    category_rel: Category | null;
    comments: Comment[];
    /** Polymorphic many-to-many with tags */
    tags: Tag[];
    /** Polymorphic images */
    images: Image[];
    /** Polymorphic attachment via a custom MorphOne subclass */
    attachment: Attachment | null;
    // Counts
    author_count: number;
    category_rel_count: number;
    comments_count: number;
    tags_count: number;
    images_count: number;
    attachment_count: number;
    // Exists
    author_exists: boolean;
    category_rel_exists: boolean;
    comments_exists: boolean;
    tags_exists: boolean;
    images_exists: boolean;
    attachment_exists: boolean;
}

export interface PostAll extends Post, PostMutators, PostRelations {}

export interface PostAllResource extends PostResource, PostMutators, PostRelations {}
