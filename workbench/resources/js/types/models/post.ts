import type { PriorityType, StatusType, VisibilityType } from '../enums';
import type { Category, Comment, Image, Tag, User } from './';

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
    options: unknown[] | null;
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

export interface PostMutators
{
    /** Title displayed in uppercase */
    title_display: string | null;
    /** Excerpt of the post content */
    excerpt: string | null;
    /** Estimated reading time formatted */
    reading_time: string;
}

export interface PostRelations
{
    // Relations
    author: User;
    category: Category;
    comments: Comment[];
    /** Polymorphic many-to-many with tags */
    tags: Tag[];
    /** Polymorphic images */
    images: Image[];
    // Counts
    author_count: number;
    category_count: number;
    comments_count: number;
    tags_count: number;
    images_count: number;
    // Exists
    author_exists: boolean;
    category_exists: boolean;
    comments_exists: boolean;
    tags_exists: boolean;
    images_exists: boolean;
}

export interface PostAll extends Post, PostMutators, PostRelations {}
