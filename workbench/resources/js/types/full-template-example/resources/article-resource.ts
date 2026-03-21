import { type AsEnum } from '@tolki/enum';

import { ArticleStatus, ContentType } from '../enums';
import type { User } from '../models';
import type { ReactionResource } from './';

/** Exercises: multiple EnumResource::make, when(cond, Resource::collection), whenLoaded bare (cross-module App\User as author), whenNotNull, whenCounted, whenAggregated, when conditional with direct property. */
export interface ArticleResource
{
    id: number;
    title: string;
    slug: string;
    excerpt?: string | null;
    body: string;
    status: AsEnum<typeof ArticleStatus>;
    content_type: AsEnum<typeof ContentType>;
    is_featured: boolean;
    featured_image?: string | null;
    meta_description?: string | null;
    published_at?: string;
    author?: User;
    reactions?: ReactionResource[];
    reactions_count?: number;
    reactions_avg?: number;
}
