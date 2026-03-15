import { type AsEnum } from '@tolki/enum';

import { ArticleStatus, ContentType } from '../enums';
import type { ArticleStatusType, ContentTypeType } from '../enums';
import type { Reaction, User } from './';

export interface Article
{
    // Columns
    id: number;
    user_id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string;
    status: ArticleStatusType;
    content_type: ContentTypeType;
    featured_image: string | null;
    meta_description: string | null;
    is_featured: boolean;
    published_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    // Relations
    author: User;
    reactions: Reaction[];
    // Counts
    author_count: number;
    reactions_count: number;
    // Exists
    author_exists: boolean;
    reactions_exists: boolean;
}

export interface ArticleResource extends Omit<Article, 'status' | 'content_type'>
{
    status: AsEnum<typeof ArticleStatus>;
    content_type: AsEnum<typeof ContentType>;
}
